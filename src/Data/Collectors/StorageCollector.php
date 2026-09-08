<?php
/**
 * Uploads directory size.
 *
 * By far the most expensive thing collected, so it is opt-outable, bounded by a
 * per-site execution budget, and only ever runs from cron or an explicit
 * refresh — never on a dashboard page load. When the budget runs out
 * `recurse_dirsize()` returns false and the result is reported as partial
 * rather than silently wrong.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data\Collectors;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class StorageCollector implements Collector {

	public function key(): string {
		return 'storage';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function collect( \WP_Site $site, Settings $settings ): array {
		if ( ! $settings->get( 'collect_storage' ) ) {
			return array(
				'bytes'   => null,
				'partial' => false,
				'skipped' => true,
			);
		}

		$uploads = wp_get_upload_dir();
		$basedir = $uploads['basedir'] ?? '';

		if ( '' === $basedir || ! is_dir( $basedir ) ) {
			return array(
				'bytes'   => 0,
				'partial' => false,
				'skipped' => false,
			);
		}

		$size = recurse_dirsize( $basedir, null, (int) $settings->get( 'storage_scan_timeout' ) );

		return array(
			'bytes'   => is_numeric( $size ) ? (int) $size : null,
			'partial' => ! is_numeric( $size ),
			'skipped' => false,
		);
	}
}
