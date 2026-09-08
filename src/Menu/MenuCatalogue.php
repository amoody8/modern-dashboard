<?php
/**
 * What the admin menu actually contains, network-wide.
 *
 * WordPress only knows a site's menu while that site is rendering an admin
 * page: `$menu` is built by whichever plugins are active there, so it cannot be
 * read for another site with `switch_to_blog()`. The catalogue is therefore
 * assembled by observation — every admin page load contributes what that site
 * has — rather than by enumeration.
 *
 * It is keyed by slug rather than by site, so it grows with the number of
 * distinct menu items (tens) instead of the number of sites (possibly
 * thousands). Merging is additive and converges: a lost concurrent write is
 * repaired by the next admin page load.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Menu;

defined( 'ABSPATH' ) || exit;

final class MenuCatalogue {

	public const OPTION = 'modern_dashboard_menu_catalogue';

	/** Items unseen for this long are pruned, so uninstalled plugins fade out. */
	private const STALE_AFTER = 30 * DAY_IN_SECONDS;

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	public function register(): void {
		// Late, so every plugin has registered its pages by now.
		add_action( 'admin_menu', array( $this, 'capture' ), 100000 );
	}

	/**
	 * Record the current site's menu.
	 */
	public function capture(): void {
		if ( is_network_admin() || is_user_admin() ) {
			return;
		}

		$observed = $this->read_globals();

		if ( array() === $observed ) {
			return;
		}

		$stored = $this->all();
		$merged = $this->merge( $stored['items'], $observed );

		// Only write when something actually changed: this runs on every admin
		// page load, and an unconditional write would be a query per request.
		if ( $this->signature( $merged ) === $this->signature( $stored['items'] ) ) {
			return;
		}

		update_network_option(
			null,
			self::OPTION,
			array(
				'items'   => $merged,
				'updated' => time(),
			)
		);

		$this->cache = null;
	}

	/**
	 * @return array{items:array<string,mixed>,updated:int}
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_network_option( null, self::OPTION, array() );

		$this->cache = array(
			'items'   => is_array( $stored['items'] ?? null ) ? $stored['items'] : array(),
			'updated' => (int) ( $stored['updated'] ?? 0 ),
		);

		return $this->cache;
	}

	/**
	 * The catalogue in the shape the editor consumes: a flat, ordered list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function to_rest(): array {
		$items = $this->all()['items'];

		uasort(
			$items,
			static fn( array $a, array $b ): int => ( $a['position'] ?? 100 ) <=> ( $b['position'] ?? 100 )
		);

		$out = array();

		foreach ( $items as $slug => $item ) {
			$children = $item['children'] ?? array();

			uasort(
				$children,
				static fn( array $a, array $b ): int => ( $a['position'] ?? 100 ) <=> ( $b['position'] ?? 100 )
			);

			$out[] = array(
				'slug'       => (string) $slug,
				'label'      => (string) ( $item['label'] ?? $slug ),
				'capability' => (string) ( $item['capability'] ?? '' ),
				'seen'       => (int) ( $item['seen'] ?? 0 ),
				'children'   => array_map(
					static fn( string $child_slug, array $child ): array => array(
						'slug'       => $child_slug,
						'label'      => (string) ( $child['label'] ?? $child_slug ),
						'capability' => (string) ( $child['capability'] ?? '' ),
					),
					array_keys( $children ),
					array_values( $children )
				),
			);
		}

		return $out;
	}

	/**
	 * Forget everything and start observing again.
	 */
	public function reset(): void {
		delete_network_option( null, self::OPTION );

		$this->cache = null;
	}

	/**
	 * Normalise the admin menu globals into plain data.
	 *
	 * @return array<string,mixed>
	 */
	private function read_globals(): array {
		global $menu, $submenu;

		if ( ! is_array( $menu ) ) {
			return array();
		}

		$items = array();

		foreach ( $menu as $position => $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry[2] ) ) {
				continue;
			}

			$slug = (string) $entry[2];

			// Separators are decorative and carry no slug worth editing.
			if ( '' === $slug || str_starts_with( $slug, 'separator' ) ) {
				continue;
			}

			$children = array();

			foreach ( (array) ( $submenu[ $slug ] ?? array() ) as $child_position => $child ) {
				if ( ! is_array( $child ) || ! isset( $child[2] ) ) {
					continue;
				}

				$children[ (string) $child[2] ] = array(
					'label'      => $this->clean_label( (string) ( $child[0] ?? '' ) ),
					'capability' => (string) ( $child[1] ?? '' ),
					'position'   => (int) $child_position,
				);
			}

			$items[ $slug ] = array(
				'label'      => $this->clean_label( (string) ( $entry[0] ?? '' ) ),
				'capability' => (string) ( $entry[1] ?? '' ),
				'position'   => (int) $position,
				'children'   => $children,
			);
		}

		return $items;
	}

	/**
	 * Menu titles carry markup — update-count bubbles, comment counters — that
	 * would otherwise be stored and displayed as literal text in the editor.
	 */
	private function clean_label( string $label ): string {
		$label = preg_replace( '#<span[^>]*>.*?</span>#is', '', $label ) ?? $label;

		return trim( wp_strip_all_tags( $label ) );
	}

	/**
	 * @param array<string,mixed> $stored   What the catalogue already holds.
	 * @param array<string,mixed> $observed What this site just reported.
	 *
	 * @return array<string,mixed>
	 */
	private function merge( array $stored, array $observed ): array {
		$now = time();

		foreach ( $observed as $slug => $item ) {
			$existing = $stored[ $slug ] ?? array();

			$stored[ $slug ] = array(
				'label'      => $item['label'],
				'capability' => $item['capability'],
				'position'   => $item['position'],
				'seen'       => $now,
				'children'   => array_merge(
					(array) ( $existing['children'] ?? array() ),
					$item['children']
				),
			);
		}

		return array_filter(
			$stored,
			static fn( array $item ): bool => ( $now - (int) ( $item['seen'] ?? 0 ) ) < self::STALE_AFTER
		);
	}

	/**
	 * A cheap fingerprint used to decide whether a write is needed at all.
	 *
	 * Deliberately excludes `seen`, which changes on every observation and would
	 * make every page load look like a change.
	 *
	 * @param array<string,mixed> $items Catalogue items.
	 */
	private function signature( array $items ): string {
		$shape = array();

		foreach ( $items as $slug => $item ) {
			$shape[ $slug ] = array(
				$item['label'] ?? '',
				$item['capability'] ?? '',
				$item['position'] ?? 0,
				array_keys( (array) ( $item['children'] ?? array() ) ),
			);
		}

		ksort( $shape );

		return md5( (string) wp_json_encode( $shape ) );
	}
}
