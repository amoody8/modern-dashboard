<?php
/**
 * REST surface for branding.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Support\Capabilities;
use ModernDashboard\Theme\Theme;
use ModernDashboard\Theme\ThemeRenderer;
use ModernDashboard\Theme\ThemeRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class ThemeRoutes {

	private ThemeRepository $themes;

	public function __construct( ThemeRepository $themes ) {
		$this->themes = $themes;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/theme',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_theme' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'save_theme' ),
					'args'                => Theme::rest_schema(),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/theme/site/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_site_theme' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'save_site_theme' ),
					'args'                => Theme::rest_schema(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'clear_site_theme' ),
				),
			)
		);
	}

	public function can_manage(): bool|WP_Error {
		if ( Capabilities::can_manage() ) {
			return true;
		}

		return new WP_Error(
			'modern_dashboard_forbidden',
			__( 'You need network administrator access to change branding.', 'modern-dashboard' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function get_theme(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'theme'      => $this->themes->network(),
				'defaults'   => Theme::defaults(),
				'colours'    => Theme::colour_keys(),
				'pairs'      => Theme::contrast_pairs(),
				'bypass_arg' => ThemeRenderer::BYPASS_ARG,
			)
		);
	}

	public function save_theme( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			array( 'theme' => $this->themes->save_network( $this->payload( $request ) ) )
		);
	}

	public function get_site_theme( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$blog_id = (int) $request['id'];

		if ( ! get_site( $blog_id ) ) {
			return $this->no_site();
		}

		$override = $this->themes->site_override( $blog_id );

		return rest_ensure_response(
			array(
				// `null` means "this site has no override and follows the
				// network", which the editor renders differently from an
				// override that merely has branding switched off.
				'theme'    => $override,
				'inherits' => null === $override,
				'network'  => $this->themes->network(),
			)
		);
	}

	public function save_site_theme( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$blog_id = (int) $request['id'];

		if ( ! get_site( $blog_id ) ) {
			return $this->no_site();
		}

		return rest_ensure_response(
			array(
				'theme'    => $this->themes->save_site( $blog_id, $this->payload( $request ) ),
				'inherits' => false,
			)
		);
	}

	public function clear_site_theme( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$blog_id = (int) $request['id'];

		if ( ! get_site( $blog_id ) ) {
			return $this->no_site();
		}

		$this->themes->clear_site( $blog_id );

		return rest_ensure_response(
			array(
				'theme'    => null,
				'inherits' => true,
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payload( WP_REST_Request $request ): array {
		return array_intersect_key( $request->get_params(), Theme::rest_schema() );
	}

	private function no_site(): WP_Error {
		return new WP_Error(
			'modern_dashboard_no_site',
			__( 'That site does not exist in this network.', 'modern-dashboard' ),
			array( 'status' => 404 )
		);
	}
}
