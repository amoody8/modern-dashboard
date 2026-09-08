<?php
/**
 * Environment gate.
 *
 * Failure *codes* are collected during the check and only turned into
 * translated sentences inside the notice callback. The check itself runs while
 * plugins load, long before `init`, and calling translation functions that
 * early triggers WordPress's "textdomain loaded too early" notice.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Support;

defined( 'ABSPATH' ) || exit;

final class Requirements {

	public const MIN_PHP = '8.0';
	public const MIN_WP  = '6.6';

	private string $file;

	/** @var array<int,array{code:string,args:array<int,string>}> */
	private array $failures = array();

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public function met(): bool {
		$this->failures = array();

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			$this->failures[] = array(
				'code' => 'php',
				'args' => array( self::MIN_PHP, PHP_VERSION ),
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP, '<' ) ) {
			$this->failures[] = array(
				'code' => 'wp',
				'args' => array( self::MIN_WP, get_bloginfo( 'version' ) ),
			);
		}

		if ( ! is_multisite() ) {
			$this->failures[] = array(
				'code' => 'multisite',
				'args' => array(),
			);
		} elseif ( ! $this->is_network_active() ) {
			$this->failures[] = array(
				'code' => 'network',
				'args' => array(),
			);
		}

		return array() === $this->failures;
	}

	/**
	 * A per-site activation cannot see the rest of the network, so it is treated
	 * as a misconfiguration rather than a supported mode.
	 */
	private function is_network_active(): bool {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( plugin_basename( $this->file ) );
	}

	public function render_notice(): void {
		if ( array() === $this->failures ) {
			return;
		}

		$failures = $this->failures;

		$notice = static function () use ( $failures ): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p><strong>';
			esc_html_e( 'Modern Dashboard is inactive.', 'modern-dashboard' );
			echo '</strong></p><ul style="list-style:disc;margin-left:20px">';

			foreach ( $failures as $failure ) {
				printf( '<li>%s</li>', esc_html( self::message( $failure['code'], $failure['args'] ) ) );
			}

			echo '</ul></div>';
		};

		add_action( 'admin_notices', $notice );
		add_action( 'network_admin_notices', $notice );
	}

	/**
	 * @param string            $code Failure code.
	 * @param array<int,string> $args Substitution values.
	 */
	private static function message( string $code, array $args ): string {
		switch ( $code ) {
			case 'php':
				return sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'PHP %1$s or newer is required. This server runs PHP %2$s.', 'modern-dashboard' ),
					$args[0],
					$args[1]
				);

			case 'wp':
				return sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version */
					__( 'WordPress %1$s or newer is required. This site runs WordPress %2$s.', 'modern-dashboard' ),
					$args[0],
					$args[1]
				);

			case 'multisite':
				return __( 'Modern Dashboard is built for Multisite networks and needs a Multisite install to run.', 'modern-dashboard' );

			case 'network':
				return __( 'Modern Dashboard must be network-activated so it can read every site in the network.', 'modern-dashboard' );
		}

		return '';
	}
}
