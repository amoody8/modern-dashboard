<?php
/**
 * Restyles wp-admin itself.
 *
 * Everything else this plugin draws lives on its own screens. This puts a
 * stylesheet on *every* admin page of every site, restyling WordPress's own
 * chrome — the sidebar, the toolbar, list tables, forms, buttons — so the
 * network's admin reads as one product rather than a modern dashboard bolted
 * into a stock install.
 *
 * It restyles and never restructures: no DOM is moved and nothing meaningful is
 * hidden, so a WordPress release that changes markup degrades to "looks stock"
 * rather than "broken". The class is added to `<body>` rather than the
 * stylesheet being unconditionally scoped to `.wp-admin`, so a single query
 * argument can take the whole thing off.
 *
 * `?mdash-skin=off` renders the untouched admin for anyone who can
 * `manage_options`, matching the recovery hatch the menu and branding
 * subsystems already offer.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Theme;

use ModernDashboard\Plugin;
use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminSkin {

	/** Query argument that disables the skin for one request. */
	public const BYPASS_ARG = 'mdash-skin';

	private const HANDLE = 'modern-dashboard-admin';

	private Plugin $plugin;
	private Settings $settings;

	private ?bool $resolved = null;

	public function __construct( Plugin $plugin, Settings $settings ) {
		$this->plugin   = $plugin;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_notices', array( $this, 'render_bypass_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_bypass_notice' ) );
	}

	public function enqueue(): void {
		if ( ! $this->active() ) {
			return;
		}

		$build = $this->plugin->dir() . 'build/';

		if ( ! is_readable( $build . 'admin.css' ) ) {
			return;
		}

		$version = $this->plugin->version();
		$asset   = $build . 'admin.asset.php';

		if ( is_readable( $asset ) ) {
			$meta    = (array) require $asset;
			$version = (string) ( $meta['version'] ?? $version );
		}

		wp_enqueue_style(
			self::HANDLE,
			$this->plugin->url() . 'build/admin.css',
			array(),
			$version
		);

		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
	}

	/**
	 * The stylesheet does nothing until this class lands on the body, which is
	 * what makes the bypass a single toggle rather than a set of overrides.
	 *
	 * @param string $classes Existing body classes.
	 */
	public function body_class( string $classes ): string {
		if ( ! $this->active() ) {
			return $classes;
		}

		return trim( $classes . ' mds-skin' );
	}

	public function render_bypass_notice(): void {
		if ( ! $this->bypassed() ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e(
			'Showing the unstyled WordPress admin. Reload without the mdash-skin query argument to bring the theme back.',
			'modern-dashboard'
		);
		echo '</p></div>';
	}

	private function active(): bool {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$this->resolved = (bool) $this->settings->get( 'skin_enabled' ) && ! $this->bypassed();

		return $this->resolved;
	}

	private function bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle for one request; it changes nothing.
		$requested = isset( $_GET[ self::BYPASS_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::BYPASS_ARG ] ) ) : '';

		return 'off' === $requested && current_user_can( 'manage_options' );
	}
}
