<?php
/**
 * PSR-4 style autoloader.
 *
 * Ships with the plugin so a Composer `vendor/` directory is never required at
 * runtime. Composer is used for development tooling only.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Register an autoloader mapping $prefix to $base_dir.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		$prefix   = trim( $prefix, '\\' ) . '\\';
		$base_dir = rtrim( $base_dir, '/\\' ) . '/';

		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $base_dir ): void {
				if ( ! str_starts_with( $class_name, $prefix ) ) {
					return;
				}

				$relative = substr( $class_name, strlen( $prefix ) );
				$path     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		);
	}
}
