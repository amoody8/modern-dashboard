<?php
/**
 * REST surface for the menu editor.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Builder\TemplateRepository;
use ModernDashboard\Menu\MenuApplier;
use ModernDashboard\Menu\MenuCatalogue;
use ModernDashboard\Menu\MenuRules;
use ModernDashboard\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class MenuRoutes {

	private MenuCatalogue $catalogue;
	private MenuRules $rules;
	private TemplateRepository $templates;

	public function __construct( MenuCatalogue $catalogue, MenuRules $rules, TemplateRepository $templates ) {
		$this->catalogue = $catalogue;
		$this->rules     = $rules;
		$this->templates = $templates;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/menu',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_menu' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'save_rules' ),
					'args'                => array(
						'enabled'             => array( 'type' => 'boolean' ),
						'exempt_super_admins' => array( 'type' => 'boolean' ),
						'roles'               => array( 'type' => 'object' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/menu/catalogue',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'reset_catalogue' ),
			)
		);
	}

	public function can_manage(): bool|WP_Error {
		if ( Capabilities::can_manage() ) {
			return true;
		}

		return new WP_Error(
			'modern_dashboard_forbidden',
			__( 'You need network administrator access to edit admin menus.', 'modern-dashboard' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function get_menu(): WP_REST_Response {
		$catalogue = $this->catalogue->all();

		return rest_ensure_response(
			array(
				'items'      => $this->catalogue->to_rest(),
				'updated'    => $catalogue['updated'],
				'rules'      => $this->rules->all(),
				'roles'      => $this->templates->assignable_roles(),
				'bypass_arg' => MenuApplier::BYPASS_ARG,
			)
		);
	}

	public function save_rules( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			$this->rules->save(
				array(
					'enabled'             => (bool) $request['enabled'],
					'exempt_super_admins' => (bool) $request['exempt_super_admins'],
					'roles'               => (array) ( $request['roles'] ?? array() ),
				)
			)
		);
	}

	public function reset_catalogue(): WP_REST_Response {
		$this->catalogue->reset();

		return rest_ensure_response(
			array(
				'reset' => true,
				'items' => array(),
			)
		);
	}
}
