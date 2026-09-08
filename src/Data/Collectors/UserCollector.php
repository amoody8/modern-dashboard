<?php
/**
 * Per-site user counts by role.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data\Collectors;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class UserCollector implements Collector {

	public function key(): string {
		return 'users';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function collect( \WP_Site $site, Settings $settings ): array {
		$counts = count_users();
		$roles  = array();

		foreach ( (array) ( $counts['avail_roles'] ?? array() ) as $role => $count ) {
			if ( $count > 0 ) {
				$roles[ (string) $role ] = (int) $count;
			}
		}

		arsort( $roles, SORT_NUMERIC );

		return array(
			'total'          => (int) ( $counts['total_users'] ?? 0 ),
			'roles'          => $roles,
			'administrators' => (int) ( $roles['administrator'] ?? 0 ),
		);
	}
}
