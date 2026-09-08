<?php
/**
 * Per-role admin menu rules.
 *
 * Rules reference menu slugs, which differ from site to site depending on what
 * is active there. A rule naming a slug a given site does not have is simply a
 * no-op, so one network-wide rule set can serve a heterogeneous network without
 * needing to know which site has what.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Menu;

use ModernDashboard\Builder\TemplateRepository;

defined( 'ABSPATH' ) || exit;

final class MenuRules {

	public const OPTION = 'modern_dashboard_menu_rules';

	/** Generous ceiling on entries in any one list, to bound the option size. */
	private const MAX_ENTRIES = 200;

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array{enabled:bool,exempt_super_admins:bool,roles:array<string,mixed>}
	 */
	public static function defaults(): array {
		return array(
			'enabled'             => false,
			'exempt_super_admins' => true,
			'roles'               => array(),
		);
	}

	/**
	 * @return array{enabled:bool,exempt_super_admins:bool,roles:array<string,mixed>}
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_network_option( null, self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$this->cache = array(
			'enabled'             => (bool) ( $stored['enabled'] ?? false ),
			'exempt_super_admins' => (bool) ( $stored['exempt_super_admins'] ?? true ),
			'roles'               => is_array( $stored['roles'] ?? null ) ? $stored['roles'] : array(),
		);

		return $this->cache;
	}

	/**
	 * Replace the whole rule set.
	 *
	 * @param array<string,mixed> $input Untrusted input.
	 *
	 * @return array{enabled:bool,exempt_super_admins:bool,roles:array<string,mixed>}
	 */
	public function save( array $input ): array {
		$clean = $this->sanitize( $input );

		update_network_option( null, self::OPTION, $clean );
		$this->cache = $clean;

		return $clean;
	}

	/**
	 * @param array<string,mixed> $input Untrusted input.
	 *
	 * @return array{enabled:bool,exempt_super_admins:bool,roles:array<string,mixed>}
	 */
	public function sanitize( array $input ): array {
		$roles = array();

		foreach ( (array) ( $input['roles'] ?? array() ) as $role => $rules ) {
			$role = $this->slug( (string) $role );

			if ( '' === $role || ! is_array( $rules ) ) {
				continue;
			}

			$clean = array(
				'hidden'   => $this->slug_list( $rules['hidden'] ?? array() ),
				'renamed'  => $this->label_map( $rules['renamed'] ?? array() ),
				'order'    => $this->slug_list( $rules['order'] ?? array() ),
				'submenus' => array(),
			);

			foreach ( (array) ( $rules['submenus'] ?? array() ) as $parent => $sub ) {
				$parent = $this->slug( (string) $parent );

				if ( '' === $parent || ! is_array( $sub ) ) {
					continue;
				}

				$hidden  = $this->slug_list( $sub['hidden'] ?? array() );
				$renamed = $this->label_map( $sub['renamed'] ?? array() );

				// An empty submenu entry carries no meaning; dropping it keeps
				// the stored rules readable and small.
				if ( array() !== $hidden || array() !== $renamed ) {
					$clean['submenus'][ $parent ] = array(
						'hidden'  => $hidden,
						'renamed' => $renamed,
					);
				}
			}

			$roles[ $role ] = $clean;
		}

		return array(
			'enabled'             => (bool) ( $input['enabled'] ?? false ),
			'exempt_super_admins' => (bool) ( $input['exempt_super_admins'] ?? true ),
			'roles'               => $roles,
		);
	}

	/**
	 * The rules that apply to a user, or null when the menu should be left alone.
	 *
	 * @return array<string,mixed>|null
	 */
	public function resolve_for_user( ?\WP_User $user = null ): ?array {
		$state = $this->all();

		if ( ! $state['enabled'] ) {
			return null;
		}

		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return null;
		}

		$is_super = is_multisite() && is_super_admin( $user->ID );

		// A network administrator who has hidden their own way back to this
		// screen has no way to undo it, so they are exempt unless the network
		// explicitly opts out.
		if ( $is_super && $state['exempt_super_admins'] ) {
			return null;
		}

		$roles = $is_super ? array( TemplateRepository::SUPER_ADMIN_ROLE ) : array();
		$roles = array_merge( $roles, array_map( 'strval', (array) $user->roles ) );

		foreach ( $roles as $role ) {
			if ( isset( $state['roles'][ $role ] ) ) {
				return $state['roles'][ $role ];
			}
		}

		return null;
	}

	/**
	 * Menu slugs are not WordPress slugs: they can be file names with query
	 * strings (`edit.php?post_type=page`) or even absolute URLs, so `sanitize_key`
	 * would destroy them. This keeps the characters those forms need and drops
	 * everything else, including whitespace and control characters.
	 */
	private function slug( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9_\-.\/?=&%:+]/', '', $value ) ?? '';

		return substr( $value, 0, 255 );
	}

	/**
	 * @param mixed $input Untrusted list of slugs.
	 *
	 * @return array<int,string>
	 */
	private function slug_list( mixed $input ): array {
		$slugs = array();

		foreach ( (array) $input as $value ) {
			$slug = $this->slug( (string) $value );

			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_slice( array_values( array_unique( $slugs ) ), 0, self::MAX_ENTRIES );
	}

	/**
	 * Renames are stored as plain text. They are escaped again at the point they
	 * are written into the menu, because WordPress treats menu titles as trusted
	 * markup authored by plugin code and does not escape them on output.
	 *
	 * @param mixed $input Untrusted slug => label map.
	 *
	 * @return array<string,string>
	 */
	private function label_map( mixed $input ): array {
		$map = array();

		foreach ( (array) $input as $slug => $label ) {
			$slug  = $this->slug( (string) $slug );
			$label = trim( sanitize_text_field( (string) $label ) );

			if ( '' !== $slug && '' !== $label && count( $map ) < self::MAX_ENTRIES ) {
				$map[ $slug ] = substr( $label, 0, 120 );
			}
		}

		return $map;
	}
}
