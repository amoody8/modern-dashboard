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

	private const PALETTE_HANDLE = 'modern-dashboard-palette';

	private Plugin $plugin;
	private PaletteGate $gate;

	public function __construct( Plugin $plugin, PaletteGate $gate ) {
		$this->plugin = $plugin;
		$this->gate   = $gate;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_app' ) );

		// Later than the app so that on the plugin's own screens, where both
		// load, the emitted order stays stable.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_palette' ), 20 );
	}

	public function enqueue_app( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, NetworkAdminPage::hooks(), true ) ) {
			return;
		}

		$meta = $this->asset_meta( 'index' );

		if ( null === $meta ) {
			add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'render_missing_build_notice' ) );

			return;
		}

		$this->enqueue_entry( self::HANDLE, 'index', $meta );

		wp_add_inline_script(
			self::HANDLE,
			'window.modernDashboard = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * The palette loads on every admin screen, so this runs on requests that
	 * have nothing to do with the dashboard. Everything it does is either
	 * already computed for other reasons or a constant.
	 */
	public function enqueue_palette(): void {
		if ( ! $this->gate->should_load() ) {
			return;
		}

		$meta = $this->asset_meta( 'palette' );

		// A missing palette build degrades to no palette rather than a notice:
		// this runs on every screen of every site, and nagging there would be
		// worse than the thing it reports.
		if ( null === $meta ) {
			return;
		}

		$this->enqueue_entry( self::PALETTE_HANDLE, 'palette', $meta );

		wp_add_inline_script(
			self::PALETTE_HANDLE,
			'window.modernDashboardPalette = ' . wp_json_encode( $this->palette_boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * Dependencies and version for one build entry.
	 *
	 * @return array{dependencies:string[],version:string}|null Null when the entry is missing.
	 */
	private function asset_meta( string $entry ): ?array {
		$build = $this->plugin->dir() . 'build/';

		if ( ! is_readable( $build . $entry . '.js' ) ) {
			return null;
		}

		$asset = $build . $entry . '.asset.php';

		if ( ! is_readable( $asset ) ) {
			return array(
				'dependencies' => array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
				'version'      => $this->plugin->version(),
			);
		}

		/** @var array{dependencies:string[],version:string} $meta */
		$meta = (array) require $asset;

		return $meta;
	}

	/**
	 * @param array{dependencies:string[],version:string} $meta Build metadata.
	 */
	private function enqueue_entry( string $handle, string $entry, array $meta ): void {
		$build = $this->plugin->dir() . 'build/';

		wp_enqueue_script(
			$handle,
			$this->plugin->url() . 'build/' . $entry . '.js',
			$meta['dependencies'],
			(string) $meta['version'],
			true
		);

		if ( is_readable( $build . $entry . '.css' ) ) {
			wp_enqueue_style(
				$handle,
				$this->plugin->url() . 'build/' . $entry . '.css',
				array(),
				(string) $meta['version']
			);

			// The build emits an -rtl.css alongside it; let WordPress swap in
			// the right one for right-to-left locales.
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}

		wp_set_script_translations( $handle, 'modern-dashboard', $this->plugin->dir() . 'languages' );
	}

	/**
	 * Deliberately smaller than the app payload: no settings read (the gate has
	 * already made the one decision that needed it), no links (destinations come
	 * from the commands endpoint), and no capability flags (the endpoints
	 * authorize; shipping flags would only invite the client to guess).
	 *
	 * @return array<string,mixed>
	 */
	private function palette_boot_data(): array {
		return array(
			'root'      => esc_url_raw( rest_url( Routes::NAMESPACE ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'blogId'    => get_current_blog_id(),
			'isNetwork' => is_network_admin(),
			'bypassArg' => PaletteGate::BYPASS_ARG,
			'version'   => $this->plugin->version(),
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
