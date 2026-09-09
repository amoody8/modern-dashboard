<?php
/**
 * Catalogue of commands the palette can run.
 *
 * The registry is the single authority on what a command is: the palette builds
 * its result list from it and the resolver authorizes against it, so adding a
 * command in one place teaches both sides at once.
 *
 * Capabilities are declared as capability *names*, never callables. A callable
 * cannot be validated or introspected before it runs, so a third party shipping
 * a careless one would become an authorization bypass. A definition that omits
 * the field falls back to `manage_options` — over-restrictive by accident is
 * recoverable, under-restrictive is not.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Palette;

use ModernDashboard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class CommandRegistry {

	/** Capability assumed when a definition does not declare one. */
	public const DEFAULT_CAPABILITY = 'manage_options';

	/** Sort weight applied when a definition does not declare one. */
	public const DEFAULT_PRIORITY = 50;

	/** @var array<string,array<string,mixed>>|null */
	private ?array $commands = null;

	/**
	 * Every registered command, keyed by id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		if ( null !== $this->commands ) {
			return $this->commands;
		}

		/**
		 * Filters the commands offered by the command palette.
		 *
		 * A command needs a `label` and an `action`. `capability` is a
		 * capability name checked with `current_user_can()` before the command
		 * is ever sent to the browser, so a command a user cannot run is never
		 * disclosed to them. Omitting it means `manage_options`.
		 *
		 * `context` limits a command to `network` or `site` screens; `any` is
		 * the default. `action` is one of:
		 *
		 *     array( 'type' => 'navigate', 'url' => 'admin_url:edit.php' )
		 *     array( 'type' => 'rest', 'path' => 'refresh', 'method' => 'POST' )
		 *     array( 'type' => 'client', 'handler' => 'copy-url' )
		 *
		 * @param array<string,array<string,mixed>> $commands Registered commands.
		 */
		$commands = apply_filters( 'modern_dashboard_commands', $this->core_commands() );

		$this->commands = array_map(
			array( $this, 'normalize' ),
			array_filter(
				(array) $commands,
				static fn( $definition ): bool => is_array( $definition )
					&& isset( $definition['label'], $definition['action'] )
					&& is_array( $definition['action'] )
			)
		);

		return $this->commands;
	}

	public function has( string $id ): bool {
		return isset( $this->all()[ $id ] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	/**
	 * Group labels, so the client renders headings without knowing the ids.
	 *
	 * @return array<string,string>
	 */
	public static function groups(): array {
		return array(
			'navigate'  => __( 'Go to', 'modern-dashboard' ),
			'network'   => __( 'Network', 'modern-dashboard' ),
			'dashboard' => __( 'Dashboard', 'modern-dashboard' ),
			'content'   => __( 'Content', 'modern-dashboard' ),
			'settings'  => __( 'Settings', 'modern-dashboard' ),
		);
	}

	/**
	 * Fill in the fields a definition is allowed to omit, so every consumer can
	 * read them unconditionally.
	 *
	 * @param array<string,mixed> $definition Raw definition.
	 *
	 * @return array<string,mixed>
	 */
	private function normalize( array $definition ): array {
		return array_merge(
			array(
				'group'      => 'navigate',
				'keywords'   => array(),
				'capability' => self::DEFAULT_CAPABILITY,
				'context'    => 'any',
				'icon'       => 'admin-generic',
				'priority'   => self::DEFAULT_PRIORITY,
			),
			$definition
		);
	}

	/**
	 * The commands every network gets.
	 *
	 * Admin destinations are deliberately hardcoded rather than derived from
	 * `MenuCatalogue`. The catalogue is observational and keyed by slug across
	 * the whole network, so a slug seen on one site may not exist on another —
	 * deriving from it would offer commands that lead nowhere. The core set is
	 * stable across WordPress versions; anything else belongs to the plugin
	 * that adds it, through the filter.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function core_commands(): array {
		return array_merge(
			$this->navigation_commands(),
			$this->network_commands(),
			$this->dashboard_commands(),
			$this->settings_commands()
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function navigation_commands(): array {
		$screens = array(
			'posts'      => array( 'edit.php', __( 'Posts', 'modern-dashboard' ), 'edit_posts', 'admin-post', array( 'articles', 'blog' ) ),
			'post-new'   => array( 'post-new.php', __( 'Add new post', 'modern-dashboard' ), 'edit_posts', 'plus', array( 'create', 'write', 'new' ) ),
			'media'      => array( 'upload.php', __( 'Media library', 'modern-dashboard' ), 'upload_files', 'admin-media', array( 'images', 'files', 'uploads' ) ),
			'pages'      => array( 'edit.php?post_type=page', __( 'Pages', 'modern-dashboard' ), 'edit_pages', 'admin-page', array() ),
			'page-new'   => array( 'post-new.php?post_type=page', __( 'Add new page', 'modern-dashboard' ), 'edit_pages', 'plus', array( 'create', 'new' ) ),
			'comments'   => array( 'edit-comments.php', __( 'Comments', 'modern-dashboard' ), 'moderate_comments', 'admin-comments', array( 'moderate', 'replies' ) ),
			'appearance' => array( 'themes.php', __( 'Themes', 'modern-dashboard' ), 'switch_themes', 'admin-appearance', array( 'appearance', 'design' ) ),
			'plugins'    => array( 'plugins.php', __( 'Plugins', 'modern-dashboard' ), 'activate_plugins', 'admin-plugins', array( 'extensions', 'addons' ) ),
			'users'      => array( 'users.php', __( 'Users', 'modern-dashboard' ), 'list_users', 'admin-users', array( 'people', 'accounts', 'members' ) ),
			'tools'      => array( 'tools.php', __( 'Tools', 'modern-dashboard' ), 'manage_options', 'admin-tools', array( 'import', 'export' ) ),
			'options'    => array( 'options-general.php', __( 'Settings', 'modern-dashboard' ), 'manage_options', 'admin-settings', array( 'options', 'configuration' ) ),
		);

		$commands = array();
		$priority = 10;

		foreach ( $screens as $key => list( $path, $label, $capability, $icon, $keywords ) ) {
			$commands[ 'go.' . $key ] = array(
				'label'      => $label,
				'group'      => 'navigate',
				'keywords'   => $keywords,
				'capability' => $capability,
				'context'    => 'site',
				'icon'       => $icon,
				'priority'   => $priority,
				'action'     => array(
					'type' => 'navigate',
					'url'  => 'admin_url:' . $path,
				),
			);

			++$priority;
		}

		return $commands;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function network_commands(): array {
		$screens = array(
			'sites'    => array( 'sites.php', __( 'All sites', 'modern-dashboard' ), 'admin-multisite', array( 'blogs', 'multisite', 'network' ) ),
			'site-new' => array( 'site-new.php', __( 'Add new site', 'modern-dashboard' ), 'plus', array( 'create', 'new blog' ) ),
			'updates'  => array( 'update-core.php', __( 'Updates', 'modern-dashboard' ), 'update', array( 'upgrade', 'version' ) ),
			'themes'   => array( 'themes.php', __( 'Network themes', 'modern-dashboard' ), 'admin-appearance', array() ),
			'plugins'  => array( 'plugins.php', __( 'Network plugins', 'modern-dashboard' ), 'admin-plugins', array() ),
			'users'    => array( 'users.php', __( 'Network users', 'modern-dashboard' ), 'admin-users', array( 'people', 'accounts' ) ),
			'settings' => array( 'settings.php', __( 'Network settings', 'modern-dashboard' ), 'admin-settings', array( 'options' ) ),
		);

		$commands = array();
		$priority = 30;

		foreach ( $screens as $key => list( $path, $label, $icon, $keywords ) ) {
			$commands[ 'network.' . $key ] = array(
				'label'      => $label,
				'group'      => 'network',
				'keywords'   => $keywords,
				'capability' => Capabilities::MANAGE,
				'context'    => 'any',
				'icon'       => $icon,
				'priority'   => $priority,
				'action'     => array(
					'type' => 'navigate',
					'url'  => 'network_admin_url:' . $path,
				),
			);

			++$priority;
		}

		return $commands;
	}

	/**
	 * The plugin's own screens.
	 *
	 * These navigate to a hash rather than carrying tab state in the URL path,
	 * because the dashboard is a single admin page whose tabs are client-side.
	 * `App` reads the hash on mount and follows `hashchange`.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function dashboard_commands(): array {
		$tabs = array(
			'overview' => array( __( 'Network overview', 'modern-dashboard' ), Capabilities::VIEW, array( 'stats', 'metrics', 'summary' ) ),
			'sites'    => array( __( 'Site list', 'modern-dashboard' ), Capabilities::VIEW, array( 'blogs', 'table' ) ),
			'builder'  => array( __( 'Dashboard builder', 'modern-dashboard' ), Capabilities::MANAGE, array( 'layout', 'blocks', 'template' ) ),
			'menus'    => array( __( 'Menu editor', 'modern-dashboard' ), Capabilities::MANAGE, array( 'admin menu', 'navigation' ) ),
			'branding' => array( __( 'Branding', 'modern-dashboard' ), Capabilities::MANAGE, array( 'theme', 'colours', 'colors', 'white label', 'login' ) ),
			'settings' => array( __( 'Dashboard settings', 'modern-dashboard' ), Capabilities::MANAGE, array( 'collection', 'cron', 'options' ) ),
		);

		$commands = array();
		$priority = 20;

		foreach ( $tabs as $tab => list( $label, $capability, $keywords ) ) {
			$commands[ 'dashboard.' . $tab ] = array(
				'label'      => $label,
				'group'      => 'dashboard',
				'keywords'   => $keywords,
				'capability' => $capability,
				'context'    => 'any',
				'icon'       => 'screenoptions',
				'priority'   => $priority,
				'action'     => array(
					'type' => 'navigate',
					'url'  => 'network_admin_url:admin.php?page=modern-dashboard#' . $tab,
				),
			);

			++$priority;
		}

		return $commands;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function settings_commands(): array {
		return array(
			'palette.disable' => array(
				'label'      => __( 'Disable the command palette for this page load', 'modern-dashboard' ),
				'group'      => 'settings',
				'keywords'   => array( 'turn off', 'hide', 'bypass', 'recover' ),
				'capability' => 'manage_options',
				'context'    => 'any',
				'icon'       => 'hidden',
				'priority'   => 90,
				'action'     => array(
					'type' => 'client',
					// Reloads with the bypass argument, so the recovery hatch is
					// discoverable from inside the thing it disables.
					'handler' => 'disable-palette',
				),
			),
		);
	}
}
