<?php
/**
 * Applies menu rules to the admin menu.
 *
 * Three safeguards exist because a menu editor can hide the very screen someone
 * would use to undo a mistake:
 *
 *  1. Network admin screens are never touched, so the network dashboard — and
 *     this editor — always remain reachable.
 *  2. Super administrators are exempt by default.
 *  3. `?mdash-menu=off` restores the untouched menu for anyone who can
 *     `manage_options`, which is the recovery route for a site administrator who
 *     has been given rules that hide too much.
 *
 * Hiding a menu item is cosmetic. It does not revoke any capability, and the
 * page remains reachable by URL. Use roles and capabilities for access control;
 * use this for tidiness.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Menu;

defined( 'ABSPATH' ) || exit;

final class MenuApplier {

	/** Query argument that disables customisation for one request. */
	public const BYPASS_ARG = 'mdash-menu';

	private MenuRules $rules;

	/** @var array<string,mixed>|null Resolved rules for this request. */
	private ?array $resolved = null;

	private bool $resolved_once = false;

	public function __construct( MenuRules $rules ) {
		$this->rules = $rules;
	}

	public function register(): void {
		// After the catalogue has observed the untouched menu, so what the
		// editor lists is always the real menu rather than an edited one.
		add_action( 'admin_menu', array( $this, 'apply' ), 100001 );
		add_filter( 'custom_menu_order', array( $this, 'wants_custom_order' ) );
		add_filter( 'menu_order', array( $this, 'order' ) );
		add_action( 'admin_notices', array( $this, 'render_bypass_notice' ) );
	}

	/**
	 * Hides and renames. Ordering is handled by the `menu_order` filter, which
	 * WordPress applies after this runs.
	 */
	public function apply(): void {
		$rules = $this->rules_for_request();

		if ( null === $rules ) {
			return;
		}

		foreach ( (array) ( $rules['hidden'] ?? array() ) as $slug ) {
			remove_menu_page( $slug );
		}

		foreach ( (array) ( $rules['submenus'] ?? array() ) as $parent => $sub ) {
			foreach ( (array) ( $sub['hidden'] ?? array() ) as $slug ) {
				remove_submenu_page( $parent, $slug );
			}
		}

		$this->rename( $rules );
	}

	/**
	 * @param array<string,mixed> $rules Resolved rules.
	 */
	private function rename( array $rules ): void {
		global $menu, $submenu;

		$top = (array) ( $rules['renamed'] ?? array() );

		if ( array() !== $top && is_array( $menu ) ) {
			foreach ( $menu as $index => $entry ) {
				$slug = (string) ( $entry[2] ?? '' );

				if ( isset( $top[ $slug ] ) ) {
					// Escaped here because WordPress treats menu titles as
					// trusted markup and prints them without escaping.
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Renaming a menu item has no API; writing the title element of $menu is the documented way, and the global itself is not replaced.
					$menu[ $index ][0] = esc_html( $top[ $slug ] );
				}
			}
		}

		if ( ! is_array( $submenu ) ) {
			return;
		}

		foreach ( (array) ( $rules['submenus'] ?? array() ) as $parent => $sub ) {
			$renames = (array) ( $sub['renamed'] ?? array() );

			if ( array() === $renames || ! isset( $submenu[ $parent ] ) ) {
				continue;
			}

			foreach ( $submenu[ $parent ] as $index => $entry ) {
				$slug = (string) ( $entry[2] ?? '' );

				if ( isset( $renames[ $slug ] ) ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above: the title element is written, not the global.
					$submenu[ $parent ][ $index ][0] = esc_html( $renames[ $slug ] );
				}
			}
		}
	}

	/**
	 * @param bool $enabled Whether another plugin already asked for a custom order.
	 */
	public function wants_custom_order( $enabled ): bool {
		$rules = $this->rules_for_request();

		if ( null === $rules || array() === (array) ( $rules['order'] ?? array() ) ) {
			return (bool) $enabled;
		}

		return true;
	}

	/**
	 * Reorder top-level items.
	 *
	 * Slugs the rules do not mention keep their relative order at the end, so a
	 * newly activated plugin appears rather than disappearing. Separators are
	 * dropped once a custom order exists: their positions are meaningless after
	 * the items around them have moved.
	 *
	 * @param array<int,string> $order Current top-level slug order.
	 *
	 * @return array<int,string>
	 */
	public function order( $order ): array {
		$order  = (array) $order;
		$rules  = $this->rules_for_request();
		$wanted = (array) ( $rules['order'] ?? array() );

		if ( null === $rules || array() === $wanted ) {
			return $order;
		}

		$present = array_map( 'strval', $order );
		$first   = array();

		foreach ( $wanted as $slug ) {
			if ( in_array( (string) $slug, $present, true ) ) {
				$first[] = (string) $slug;
			}
		}

		$rest = array();

		foreach ( $present as $slug ) {
			if ( ! in_array( $slug, $first, true ) && ! str_starts_with( $slug, 'separator' ) ) {
				$rest[] = $slug;
			}
		}

		return array_merge( $first, $rest );
	}

	public function render_bypass_notice(): void {
		if ( ! $this->bypassed() ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e(
			'Showing the unmodified admin menu. Reload without the mdash-menu query argument to see your customised menu again.',
			'modern-dashboard'
		);
		echo '</p></div>';
	}

	/**
	 * Rules for this request, or null when the menu must be left untouched.
	 *
	 * @return array<string,mixed>|null
	 */
	private function rules_for_request(): ?array {
		if ( $this->resolved_once ) {
			return $this->resolved;
		}

		$this->resolved_once = true;
		$this->resolved      = null;

		// Never touch the network admin: the way to fix a bad rule lives there.
		if ( is_network_admin() || is_user_admin() ) {
			return null;
		}

		if ( $this->bypassed() ) {
			return null;
		}

		$this->resolved = $this->rules->resolve_for_user();

		return $this->resolved;
	}

	/**
	 * Gated on `manage_options` so it stays a recovery route for administrators
	 * rather than a way for any logged-in user to undo a tidied menu.
	 */
	private function bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle for one request; it changes nothing.
		$requested = isset( $_GET[ self::BYPASS_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::BYPASS_ARG ] ) ) : '';

		return 'off' === $requested && current_user_can( 'manage_options' );
	}
}
