<?php
/**
 * REST surface for the role editor.
 *
 * Network administrators only. Editing capabilities changes who can do what on
 * every site in the network, which is not something a site administrator should
 * reach even for their own site.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Roles\CapabilityCatalogue;
use ModernDashboard\Roles\CoreCapabilities;
use ModernDashboard\Roles\RoleApplier;
use ModernDashboard\Roles\RolePreview;
use ModernDashboard\Roles\RoleRules;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class RoleRoutes {

	use Guard;

	private RoleRules $rules;
	private CapabilityCatalogue $catalogue;
	private RolePreview $preview;

	public function __construct( RoleRules $rules, CapabilityCatalogue $catalogue, RolePreview $preview ) {
		$this->rules     = $rules;
		$this->catalogue = $catalogue;
		$this->preview   = $preview;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_roles' ),
				),
				array(
					'methods'             => 'POST, PUT, PATCH',
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'save_roles' ),
					'args'                => array(
						'enabled'        => array( 'type' => 'boolean' ),
						'excluded_sites' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						// Validated by RoleRules::sanitize(), which rebuilds the
						// structure key by key. A schema detailed enough to
						// describe it would duplicate that logic and drift.
						'roles'          => array( 'type' => 'object' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/roles/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'preview_roles' ),
				'args'                => array(
					'enabled'        => array( 'type' => 'boolean' ),
					'excluded_sites' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'roles'          => array( 'type' => 'object' ),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/roles/catalogue',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'reset_catalogue' ),
			)
		);
	}

	/**
	 * Everything the editor needs, so it hardcodes nothing.
	 */
	public function get_roles(): WP_REST_Response {
		return rest_ensure_response( $this->payload() );
	}

	public function save_roles( WP_REST_Request $request ): WP_REST_Response {
		$this->rules->save(
			array(
				'enabled'        => (bool) $request['enabled'],
				'excluded_sites' => (array) ( $request['excluded_sites'] ?? array() ),
				'roles'          => (array) ( $request['roles'] ?? array() ),
			)
		);

		// The saved state is returned, not the submitted one: the sanitizer
		// drops revocations of protected capabilities, and the editor has to
		// show what was actually stored rather than what was asked for.
		return rest_ensure_response( $this->payload() );
	}

	public function preview_roles( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			$this->preview->impact(
				array(
					'enabled'        => (bool) $request['enabled'],
					'excluded_sites' => (array) ( $request['excluded_sites'] ?? array() ),
					'roles'          => (array) ( $request['roles'] ?? array() ),
				)
			)
		);
	}

	public function reset_catalogue(): WP_REST_Response {
		$this->catalogue->reset();

		return rest_ensure_response( $this->payload() );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payload(): array {
		return array(
			'rules'     => $this->rules->all(),
			'catalogue' => $this->catalogue->to_rest(),
			'roles'     => $this->roles(),
			'protected' => CoreCapabilities::protected_caps(),
			'bypassArg' => RoleApplier::BYPASS_ARG,
		);
	}

	/**
	 * Editable roles, with the capabilities each currently holds.
	 *
	 * Read from the current site's roles, which is the only place they exist.
	 * On a heterogeneous network another site may differ — the editor says so
	 * rather than implying this list is network-wide truth.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function roles(): array {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}

		$out = array();

		foreach ( wp_roles()->roles as $slug => $role ) {
			$out[] = array(
				'slug'  => (string) $slug,
				'label' => translate_user_role( (string) ( $role['name'] ?? $slug ) ),
				'caps'  => array_keys( array_filter( (array) ( $role['capabilities'] ?? array() ) ) ),
			);
		}

		return $out;
	}
}
