<?php
/**
 * REST surface for the command palette.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Rest;

use ModernDashboard\Palette\CommandRegistry;
use ModernDashboard\Palette\CommandResolver;
use ModernDashboard\Palette\SearchController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class PaletteRoutes {

	use Guard;

	private CommandResolver $commands;
	private SearchController $search;

	public function __construct( CommandResolver $commands, SearchController $search ) {
		$this->commands = $commands;
		$this->search   = $search;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Routes::NAMESPACE,
			'/palette/commands',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_view' ),
				'callback'            => array( $this, 'get_commands' ),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/palette/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_view' ),
				'callback'            => array( $this, 'get_search' ),
				'args'                => array(
					'term'  => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 2,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'types' => array(
						'type'    => 'array',
						'default' => array( 'post', 'user', 'site' ),
						'items'   => array(
							'type' => 'string',
							'enum' => array( 'post', 'user', 'site' ),
						),
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 50,
					),
				),
			)
		);
	}

	/**
	 * The catalogue, already filtered to what this user may run.
	 *
	 * Group labels ship with it so the client renders headings without knowing
	 * what the group ids mean.
	 */
	public function get_commands(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'commands' => $this->commands->for_current_user(),
				'groups'   => CommandRegistry::groups(),
				'context'  => is_network_admin() ? 'network' : 'site',
				'blogId'   => get_current_blog_id(),
			)
		);
	}

	public function get_search( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			$this->search->search(
				(string) $request['term'],
				array_map( 'strval', (array) $request['types'] ),
				(int) $request['limit']
			)
		);
	}
}
