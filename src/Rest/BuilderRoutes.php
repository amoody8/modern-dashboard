<?php
/**
 * REST surface for the dashboard builder.
 *
 * Reading the *active* template only needs the view capability, since rendering
 * a dashboard is not the same as editing one. Everything else — listing,
 * authoring, deleting, assigning — is network-admin only.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Builder\BlockRegistry;
use ModernDashboard\Builder\TemplateRepository;
use ModernDashboard\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class BuilderRoutes {

	private BlockRegistry $registry;
	private TemplateRepository $templates;

	public function __construct( BlockRegistry $registry, TemplateRepository $templates ) {
		$this->registry  = $registry;
		$this->templates = $templates;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/blocks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'get_blocks' ),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_templates' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'save_template' ),
					'args'                => array(
						'id'     => array( 'type' => 'string' ),
						'name'   => array( 'type' => 'string' ),
						'blocks' => array( 'type' => 'array' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/templates/active',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_view' ),
				'callback'            => array( $this, 'get_active_template' ),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/templates/assignments',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'save_assignments' ),
				'args'                => array(
					'assignments' => array( 'type' => 'object' ),
					'default'     => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/templates/(?P<id>[a-z0-9_\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_template' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'delete_template' ),
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
			__( 'You need network administrator access to edit dashboards.', 'modern-dashboard' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function can_view(): bool|WP_Error {
		if ( Capabilities::can_manage() || current_user_can( Capabilities::VIEW ) ) {
			return true;
		}

		return new WP_Error(
			'modern_dashboard_forbidden',
			__( 'You are not allowed to view this dashboard.', 'modern-dashboard' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function get_blocks(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'blocks'  => $this->registry->to_rest(),
				'columns' => BlockRegistry::COLUMNS,
			)
		);
	}

	public function get_templates(): WP_REST_Response {
		$state = $this->templates->state();

		return rest_ensure_response(
			array(
				'templates'   => $this->templates->all(),
				'assignments' => $state['assignments'],
				'default'     => $state['default'],
				'roles'       => $this->templates->assignable_roles(),
			)
		);
	}

	public function get_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$template = $this->templates->get( (string) $request['id'] );

		if ( null === $template ) {
			return new WP_Error(
				'modern_dashboard_no_template',
				__( 'That dashboard template does not exist.', 'modern-dashboard' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $template );
	}

	public function get_active_template(): WP_REST_Response {
		return rest_ensure_response( $this->templates->resolve_for_user() );
	}

	public function save_template( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			$this->templates->save(
				array(
					'id'     => $request['id'] ?? '',
					'name'   => $request['name'] ?? '',
					'blocks' => (array) ( $request['blocks'] ?? array() ),
				)
			)
		);
	}

	public function delete_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$deleted = $this->templates->delete( (string) $request['id'] );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		$state = $this->templates->state();

		return rest_ensure_response(
			array(
				'deleted'   => true,
				'templates' => $this->templates->all(),
				'default'   => $state['default'],
			)
		);
	}

	public function save_assignments( WP_REST_Request $request ): WP_REST_Response {
		$default = $request['default'] ?? null;

		return rest_ensure_response(
			$this->templates->assign(
				(array) ( $request['assignments'] ?? array() ),
				null === $default ? null : (string) $default
			)
		);
	}
}
