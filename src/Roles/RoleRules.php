<?php
/**
 * The network's role edits.
 *
 * Stored as *intent* rather than as roles. WordPress keeps roles in a per-site
 * option, so writing them would mean touching every site on every save — the
 * thing `Capabilities` already explains this plugin does not do. Instead one
 * network option records what the administrator wants, and it is applied at
 * read time by `RoleApplier`.
 *
 * That makes every edit reversible: switching the feature off restores the
 * network exactly, because nothing was ever overwritten.
 *
 * Grants and revocations are kept as separate lists rather than a
 * capability => bool map, so "not mentioned" stays distinct from "explicitly
 * off". A role the administrator has not touched must be able to pick up a
 * capability that a plugin adds later.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Roles;

defined( 'ABSPATH' ) || exit;

final class RoleRules {

	public const OPTION = 'modern_dashboard_role_rules';

	/** Generous ceiling per list, to bound the option. */
	private const MAX_CAPS = 300;

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Off until asked for. This feature can change who can do what on
			// every site in the network; it should never arrive switched on.
			'enabled'        => false,
			'excluded_sites' => array(),
			'roles'          => array(),
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

		$this->cache = is_array( $stored )
			? array_merge( self::defaults(), $stored )
			: self::defaults();

		return $this->cache;
	}

	/**
	 * @param array<string,mixed> $input Raw input.
	 *
	 * @return array<string,mixed> The stored result.
	 */
	public function save( array $input ): array {
		$clean = $this->sanitize( array_merge( $this->all(), $input ) );

		update_network_option( null, self::OPTION, $clean );
		$this->cache = $clean;

		return $clean;
	}

	/**
	 * Whitelist by construction: nothing survives unless it is copied here.
	 *
	 * @param array<string,mixed> $input Raw input.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize( array $input ): array {
		$roles     = array();
		$protected = CoreCapabilities::protected_caps();

		foreach ( (array) ( $input['roles'] ?? array() ) as $slug => $rule ) {
			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug || ! is_array( $rule ) ) {
				continue;
			}

			$grant = $this->cap_list( $rule['grant'] ?? array() );

			// The floor, enforced server-side rather than in the UI. The editor
			// can be bypassed by posting to the REST route, and revoking
			// `manage_options` would also destroy the way back in: the bypass
			// arguments on the menu and branding subsystems gate on exactly
			// that capability.
			$revoke = array_values(
				array_diff( $this->cap_list( $rule['revoke'] ?? array() ), $protected )
			);

			if ( array() === $grant && array() === $revoke ) {
				continue;
			}

			$roles[ $slug ] = array(
				'grant'  => $grant,
				// A capability cannot be granted and revoked at once; the grant
				// wins, because it is the more specific instruction.
				'revoke' => array_values( array_diff( $revoke, $grant ) ),
			);
		}

		return array(
			'enabled'        => (bool) ( $input['enabled'] ?? false ),
			'excluded_sites' => array_values(
				array_unique( array_filter( array_map( 'absint', (array) ( $input['excluded_sites'] ?? array() ) ) ) )
			),
			'roles'          => $roles,
		);
	}

	/**
	 * Whether the rules apply to a given site.
	 *
	 * @param int|null $blog_id Site to test; the current one by default.
	 */
	public function applies_to_site( ?int $blog_id = null ): bool {
		$rules = $this->all();

		if ( empty( $rules['enabled'] ) ) {
			return false;
		}

		$blog_id = $blog_id ?? get_current_blog_id();

		return ! in_array( (int) $blog_id, array_map( 'intval', (array) $rules['excluded_sites'] ), true );
	}

	/**
	 * The capability delta for a set of roles, merged.
	 *
	 * @param string[] $roles Role slugs the user holds.
	 *
	 * @return array<string,bool> Capability => whether it is granted.
	 */
	public function overlay_for_roles( array $roles ): array {
		$rules   = $this->all();
		$overlay = array();

		foreach ( $roles as $role ) {
			$rule = $rules['roles'][ (string) $role ] ?? null;

			if ( ! is_array( $rule ) ) {
				continue;
			}

			foreach ( (array) ( $rule['revoke'] ?? array() ) as $cap ) {
				$overlay[ (string) $cap ] = false;
			}

			// Grants are applied second: holding two roles where one grants and
			// another revokes should leave the capability granted, matching how
			// WordPress unions the capabilities of multiple roles.
			foreach ( (array) ( $rule['grant'] ?? array() ) as $cap ) {
				$overlay[ (string) $cap ] = true;
			}
		}

		return $overlay;
	}

	/**
	 * @param mixed $value Raw list.
	 *
	 * @return string[]
	 */
	private function cap_list( $value ): array {
		$caps = array();

		foreach ( (array) $value as $cap ) {
			$clean = $this->cap_name( (string) $cap );

			if ( '' !== $clean ) {
				$caps[] = $clean;
			}
		}

		return array_slice( array_values( array_unique( $caps ) ), 0, self::MAX_CAPS );
	}

	/**
	 * Capability names are lowercase with underscores. `sanitize_key` is the
	 * right shape here, unlike audit action names where the dot matters.
	 */
	private function cap_name( string $value ): string {
		return substr( sanitize_key( $value ), 0, 64 );
	}
}
