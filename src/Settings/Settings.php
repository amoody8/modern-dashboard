<?php
/**
 * Network-controlled settings.
 *
 * Everything lives in a single network option. Individual sites read these
 * values but cannot write them, which is what makes the network admin the
 * single source of truth for the whole install.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'modern_dashboard_settings';

	public const INTERVALS = array( 'mdash_quarter_hourly', 'hourly', 'twicedaily', 'daily' );

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'refresh_interval'        => 'hourly',
			'batch_size'              => 25,
			'collect_storage'         => true,
			'storage_scan_timeout'    => 10,
			'allow_site_admins'       => false,
			'stale_after'             => 2 * HOUR_IN_SECONDS,
			'inactive_threshold_days' => 90,
			'excluded_sites'          => array(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_network_option( null, self::OPTION, null );

		if ( ! is_array( $stored ) ) {
			$stored = self::defaults();
			update_network_option( null, self::OPTION, $stored );
		}

		$this->cache = array_merge( self::defaults(), $stored );

		return $this->cache;
	}

	public function get( string $key ): mixed {
		return $this->all()[ $key ] ?? null;
	}

	/**
	 * Merge a partial, already-untrusted payload into the stored settings.
	 *
	 * @param array<string,mixed> $input Raw input.
	 *
	 * @return array<string,mixed> The full, sanitized settings after the update.
	 */
	public function update( array $input ): array {
		$merged = $this->sanitize( array_merge( $this->all(), $input ) );

		update_network_option( null, self::OPTION, $merged );
		$this->cache = $merged;

		return $merged;
	}

	/**
	 * @param array<string,mixed> $input Raw input.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = self::defaults();

		$interval = (string) ( $input['refresh_interval'] ?? $defaults['refresh_interval'] );

		return array(
			'refresh_interval'        => in_array( $interval, self::INTERVALS, true ) ? $interval : $defaults['refresh_interval'],
			'batch_size'              => max( 1, min( 200, (int) ( $input['batch_size'] ?? $defaults['batch_size'] ) ) ),
			'collect_storage'         => (bool) ( $input['collect_storage'] ?? $defaults['collect_storage'] ),
			'storage_scan_timeout'    => max( 1, min( 60, (int) ( $input['storage_scan_timeout'] ?? $defaults['storage_scan_timeout'] ) ) ),
			'allow_site_admins'       => (bool) ( $input['allow_site_admins'] ?? $defaults['allow_site_admins'] ),
			'stale_after'             => max( 300, min( DAY_IN_SECONDS, (int) ( $input['stale_after'] ?? $defaults['stale_after'] ) ) ),
			'inactive_threshold_days' => max( 1, min( 3650, (int) ( $input['inactive_threshold_days'] ?? $defaults['inactive_threshold_days'] ) ) ),
			'excluded_sites'          => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['excluded_sites'] ?? array() ) ) ) ) ),
		);
	}

	/**
	 * JSON Schema used by the REST settings endpoint for argument validation.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function rest_schema(): array {
		return array(
			'refresh_interval'        => array(
				'type' => 'string',
				'enum' => self::INTERVALS,
			),
			'batch_size'              => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 200,
			),
			'collect_storage'         => array( 'type' => 'boolean' ),
			'storage_scan_timeout'    => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 60,
			),
			'allow_site_admins'       => array( 'type' => 'boolean' ),
			'stale_after'             => array(
				'type'    => 'integer',
				'minimum' => 300,
				'maximum' => DAY_IN_SECONDS,
			),
			'inactive_threshold_days' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 3650,
			),
			'excluded_sites'          => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		);
	}
}
