<?php
/**
 * What the audit log listens to.
 *
 * Scope is deliberately narrow: administrative changes, not activity. Every
 * page view, every autosave, every option touched by a plugin on init would
 * make the log unreadable and enormous, and none of it answers the question a
 * network administrator actually has — *who changed this, and when*.
 *
 * So the rule for adding an event here: it must be something a person did
 * deliberately, that another person might later need to account for.
 *
 * Third parties extend this through `modern_dashboard_audit_events`, which
 * receives the recorder and can hook anything it likes.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Audit;

defined( 'ABSPATH' ) || exit;

final class Events {

	/** Post types never worth logging: machinery, not content. */
	private const IGNORED_TYPES = array(
		'revision',
		'nav_menu_item',
		'customize_changeset',
		'custom_css',
		'oembed_cache',
		'user_request',
		'wp_global_styles',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
		'scheduled-action',
	);

	private Recorder $recorder;

	public function __construct( Recorder $recorder ) {
		$this->recorder = $recorder;
	}

	public function register(): void {
		// Content.
		add_action( 'transition_post_status', array( $this, 'on_post_status' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_post_deleted' ), 10, 2 );

		// Plugins and themes. These are the events that most often explain
		// "why did the site change overnight".
		add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_deactivated' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'on_theme_switched' ), 10, 3 );

		// People.
		add_action( 'user_register', array( $this, 'on_user_registered' ) );
		add_action( 'delete_user', array( $this, 'on_user_deleted' ), 10, 2 );
		add_action( 'set_user_role', array( $this, 'on_role_changed' ), 10, 3 );
		add_action( 'wp_login', array( $this, 'on_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ) );

		// The network itself.
		add_action( 'wp_initialize_site', array( $this, 'on_site_created' ), 20 );
		add_action( 'wp_delete_site', array( $this, 'on_site_deleted' ) );

		/**
		 * Fires so other code can record its own audit events.
		 *
		 * @param Recorder $recorder Use `record( $action, $entry )`.
		 */
		do_action( 'modern_dashboard_audit_events', $this->recorder );
	}

	/**
	 * @param string   $new_status New status.
	 * @param string   $old_status Previous status.
	 * @param \WP_Post $post       The post.
	 */
	public function on_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $new_status === $old_status || in_array( $post->post_type, self::IGNORED_TYPES, true ) ) {
			return;
		}

		// `auto-draft` churns on every "add new" screen and means nothing, and
		// auto-draft → draft is WordPress creating the row, not a person
		// drafting something.
		if ( 'auto-draft' === $new_status ) {
			return;
		}

		if ( 'auto-draft' === $old_status && 'draft' === $new_status ) {
			return;
		}

		$action = 'publish' === $new_status && 'publish' !== $old_status
			? 'post.published'
			: ( 'trash' === $new_status ? 'post.trashed' : 'post.updated' );

		$this->recorder->record(
			$action,
			array(
				'object_type' => $post->post_type,
				'object_id'   => (int) $post->ID,
				'object_name' => $post->post_title,
				'context'     => array(
					'from' => $old_status,
					'to'   => $new_status,
				),
			)
		);
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    The post.
	 */
	public function on_post_deleted( int $post_id, $post = null ): void {
		if ( ! $post instanceof \WP_Post || in_array( $post->post_type, self::IGNORED_TYPES, true ) ) {
			return;
		}

		$this->recorder->record(
			'post.deleted',
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post_id,
				'object_name' => $post->post_title,
			)
		);
	}

	/**
	 * @param string $plugin       Plugin file.
	 * @param bool   $network_wide Whether it was network-activated.
	 */
	public function on_plugin_activated( string $plugin, bool $network_wide = false ): void {
		$this->recorder->record(
			'plugin.activated',
			array(
				'object_type' => 'plugin',
				'object_name' => $this->plugin_name( $plugin ),
				'context'     => array(
					'file'    => $plugin,
					'network' => $network_wide,
				),
			)
		);
	}

	/**
	 * @param string $plugin       Plugin file.
	 * @param bool   $network_wide Whether it was network-deactivated.
	 */
	public function on_plugin_deactivated( string $plugin, bool $network_wide = false ): void {
		$this->recorder->record(
			'plugin.deactivated',
			array(
				'object_type' => 'plugin',
				'object_name' => $this->plugin_name( $plugin ),
				'context'     => array(
					'file'    => $plugin,
					'network' => $network_wide,
				),
			)
		);
	}

	/**
	 * @param string    $new_name  New theme name.
	 * @param \WP_Theme $new_theme New theme.
	 * @param \WP_Theme $old_theme Previous theme.
	 */
	public function on_theme_switched( string $new_name, $new_theme = null, $old_theme = null ): void {
		$this->recorder->record(
			'theme.switched',
			array(
				'object_type' => 'theme',
				'object_name' => $new_name,
				'context'     => array(
					'from' => $old_theme instanceof \WP_Theme ? $old_theme->get( 'Name' ) : '',
				),
			)
		);
	}

	/**
	 * @param int $user_id New user.
	 */
	public function on_user_registered( int $user_id ): void {
		$user = get_userdata( $user_id );

		$this->recorder->record(
			'user.registered',
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'object_name' => $user ? $user->user_login : (string) $user_id,
			)
		);
	}

	/**
	 * @param int      $user_id  Deleted user.
	 * @param int|null $reassign User content was reassigned to.
	 */
	public function on_user_deleted( int $user_id, $reassign = null ): void {
		$user = get_userdata( $user_id );

		$this->recorder->record(
			'user.deleted',
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'object_name' => $user ? $user->user_login : (string) $user_id,
				'context'     => array( 'reassigned_to' => $reassign ? (int) $reassign : null ),
			)
		);
	}

	/**
	 * @param int      $user_id   User whose role changed.
	 * @param string   $role      New role.
	 * @param string[] $old_roles Previous roles.
	 */
	public function on_role_changed( int $user_id, string $role, $old_roles = array() ): void {
		$user = get_userdata( $user_id );

		// Registration sets a role too; that is already covered by
		// user.registered and logging both would double every signup.
		if ( empty( $old_roles ) ) {
			return;
		}

		$this->recorder->record(
			'user.role_changed',
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'object_name' => $user ? $user->user_login : (string) $user_id,
				'context'     => array(
					'from' => implode( ', ', (array) $old_roles ),
					'to'   => $role,
				),
			)
		);
	}

	/**
	 * @param string        $login Username.
	 * @param \WP_User|null $user  The user.
	 */
	public function on_login( string $login, $user = null ): void {
		$this->recorder->record(
			'user.login',
			array(
				'object_type' => 'user',
				'object_id'   => $user instanceof \WP_User ? (int) $user->ID : 0,
				'object_name' => $login,
			)
		);
	}

	/**
	 * Failed logins are the one event recorded for someone who is not signed
	 * in, which is exactly why they are worth having.
	 *
	 * @param string $login Attempted username.
	 */
	public function on_login_failed( string $login ): void {
		$this->recorder->record(
			'user.login_failed',
			array(
				'object_type' => 'user',
				'object_name' => $login,
			)
		);
	}

	/**
	 * @param \WP_Site $site New site.
	 */
	public function on_site_created( $site ): void {
		if ( ! $site instanceof \WP_Site ) {
			return;
		}

		$this->recorder->record(
			'site.created',
			array(
				'object_type' => 'site',
				'object_id'   => (int) $site->blog_id,
				'object_name' => $site->blogname ?: $site->domain,
			),
			(int) $site->blog_id
		);
	}

	/**
	 * @param \WP_Site $site Site being deleted.
	 */
	public function on_site_deleted( $site ): void {
		if ( ! $site instanceof \WP_Site ) {
			return;
		}

		$this->recorder->record(
			'site.deleted',
			array(
				'object_type' => 'site',
				'object_id'   => (int) $site->blog_id,
				'object_name' => $site->blogname ?: $site->domain,
			),
			(int) $site->blog_id
		);
	}

	private function plugin_name( string $file ): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$path = WP_PLUGIN_DIR . '/' . $file;

		if ( ! is_readable( $path ) ) {
			return $file;
		}

		$data = get_plugin_data( $path, false, false );

		return $data['Name'] ?: $file;
	}
}
