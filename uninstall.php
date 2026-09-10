<?php
/**
 * Uninstall routine.
 *
 * Removes every trace of the plugin: settings, the collection index, per-site
 * metrics (in both the site-meta and network-option storage modes), transients
 * and the scheduled event.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Autoloader.php';

\ModernDashboard\Autoloader::register( 'ModernDashboard', __DIR__ . '/src' );

use ModernDashboard\Builder\TemplateRepository;
use ModernDashboard\Cron\Scheduler;
use ModernDashboard\Data\NetworkAggregator;
use ModernDashboard\Data\Store;
use ModernDashboard\Menu\MenuCatalogue;
use ModernDashboard\Menu\MenuRules;
use ModernDashboard\Audit\Schema as AuditSchema;
use ModernDashboard\Palette\SearchIndex;
use ModernDashboard\Roles\CapabilityCatalogue;
use ModernDashboard\Roles\RoleRules;
use ModernDashboard\Settings\Settings;
use ModernDashboard\Theme\ThemeRepository;

wp_clear_scheduled_hook( Scheduler::HOOK );

if ( ! is_multisite() ) {
	// Nothing else can exist: the plugin refuses to run outside Multisite.
	return;
}

$modern_dashboard_site_ids = get_sites(
	array(
		'fields'   => 'ids',
		'number'   => 0,
		'archived' => null,
		'spam'     => null,
		'deleted'  => null,
	)
);

// Site-meta storage: one call clears the key across every site.
if ( function_exists( 'is_site_meta_supported' ) && is_site_meta_supported() ) {
	delete_metadata( 'blog', 0, Store::META_KEY, '', true );
	delete_metadata( 'blog', 0, SearchIndex::META_KEY, '', true );
}

// Network-option fallback storage: one option per site.
foreach ( (array) $modern_dashboard_site_ids as $modern_dashboard_site_id ) {
	delete_network_option( null, 'modern_dashboard_metrics_' . (int) $modern_dashboard_site_id );
	delete_network_option( null, 'modern_dashboard_search_' . (int) $modern_dashboard_site_id );

	// Branding overrides are blog options, so they live on each site.
	switch_to_blog( (int) $modern_dashboard_site_id );
	delete_option( ThemeRepository::SITE_OPTION );
	restore_current_blog();
}

delete_network_option( null, Settings::OPTION );
delete_network_option( null, TemplateRepository::OPTION );
delete_network_option( null, MenuCatalogue::OPTION );
delete_network_option( null, MenuRules::OPTION );
delete_network_option( null, ThemeRepository::NETWORK_OPTION );
delete_network_option( null, Store::INDEX_OPTION );
delete_network_option( null, SearchIndex::INDEX_OPTION );
delete_network_option( null, RoleRules::OPTION );
delete_network_option( null, CapabilityCatalogue::OPTION );

// The audit log is a table, not an option. Dropped only here — deactivating a
// plugin must never destroy an audit trail.
AuditSchema::drop();
delete_network_option( null, Scheduler::LAST_RUN );

delete_site_transient( NetworkAggregator::TRANSIENT );
delete_site_transient( Scheduler::LOCK );
