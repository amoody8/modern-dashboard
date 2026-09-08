<?php
/**
 * Capability model.
 *
 * Capabilities are granted dynamically through `user_has_cap` rather than
 * written into roles: roles live per-site in Multisite, so writing them would
 * mean touching every site's options table on activation and again whenever the
 * network admin changes a setting.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Support;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	/** Read and configure the network-wide dashboard. */
	public const MANAGE = 'manage_network_dashboard';

	/** Read the dashboard for a single site. */
	public const VIEW = 'view_modern_dashboard';

	public static function register(): void {
		add_filter( 'user_has_cap', array( self::class, 'grant' ), 10, 4 );
	}

	/**
	 * @param array<string,bool> $allcaps Capabilities the user already has.
	 * @param string[]           $caps    Required primitive capabilities.
	 * @param array<int,mixed>   $args    Context: [ requested cap, user id, ...object id ].
	 * @param \WP_User           $user    The user being checked.
	 *
	 * @return array<string,bool>
	 */
	public static function grant( array $allcaps, array $caps, array $args, $user ): array {
		if ( ! isset( $args[0] ) || ! in_array( $args[0], array( self::MANAGE, self::VIEW ), true ) ) {
			return $allcaps;
		}

		$is_super = is_multisite() && is_super_admin( $user->ID ?? 0 );

		if ( $is_super ) {
			$allcaps[ self::MANAGE ] = true;
			$allcaps[ self::VIEW ]   = true;

			return $allcaps;
		}

		// Site administrators can see their own site's numbers when the network
		// admin has opted in. They never gain the network-wide capability.
		if ( self::VIEW === $args[0] && ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::VIEW ] = (bool) ( new Settings() )->get( 'allow_site_admins' );
		}

		return $allcaps;
	}

	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
