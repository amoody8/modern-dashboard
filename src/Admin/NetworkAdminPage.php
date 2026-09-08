<?php
/**
 * Admin screens.
 *
 * The network dashboard is the primary screen. A read-only per-site screen is
 * registered on every site only when the network admin has enabled it, keeping
 * the network the single source of truth for who sees what.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Admin;

use ModernDashboard\Settings\Settings;
use ModernDashboard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class NetworkAdminPage {

	public const SLUG = 'modern-dashboard';

	/** @var string[] Hook suffixes for screens that should load the app. */
	private static array $hooks = array();

	public function register(): void {
		add_action( 'network_admin_menu', array( $this, 'register_network_page' ) );
		add_action( 'admin_menu', array( $this, 'register_site_page' ) );
	}

	/**
	 * @return string[]
	 */
	public static function hooks(): array {
		return self::$hooks;
	}

	public function register_network_page(): void {
		$hook = add_menu_page(
			__( 'Network Dashboard', 'modern-dashboard' ),
			__( 'Network Dashboard', 'modern-dashboard' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-screenoptions',
			2
		);

		if ( $hook ) {
			self::$hooks[] = $hook;
		}
	}

	public function register_site_page(): void {
		if ( ! ( new Settings() )->get( 'allow_site_admins' ) ) {
			return;
		}

		$hook = add_submenu_page(
			'index.php',
			__( 'Site Metrics', 'modern-dashboard' ),
			__( 'Site Metrics', 'modern-dashboard' ),
			Capabilities::VIEW,
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			self::$hooks[] = $hook;
		}
	}

	public function render(): void {
		printf(
			'<div class="wrap"><div id="modern-dashboard-root" class="modern-dashboard"><p class="md-booting">%s</p></div></div>',
			esc_html__( 'Loading the dashboard…', 'modern-dashboard' )
		);
	}
}
