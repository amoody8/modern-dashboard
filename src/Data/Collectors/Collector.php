<?php
/**
 * Collector contract.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data\Collectors;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

interface Collector {

	/**
	 * Key this collector's payload is stored under.
	 */
	public function key(): string;

	/**
	 * Gather data for the current site.
	 *
	 * Always called inside a `switch_to_blog()` context, so it may use the
	 * ordinary global-scoped WordPress APIs.
	 *
	 * @param \WP_Site $site     The site being collected.
	 * @param Settings $settings Network settings.
	 *
	 * @return array<string,mixed>
	 */
	public function collect( \WP_Site $site, Settings $settings ): array;
}
