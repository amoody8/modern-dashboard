<?php
/**
 * REST API surface.
 *
 * Everything the dashboard reads is network-level data, so the routes live on
 * the network's main site and are gated on network capabilities. The one
 * exception is a single site's own detail record, which a site administrator may
 * read when the network admin has allowed it.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Cron\Scheduler;
use ModernDashboard\Data\MetricsRepository;
use ModernDashboard\Data\NetworkAggregator;
use ModernDashboard\Settings\Settings;
use ModernDashboard\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Routes {

	use Guard;

	public const NAMESPACE = 'modern-dashboard/v1';

	private MetricsRepository $repository;
	private NetworkAggregator $aggregator;
	private Settings $settings;

	public function __construct( MetricsRepository $repository, NetworkAggregator $aggregator, Settings $settings ) {
		$this->repository = $repository;
		$this->aggregator = $aggregator;
		$this->settings   = $settings;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'get_overview' ),
				'args'                => array(
					'fresh' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Bypass the short-lived overview cache.', 'modern-dashboard' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sites',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'get_sites' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'   => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( 'all', 'public', 'private', 'archived', 'spam', 'deleted' ),
					),
					'flag'     => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( 'all', 'needs_updates', 'inactive', 'attention', 'stale' ),
					),
					'orderby'  => array(
						'type'    => 'string',
						'default' => 'name',
						'enum'    => array( 'name', 'id', 'users', 'content', 'posts', 'storage', 'updates', 'last_published', 'registered', 'collected_at' ),
					),
					'order'    => array(
						'type'    => 'string',
						'default' => 'asc',
						'enum'    => array( 'asc', 'desc' ),
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 200,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sites/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_read_site' ),
				'callback'            => array( $this, 'get_site' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sites/(?P<id>\d+)/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'refresh_site' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'refresh_batch' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'get_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'update_settings' ),
					'args'                => Settings::rest_schema(),
				),
			)
		);
	}


	/**
	 * Network admins read any site. A site administrator may read their own
	 * site's record when the network has opted in.
	 */
	public function can_read_site( WP_REST_Request $request ): bool|WP_Error {
		if ( Capabilities::can_manage() ) {
			return true;
		}

		$requested = (int) $request['id'];

		if ( get_current_blog_id() === $requested && current_user_can( Capabilities::VIEW ) ) {
			return true;
		}

		return $this->forbidden(
			__( 'You are not allowed to read this site\'s dashboard data.', 'modern-dashboard' )
		);
	}

	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->aggregator->overview( ! $request['fresh'] ) );
	}

	public function get_sites( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->repository->query(
			array(
				'search'   => $request['search'],
				'status'   => $request['status'],
				'flag'     => $request['flag'],
				'orderby'  => $request['orderby'],
				'order'    => $request['order'],
				'page'     => $request['page'],
				'per_page' => $request['per_page'],
			)
		);

		$response = rest_ensure_response( $result );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	public function get_site( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$metrics = $this->repository->get( (int) $request['id'] );

		if ( null === $metrics ) {
			return new WP_Error(
				'modern_dashboard_not_collected',
				__( 'This site has not been collected yet. Refresh it to gather its metrics.', 'modern-dashboard' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $metrics );
	}

	public function refresh_site( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$metrics = $this->repository->refresh( (int) $request['id'] );

		if ( null === $metrics ) {
			return new WP_Error(
				'modern_dashboard_no_site',
				__( 'That site does not exist in this network.', 'modern-dashboard' ),
				array( 'status' => 404 )
			);
		}

		NetworkAggregator::flush();

		return rest_ensure_response( $metrics );
	}

	public function refresh_batch(): WP_REST_Response {
		$scheduler = new Scheduler( $this->repository, $this->settings );
		$refreshed = $scheduler->run();

		return rest_ensure_response(
			array(
				'refreshed' => $refreshed,
				'count'     => count( $refreshed ),
				'next_run'  => Scheduler::next_run(),
			)
		);
	}

	public function get_settings(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'settings'  => $this->settings->all(),
				'schema'    => Settings::rest_schema(),
				'intervals' => Settings::INTERVALS,
			)
		);
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		// `get_params()` covers JSON and form-encoded bodies alike; intersecting
		// with the schema drops anything that is not a real setting.
		$input = array_intersect_key( $request->get_params(), Settings::rest_schema() );

		$updated = $this->settings->update( $input );

		// `ensure_scheduled` already ran on `init` for this request, so apply an
		// interval change now rather than leaving it a page load behind.
		( new Scheduler( $this->repository, $this->settings ) )->ensure_scheduled();

		NetworkAggregator::flush();

		return rest_ensure_response(
			array(
				'settings' => $updated,
				'next_run' => Scheduler::next_run(),
			)
		);
	}
}
