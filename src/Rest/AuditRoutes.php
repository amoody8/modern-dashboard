<?php
/**
 * REST surface for the audit log.
 *
 * Every route here is `can_manage` — network administrators only. See the
 * security note in `LogRepository` for why the log does not offer a site
 * administrator their own slice the way the metrics endpoints do.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Audit\LogRepository;
use ModernDashboard\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class AuditRoutes {

	use Guard;

	private LogRepository $log;
	private Settings $settings;

	public function __construct( LogRepository $log, Settings $settings ) {
		$this->log      = $log;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/audit',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'get_log' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'action'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'group'    => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'post', 'user', 'plugin', 'theme', 'site' ),
					),
					'blog_id'  => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'user_id'  => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'since'    => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'until'    => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
				),
			)
		);
	}

	public function get_log( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->log->query(
			array(
				'search'   => $request['search'],
				'action'   => $request['action'],
				'group'    => $request['group'],
				'blog_id'  => $request['blog_id'],
				'user_id'  => $request['user_id'],
				'since'    => $request['since'],
				'until'    => $request['until'],
				'page'     => $request['page'],
				'per_page' => $request['per_page'],
			)
		);

		$response = rest_ensure_response(
			array(
				'items'     => $result['items'],
				'total'     => $result['total'],
				'page'      => $result['page'],
				'pages'     => $result['pages'],
				// Self-describing, per the convention the other read endpoints
				// follow: the client builds its filters from what the log
				// actually contains rather than a hardcoded list.
				'actions'   => $this->log->actions(),
				'retention' => (int) $this->settings->get( 'audit_retention_days' ),
				'enabled'   => (bool) $this->settings->get( 'audit_enabled' ),
			)
		);

		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}
}
