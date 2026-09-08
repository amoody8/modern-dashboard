<?php
/**
 * Script and style loading for the dashboard app.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Admin;

use ModernDashboard\Builder\BlockRegistry;
use ModernDashboard\Plugin;
use ModernDashboard\Rest\Routes;
use ModernDashboard\Settings\Settings;
use ModernDashboard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Assets {

	private const HANDLE = 'modern-dashboard';

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, NetworkAdminPage::hooks(), true ) ) {
			return;
		}

		$build = $this->plugin->dir() . 'build/';
		$asset = $build . 'index.asset.php';

		if ( ! is_readable( $build . 'index.js' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'render_missing_build_notice' ) );

			return;
		}

		$meta = is_readable( $asset )
			? (array) require $asset
			: array(
				'dependencies' => array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
				'version'      => $this->plugin->version(),
			);

		wp_enqueue_script(
			self::HANDLE,
			$this->plugin->url() . 'build/index.js',
			$meta['dependencies'],
			(string) $meta['version'],
			true
		);

		if ( is_readable( $build . 'index.css' ) ) {
			wp_enqueue_style(
				self::HANDLE,
				$this->plugin->url() . 'build/index.css',
				array(),
				(string) $meta['version']
			);

			// The build emits index-rtl.css alongside it; let WordPress swap in
			// the right one for right-to-left locales.
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}

		wp_set_script_translations( self::HANDLE, 'modern-dashboard', $this->plugin->dir() . 'languages' );

		wp_add_inline_script(
			self::HANDLE,
			'window.modernDashboard = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function boot_data(): array {
		$settings = $this->plugin->settings();

		return array(
			'root'      => esc_url_raw( rest_url( Routes::NAMESPACE ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'context'   => is_network_admin() ? 'network' : 'site',
			'blogId'    => get_current_blog_id(),
			'canManage' => Capabilities::can_manage(),
			'settings'  => $settings->all(),
			'columns'   => BlockRegistry::COLUMNS,
			'intervals' => Settings::INTERVALS,
			'links'     => array(
				'sites'   => network_admin_url( 'sites.php' ),
				'updates' => network_admin_url( 'update-core.php' ),
				'newSite' => network_admin_url( 'site-new.php' ),
			),
			'version'   => $this->plugin->version(),
		);
	}

	public function render_missing_build_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Modern Dashboard: the front-end build is missing. Run "npm install && npm run build" in the plugin directory.', 'modern-dashboard' );
		echo '</p></div>';
	}
}
