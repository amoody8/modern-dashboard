<?php
/**
 * Replaces the admin's navigation chrome.
 *
 * The skin restyles what WordPress draws. This goes further: it hides core's
 * sidebar and toolbar and renders our own in their place, so navigation is
 * something we design rather than something we recolour.
 *
 * The menu is still WordPress's. `$menu` and `$submenu` are read at render
 * time — after every plugin has registered its pages — and handed to the
 * client as data. So a plugin that adds a page appears in our sidebar with no
 * integration work, and the capability filtering core already did is inherited
 * rather than reimplemented.
 *
 * What is deliberately *not* replaced: the page content itself. Core screens
 * render exactly as they always did, inside our frame. That keeps every admin
 * page working — including ones from plugins we have never seen — and confines
 * the blast radius of this feature to the furniture.
 *
 * `?mdash-shell=off` restores the stock chrome for anyone who can
 * `manage_options`. This matters more than the other bypasses in this plugin:
 * a broken sidebar is a broken way out.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Admin;

use ModernDashboard\Plugin;
use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Shell {

	/** Query argument that restores the stock chrome for one request. */
	public const BYPASS_ARG = 'mdash-shell';

	private const HANDLE = 'modern-dashboard-shell';

	private Plugin $plugin;
	private Settings $settings;

	private ?bool $resolved = null;

	public function __construct( Plugin $plugin, Settings $settings ) {
		$this->plugin   = $plugin;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 21 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'in_admin_header', array( $this, 'render_mount' ) );
		add_action( 'admin_notices', array( $this, 'render_bypass_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_bypass_notice' ) );
	}

	public function enqueue(): void {
		if ( ! $this->active() ) {
			return;
		}

		$build = $this->plugin->dir() . 'build/';

		if ( ! is_readable( $build . 'shell.js' ) ) {
			return;
		}

		$meta = is_readable( $build . 'shell.asset.php' )
			? (array) require $build . 'shell.asset.php'
			: array(
				'dependencies' => array( 'wp-element', 'wp-i18n' ),
				'version'      => $this->plugin->version(),
			);

		wp_enqueue_script(
			self::HANDLE,
			$this->plugin->url() . 'build/shell.js',
			$meta['dependencies'],
			(string) $meta['version'],
			true
		);

		if ( is_readable( $build . 'shell.css' ) ) {
			wp_enqueue_style(
				self::HANDLE,
				$this->plugin->url() . 'build/shell.css',
				array(),
				(string) $meta['version']
			);

			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}

		wp_set_script_translations( self::HANDLE, 'modern-dashboard', $this->plugin->dir() . 'languages' );

		wp_add_inline_script(
			self::HANDLE,
			'window.modernDashboardShell = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * @param string $classes Existing body classes.
	 */
	public function body_class( string $classes ): string {
		if ( ! $this->active() ) {
			return $classes;
		}

		return trim( $classes . ' mds-shell' );
	}

	/**
	 * The mount point, printed inside the admin header so it sits above the
	 * page content in source order rather than being appended to the body.
	 */
	public function render_mount(): void {
		if ( ! $this->active() ) {
			return;
		}

		echo '<div id="mds-shell-root"></div>';
	}

	public function render_bypass_notice(): void {
		if ( ! $this->bypassed() ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e(
			'Showing the standard WordPress navigation. Reload without the mdash-shell query argument to bring the new one back.',
			'modern-dashboard'
		);
		echo '</p></div>';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function boot_data(): array {
		return array(
			'menu'      => $this->menu(),
			'current'   => $this->current_slug(),
			'title'     => $this->screen_title(),
			'siteName'  => get_bloginfo( 'name' ),
			'siteUrl'   => home_url( '/' ),
			'isNetwork' => is_network_admin(),
			'user'      => $this->user(),
			'links'     => array(
				'profile'  => admin_url( 'profile.php' ),
				'logout'   => wp_logout_url(),
				'newPost'  => admin_url( 'post-new.php' ),
				'siteHome' => home_url( '/' ),
				'network'  => is_multisite() && current_user_can( 'manage_network' )
					? network_admin_url()
					: null,
			),
			'bypassArg' => self::BYPASS_ARG,
		);
	}

	/**
	 * WordPress's own menu, as data.
	 *
	 * Read from the globals rather than rebuilt: they are the product of every
	 * plugin's `admin_menu` registration and of core's capability filtering, so
	 * anything else would be a reimplementation that drifts.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function menu(): array {
		global $menu, $submenu;

		$items = array();

		foreach ( (array) $menu as $entry ) {
			$slug = (string) ( $entry[2] ?? '' );

			if ( '' === $slug || str_starts_with( $slug, 'separator' ) ) {
				continue;
			}

			$capability = (string) ( $entry[1] ?? 'read' );

			if ( ! current_user_can( $capability ) ) {
				continue;
			}

			$children = array();

			foreach ( (array) ( $submenu[ $slug ] ?? array() ) as $child ) {
				$child_slug = (string) ( $child[2] ?? '' );
				$child_cap  = (string) ( $child[1] ?? 'read' );

				if ( '' === $child_slug || ! current_user_can( $child_cap ) ) {
					continue;
				}

				$children[] = array(
					'label' => $this->clean_label( (string) ( $child[0] ?? '' ) ),
					'url'   => $this->url_for( $child_slug, $slug ),
					'slug'  => $child_slug,
				);
			}

			$items[] = array(
				'label'    => $this->clean_label( (string) $entry[0] ),
				'url'      => $this->url_for( $slug, null ),
				'slug'     => $slug,
				'icon'     => $this->icon_for( (string) ( $entry[6] ?? '' ), $slug ),
				'children' => $children,
			);
		}

		return $items;
	}

	/**
	 * Menu titles carry update-count bubbles as markup. They mean something, so
	 * the count is extracted rather than discarded.
	 */
	private function clean_label( string $label ): string {
		$count = 0;

		if ( preg_match( '/<span[^>]*>\s*(\d+)/', $label, $matches ) ) {
			$count = (int) $matches[1];
		}

		$clean = trim( wp_strip_all_tags( preg_replace( '/<span[^>]*>.*?<\/span>/s', '', $label ) ?? $label ) );

		return $count > 0 ? $clean . '\u{2009}·\u{2009}' . $count : $clean;
	}

	/**
	 * Core's rule: a slug containing `.php` is a file, anything else is a page
	 * under `admin.php`.
	 */
	private function url_for( string $slug, ?string $parent_slug ): string {
		$admin_url = is_network_admin() ? 'network_admin_url' : 'admin_url';

		if ( str_contains( $slug, '.php' ) ) {
			return $admin_url( $slug );
		}

		$base = ( null !== $parent_slug && str_contains( $parent_slug, '.php' ) )
			? $parent_slug
			: 'admin.php';

		$separator = str_contains( $base, '?' ) ? '&' : '?';

		return $admin_url( $base . $separator . 'page=' . rawurlencode( $slug ) );
	}

	/**
	 * Dashicon slug for a menu entry. Core stores either a dashicon name, a URL,
	 * or `none`; only the first is usable, so the rest fall back by slug.
	 */
	private function icon_for( string $icon, string $slug ): string {
		if ( str_starts_with( $icon, 'dashicons-' ) ) {
			return substr( $icon, 10 );
		}

		$known = array(
			'index.php'           => 'dashboard',
			'edit.php'            => 'admin-post',
			'upload.php'          => 'admin-media',
			'edit-comments.php'   => 'admin-comments',
			'themes.php'          => 'admin-appearance',
			'plugins.php'         => 'admin-plugins',
			'users.php'           => 'admin-users',
			'tools.php'           => 'admin-tools',
			'options-general.php' => 'admin-settings',
			'sites.php'           => 'admin-multisite',
		);

		foreach ( $known as $match => $dashicon ) {
			if ( str_starts_with( $slug, $match ) ) {
				return $dashicon;
			}
		}

		return 'admin-generic';
	}

	private function current_slug(): string {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which screen is being rendered, not acting on input.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';

		if ( '' !== $page ) {
			return $page;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';

		if ( '' !== $type && 'post' !== $type ) {
			return (string) $pagenow . '?post_type=' . $type;
		}

		return (string) $pagenow;
	}

	private function screen_title(): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && ! empty( $screen->base ) ) {
			return ucwords( str_replace( array( '-', '_' ), ' ', (string) $screen->base ) );
		}

		return '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function user(): array {
		$user = wp_get_current_user();

		return array(
			'name'   => $user->display_name ?: $user->user_login,
			'email'  => $user->user_email,
			'avatar' => get_avatar_url( $user->ID, array( 'size' => 52 ) ),
		);
	}

	private function active(): bool {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$this->resolved = (bool) $this->settings->get( 'shell_enabled' )
			&& ! $this->bypassed()
			&& ! $this->is_editor();

		return $this->resolved;
	}

	/**
	 * The block editor takes over the whole screen and supplies its own
	 * navigation. Framing it would mean two headers and no gain.
	 */
	private function is_editor(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen instanceof \WP_Screen
			&& ( $screen->is_block_editor() || 'site-editor' === $screen->base );
	}

	private function bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle for one request; it changes nothing.
		$requested = isset( $_GET[ self::BYPASS_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::BYPASS_ARG ] ) ) : '';

		return 'off' === $requested && current_user_can( 'manage_options' );
	}
}
