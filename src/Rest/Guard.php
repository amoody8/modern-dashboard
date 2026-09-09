<?php
/**
 * Shared REST permission callbacks.
 *
 * A trait rather than a base class: every route class is `final` with its own
 * constructor-injected dependencies and no shared state, so inheritance would
 * buy nothing but a chain to thread through.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Support\Capabilities;
use WP_Error;

defined( 'ABSPATH' ) || exit;

trait Guard {

	public function can_manage(): bool|WP_Error {
		if ( Capabilities::can_manage() ) {
			return true;
		}

		return $this->forbidden(
			__( 'You need network administrator access to use the network dashboard.', 'modern-dashboard' )
		);
	}

	/**
	 * Reading a dashboard is not editing one, so the per-site view capability is
	 * enough here.
	 */
	public function can_view(): bool|WP_Error {
		if ( Capabilities::can_manage() || current_user_can( Capabilities::VIEW ) ) {
			return true;
		}

		return $this->forbidden(
			__( 'You are not allowed to view this dashboard.', 'modern-dashboard' )
		);
	}

	protected function forbidden( string $message ): WP_Error {
		return new WP_Error(
			'modern_dashboard_forbidden',
			$message,
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
