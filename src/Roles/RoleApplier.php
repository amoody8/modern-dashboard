<?php
/**
 * Applies the network's role edits, without writing any roles.
 *
 * Two halves, and both are needed.
 *
 * **Enforcement** filters `user_has_cap`, which is what actually decides
 * access. Verified: filtering it is sufficient for meta capabilities too —
 * `current_user_can( 'edit_post', $id )` resolves through the primitive
 * capabilities this filter controls, so `map_meta_cap` does not also need
 * filtering. Reimplementing core's mapping table would be a way to silently
 * grant access, not withhold it.
 *
 * **Visibility** hydrates `wp_roles()` in memory, so `get_role( 'editor' )`
 * shows the edited capabilities to any other plugin that inspects roles rather
 * than checking capabilities. Nothing is written: `WP_Roles::add_role()` only
 * touches the option when `use_db` is true, so populating the arrays directly
 * leaves `wp_N_user_roles` untouched.
 *
 * Lockout
 * -------
 * A capability editor can revoke the capability its own recovery route depends
 * on. `?mdash-menu=off` and `?mdash-theme=off` gate on `manage_options`, which
 * this feature can take away — so this one gates on `is_super_admin()` instead.
 *
 * Super administrators are exempt by construction, not by choice: core returns
 * true from `WP_User::has_cap()` before `user_has_cap` is ever fired for them.
 * That is the property that makes this feature safe, and it is why nothing here
 * filters `map_meta_cap` — doing so is the one way to deny a super admin, and
 * therefore the one way to lock everyone out.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Roles;

defined( 'ABSPATH' ) || exit;

final class RoleApplier {

	/** Query argument that suspends role edits for one request. */
	public const BYPASS_ARG = 'mdash-caps';

	private RoleRules $rules;

	private ?bool $active = null;

	/**
	 * Guards against re-entering the filter.
	 *
	 * Anything that calls `current_user_can()` while building the overlay would
	 * fire `user_has_cap` again and recurse until the stack gives out. It only
	 * shows up for super admins on a real page load, which is exactly when it
	 * is most expensive to discover.
	 *
	 * @var bool
	 */
	private bool $building = false;

	/** @var array<string,array<string,bool>> Overlay per "blog:user". */
	private array $overlays = array();

	/** @var array<string,bool>|null Flat set of every capability any rule touches. */
	private ?array $touched = null;

	public function __construct( RoleRules $rules ) {
		$this->rules = $rules;
	}

	public function register(): void {
		// Late, so a plugin hooking at the default priority does not silently
		// hand back a capability this filter just removed.
		add_filter( 'user_has_cap', array( $this, 'filter_caps' ), 999, 4 );

		add_action( 'init', array( $this, 'hydrate' ), 20 );

		// `WP_Roles::for_site()` reloads roles from the option on a switch,
		// discarding the hydration — including inside this plugin's own
		// collectors, which run in switch_to_blog().
		add_action( 'switch_blog', array( $this, 'hydrate' ) );

		add_filter( 'editable_roles', array( $this, 'filter_editable_roles' ) );
		add_action( 'admin_notices', array( $this, 'render_bypass_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_bypass_notice' ) );
	}

	/**
	 * Enforce the edits.
	 *
	 * @param array<string,bool> $allcaps Capabilities the user already has.
	 * @param string[]           $caps    Required primitive capabilities.
	 * @param array<int,mixed>   $args    Context: [ requested cap, user id, ... ].
	 * @param \WP_User           $user    The user being checked.
	 *
	 * @return array<string,bool>
	 */
	public function filter_caps( array $allcaps, array $caps, array $args, $user ): array {
		if ( $this->building || ! $this->active() ) {
			return $allcaps;
		}

		// The cheap exit, and the reason this stays affordable: most checks are
		// for capabilities nobody edited, and they leave on one hash lookup.
		$requested = isset( $args[0] ) ? (string) $args[0] : '';

		if ( '' !== $requested && ! isset( $this->touched()[ $requested ] ) ) {
			$has_touched_primitive = false;

			foreach ( $caps as $cap ) {
				if ( isset( $this->touched()[ (string) $cap ] ) ) {
					$has_touched_primitive = true;
					break;
				}
			}

			if ( ! $has_touched_primitive ) {
				return $allcaps;
			}
		}

		if ( ! $user instanceof \WP_User || ! $user->ID ) {
			return $allcaps;
		}

		return array_merge( $allcaps, $this->overlay_for( $user ) );
	}

	/**
	 * Make the edits visible to code that reads roles rather than checking
	 * capabilities.
	 */
	public function hydrate(): void {
		if ( ! $this->active() || ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$rules = $this->rules->all();
		$roles = wp_roles();

		foreach ( (array) $rules['roles'] as $slug => $rule ) {
			$slug = (string) $slug;

			if ( ! isset( $roles->role_objects[ $slug ] ) ) {
				continue;
			}

			$caps = (array) ( $roles->roles[ $slug ]['capabilities'] ?? array() );

			foreach ( (array) ( $rule['revoke'] ?? array() ) as $cap ) {
				unset( $caps[ (string) $cap ] );
			}

			foreach ( (array) ( $rule['grant'] ?? array() ) as $cap ) {
				$caps[ (string) $cap ] = true;
			}

			// Written into the in-memory objects only. Calling add_cap() here
			// would persist to wp_N_user_roles, which is the whole thing this
			// design avoids.
			$roles->roles[ $slug ]['capabilities'] = $caps;
			$roles->role_objects[ $slug ]          = new \WP_Role( $slug, $caps );
		}
	}

	/**
	 * Reflect edits in every role dropdown core renders.
	 *
	 * @param array<string,mixed> $roles Editable roles.
	 *
	 * @return array<string,mixed>
	 */
	public function filter_editable_roles( array $roles ): array {
		if ( ! $this->active() ) {
			return $roles;
		}

		$rules = $this->rules->all();

		foreach ( (array) $rules['roles'] as $slug => $rule ) {
			$slug = (string) $slug;

			if ( ! isset( $roles[ $slug ] ) ) {
				continue;
			}

			$caps = (array) ( $roles[ $slug ]['capabilities'] ?? array() );

			foreach ( (array) ( $rule['revoke'] ?? array() ) as $cap ) {
				unset( $caps[ (string) $cap ] );
			}

			foreach ( (array) ( $rule['grant'] ?? array() ) as $cap ) {
				$caps[ (string) $cap ] = true;
			}

			$roles[ $slug ]['capabilities'] = $caps;
		}

		return $roles;
	}

	public function render_bypass_notice(): void {
		if ( ! $this->bypassed() ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e(
			'Role edits are suspended for this page load. Reload without the mdash-caps query argument to apply them again.',
			'modern-dashboard'
		);
		echo '</p></div>';
	}

	/**
	 * The merged delta for one user, computed once per user per request.
	 *
	 * @param \WP_User $user The user.
	 *
	 * @return array<string,bool>
	 */
	private function overlay_for( \WP_User $user ): array {
		$key = get_current_blog_id() . ':' . $user->ID;

		if ( isset( $this->overlays[ $key ] ) ) {
			return $this->overlays[ $key ];
		}

		$this->building = true;

		try {
			// Super admins are exempt. Core never fires this filter for them,
			// so this is belt and braces — but it also keeps `is_super_admin()`
			// out of the hot path, since the result is cached per user.
			$overlay = is_multisite() && is_super_admin( $user->ID )
				? array()
				: $this->rules->overlay_for_roles( (array) $user->roles );
		} finally {
			$this->building = false;
		}

		$this->overlays[ $key ] = $overlay;

		return $overlay;
	}

	/**
	 * Every capability any rule mentions, as a lookup.
	 *
	 * @return array<string,bool>
	 */
	private function touched(): array {
		if ( null !== $this->touched ) {
			return $this->touched;
		}

		$touched = array();

		foreach ( (array) $this->rules->all()['roles'] as $rule ) {
			foreach ( array( 'grant', 'revoke' ) as $list ) {
				foreach ( (array) ( $rule[ $list ] ?? array() ) as $cap ) {
					$touched[ (string) $cap ] = true;
				}
			}
		}

		$this->touched = $touched;

		return $this->touched;
	}

	private function active(): bool {
		if ( null !== $this->active ) {
			return $this->active;
		}

		// Never in network admin. The screen an administrator would use to undo
		// a mistake must not itself be governed by that mistake.
		$this->active = ! is_network_admin()
			&& ! is_user_admin()
			&& ! $this->bypassed()
			&& $this->rules->applies_to_site();

		return $this->active;
	}

	/**
	 * Gated on super-admin status, not `manage_options` — this feature can
	 * revoke `manage_options`, which would take the recovery route with it.
	 */
	private function bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle for one request; it changes nothing.
		$requested = isset( $_GET[ self::BYPASS_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::BYPASS_ARG ] ) ) : '';

		return 'off' === $requested && is_multisite() && is_super_admin();
	}
}
