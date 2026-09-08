<?php
/**
 * Active plugins/theme and the pending updates that actually affect this site.
 *
 * In Multisite the plugin and theme *files* are shared across the network, so
 * the update transients are network-wide. What varies per site is which of those
 * plugins and themes are switched on. This collector therefore reports the
 * intersection: updates that matter to this particular site.
 *
 * It never triggers an update check — that would mean an outbound request to
 * wordpress.org per site. It reads whatever WordPress has already cached.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data\Collectors;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class UpdatesCollector implements Collector {

	public function key(): string {
		return 'updates';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function collect( \WP_Site $site, Settings $settings ): array {
		$active = $this->active_plugins();
		$theme  = wp_get_theme();

		$plugin_updates = $this->pending_plugin_updates();
		$theme_updates  = $this->pending_theme_updates();

		$outdated = array_values( array_intersect( $active, array_keys( $plugin_updates ) ) );

		return array(
			'active_plugins'   => count( $active ),
			'plugin_updates'   => count( $outdated ),
			'outdated_plugins' => array_map(
				static fn( string $file ): array => array(
					'file'    => $file,
					'name'    => $plugin_updates[ $file ]['name'],
					'current' => $plugin_updates[ $file ]['current'],
					'new'     => $plugin_updates[ $file ]['new'],
				),
				$outdated
			),
			'theme'            => array(
				'name'             => $theme->get( 'Name' ) ?: $theme->get_stylesheet(),
				'stylesheet'       => $theme->get_stylesheet(),
				'version'          => (string) $theme->get( 'Version' ),
				'update_available' => isset( $theme_updates[ $theme->get_stylesheet() ] ),
				'update_version'   => $theme_updates[ $theme->get_stylesheet() ] ?? null,
			),
			'theme_updates'    => isset( $theme_updates[ $theme->get_stylesheet() ] ) ? 1 : 0,
		);
	}

	/**
	 * Plugins running on this site: its own active list plus anything the
	 * network has activated everywhere.
	 *
	 * @return string[] Plugin basenames.
	 */
	private function active_plugins(): array {
		$site_active    = (array) get_option( 'active_plugins', array() );
		$network_active = array_keys( (array) get_network_option( null, 'active_sitewide_plugins', array() ) );

		return array_values( array_unique( array_map( 'strval', array_merge( $site_active, $network_active ) ) ) );
	}

	/**
	 * @return array<string,array{name:string,current:string,new:string}>
	 */
	private function pending_plugin_updates(): array {
		$transient = get_site_transient( 'update_plugins' );
		$response  = is_object( $transient ) && isset( $transient->response ) ? (array) $transient->response : array();
		$updates   = array();

		foreach ( $response as $file => $data ) {
			$updates[ (string) $file ] = array(
				'name'    => (string) ( $data->slug ?? $file ),
				'current' => (string) ( $transient->checked[ $file ] ?? '' ),
				'new'     => (string) ( $data->new_version ?? '' ),
			);
		}

		return $updates;
	}

	/**
	 * @return array<string,string> Stylesheet => new version.
	 */
	private function pending_theme_updates(): array {
		$transient = get_site_transient( 'update_themes' );
		$response  = is_object( $transient ) && isset( $transient->response ) ? (array) $transient->response : array();
		$updates   = array();

		foreach ( $response as $stylesheet => $data ) {
			$updates[ (string) $stylesheet ] = (string) ( $data['new_version'] ?? '' );
		}

		return $updates;
	}
}
