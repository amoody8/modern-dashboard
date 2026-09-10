<?php
/**
 * What capabilities exist on this network.
 *
 * The same problem `MenuCatalogue` solves, for the same reason. A plugin's
 * capabilities only exist where that plugin is active, so there is no way to
 * ask "what capabilities does site 47 have?" from outside site 47 — and the
 * core list is only ever part of the answer.
 *
 * So the catalogue is built by observation. Every admin page load contributes
 * what that site has, merged into one network-level record keyed by capability
 * name, which means it grows with the number of distinct capabilities (tens)
 * rather than the number of sites. Writes happen only when the set actually
 * changes, so the steady state is a read.
 *
 * The consequence worth knowing: a freshly installed network shows only the
 * core capabilities until somebody visits a site's admin. The editor says so
 * rather than looking broken.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Roles;

defined( 'ABSPATH' ) || exit;

final class CapabilityCatalogue {

	public const OPTION = 'modern_dashboard_capability_catalogue';

	/** Capabilities unseen for this long are dropped, so uninstalled plugins fade out. */
	private const STALE_AFTER = 30 * DAY_IN_SECONDS;

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	public function register(): void {
		// Late, so every plugin has registered its roles and post types.
		add_action( 'admin_init', array( $this, 'capture' ), 100000 );
	}

	/**
	 * Record what this site has.
	 */
	public function capture(): void {
		if ( is_network_admin() || is_user_admin() ) {
			return;
		}

		$observed = array_unique( array_merge( $this->from_roles(), $this->from_post_types() ) );

		if ( array() === $observed ) {
			return;
		}

		$stored = $this->all();
		$merged = $this->merge( $stored['caps'] ?? array(), $observed );

		// This runs on every admin page load; an unconditional write would be a
		// query per request. The signature deliberately excludes `seen`, which
		// changes constantly and would defeat the guard.
		if ( $this->signature( $merged ) === $this->signature( $stored['caps'] ?? array() ) ) {
			return;
		}

		$this->cache = array(
			'caps'    => $merged,
			'updated' => time(),
		);

		update_network_option( null, self::OPTION, $this->cache );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_network_option( null, self::OPTION, array() );

		$this->cache = is_array( $stored )
			? array(
				'caps'    => (array) ( $stored['caps'] ?? array() ),
				'updated' => (int) ( $stored['updated'] ?? 0 ),
			)
			: array(
				'caps'    => array(),
				'updated' => 0,
			);

		return $this->cache;
	}

	/**
	 * The catalogue in the shape the editor needs: core capabilities grouped
	 * and labelled, then whatever else has been observed.
	 *
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		$observed = $this->all();
		$known    = CoreCapabilities::known();
		$groups   = array();

		foreach ( CoreCapabilities::groups() as $key => $group ) {
			$caps = array();

			foreach ( (array) $group['caps'] as $cap => $label ) {
				$caps[] = array(
					'cap'     => $cap,
					'label'   => $label,
					'sites'   => (int) ( $observed['caps'][ $cap ]['sites'] ?? 0 ),
					'network' => in_array( $cap, CoreCapabilities::network_only(), true ),
					'locked'  => in_array( $cap, CoreCapabilities::protected_caps(), true ),
				);
			}

			$groups[] = array(
				'key'   => $key,
				'label' => $group['label'],
				'note'  => $group['note'] ?? '',
				'caps'  => $caps,
			);
		}

		// Anything observed that the core list does not describe: almost always
		// a plugin's own capability. Shown with its site count, because "seen on
		// 2 of 40 sites" is the context that makes an unfamiliar name readable.
		$extra = array();

		foreach ( $observed['caps'] as $cap => $meta ) {
			if ( in_array( $cap, $known, true ) ) {
				continue;
			}

			$extra[] = array(
				'cap'     => (string) $cap,
				'label'   => (string) $cap,
				'sites'   => (int) ( $meta['sites'] ?? 0 ),
				'network' => false,
				'locked'  => false,
			);
		}

		if ( array() !== $extra ) {
			usort( $extra, static fn( array $a, array $b ): int => strcmp( $a['cap'], $b['cap'] ) );

			$groups[] = array(
				'key'   => 'other',
				'label' => __( 'Added by plugins', 'modern-dashboard' ),
				'note'  => __( 'Observed on at least one site. WordPress does not describe these, so the name is all we have.', 'modern-dashboard' ),
				'caps'  => $extra,
			);
		}

		return array(
			'groups'  => $groups,
			'updated' => $observed['updated'],
			'empty'   => array() === $observed['caps'],
		);
	}

	public function reset(): void {
		$this->cache = null;

		delete_network_option( null, self::OPTION );
	}

	/**
	 * Capabilities present in this site's roles.
	 *
	 * @return string[]
	 */
	private function from_roles(): array {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}

		$caps = array();

		foreach ( wp_roles()->roles as $role ) {
			$caps = array_merge( $caps, array_keys( (array) ( $role['capabilities'] ?? array() ) ) );
		}

		return $caps;
	}

	/**
	 * Capabilities declared by registered post types.
	 *
	 * These can exist in `$wp_post_types` without ever having been written into
	 * a role, so observing roles alone would miss them.
	 *
	 * @return string[]
	 */
	private function from_post_types(): array {
		$caps = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			foreach ( (array) ( $type->cap ?? array() ) as $cap ) {
				if ( is_string( $cap ) && '' !== $cap ) {
					$caps[] = $cap;
				}
			}
		}

		return $caps;
	}

	/**
	 * Additive merge, with a site count and a last-seen stamp per capability.
	 *
	 * @param array<string,mixed> $stored   What the network already knows.
	 * @param string[]            $observed What this site just reported.
	 *
	 * @return array<string,mixed>
	 */
	private function merge( array $stored, array $observed ): array {
		$now     = time();
		$blog_id = get_current_blog_id();

		foreach ( $observed as $cap ) {
			$cap = (string) $cap;

			if ( '' === $cap ) {
				continue;
			}

			$entry = $stored[ $cap ] ?? array(
				'sites' => 0,
				'seen'  => 0,
				'on'    => array(),
			);

			$on = array_values( array_unique( array_merge( (array) ( $entry['on'] ?? array() ), array( $blog_id ) ) ) );

			// Bounded: the count is what the UI shows, and holding every blog ID
			// on a large network would grow this option without adding meaning.
			$stored[ $cap ] = array(
				'sites' => count( $on ),
				'seen'  => $now,
				'on'    => array_slice( $on, 0, 50 ),
			);
		}

		// Drop capabilities nobody has reported for a month.
		return array_filter(
			$stored,
			static fn( $entry ): bool => ( (int) ( $entry['seen'] ?? 0 ) ) > ( $now - self::STALE_AFTER )
		);
	}

	/**
	 * Fingerprint for the write guard.
	 *
	 * Only the capability names and their site counts matter — `seen` changes
	 * on every observation and would make the guard useless.
	 *
	 * @param array<string,mixed> $caps Catalogue entries.
	 */
	private function signature( array $caps ): string {
		$reduced = array();

		foreach ( $caps as $cap => $entry ) {
			$reduced[ (string) $cap ] = (int) ( $entry['sites'] ?? 0 );
		}

		ksort( $reduced );

		return md5( (string) wp_json_encode( $reduced ) );
	}
}
