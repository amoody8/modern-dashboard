<?php
/**
 * Catalogue of block types a dashboard template can contain.
 *
 * The registry is the single authority on what a block is: the client builds
 * its palette and inspector from it, and the sanitizer validates against it.
 * Adding a block type in one place therefore teaches both sides at once.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Builder;

defined( 'ABSPATH' ) || exit;

final class BlockRegistry {

	/** Layout columns a row is divided into. */
	public const COLUMNS = 12;

	/** @var array<string,array<string,mixed>>|null */
	private ?array $blocks = null;

	/**
	 * Every registered block type, keyed by type name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		if ( null !== $this->blocks ) {
			return $this->blocks;
		}

		/**
		 * Filters the registered dashboard block types.
		 *
		 * A block definition needs a `label`, a `category`, a `defaults` map of
		 * setting values, and a `settings` map describing each setting so the
		 * inspector can render a control for it. `min_width`/`max_width` are in
		 * twelfths and bound what the builder will let a user choose.
		 *
		 * @param array<string,array<string,mixed>> $blocks Registered blocks.
		 */
		$blocks = apply_filters( 'modern_dashboard_blocks', $this->core_blocks() );

		$this->blocks = array_filter(
			(array) $blocks,
			static fn( $definition ): bool => is_array( $definition ) && isset( $definition['label'] )
		);

		return $this->blocks;
	}

	public function has( string $type ): bool {
		return isset( $this->all()[ $type ] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( string $type ): ?array {
		return $this->all()[ $type ] ?? null;
	}

	/**
	 * Definitions in the shape the REST API hands to the client.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function to_rest(): array {
		$out = array();

		foreach ( $this->all() as $type => $definition ) {
			$out[] = array_merge( array( 'type' => $type ), $definition );
		}

		return $out;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function core_blocks(): array {
		return array(
			'stat'        => array(
				'label'         => __( 'Statistic', 'modern-dashboard' ),
				'description'   => __( 'A single headline number drawn from the network rollup.', 'modern-dashboard' ),
				'category'      => 'data',
				'min_width'     => 2,
				'max_width'     => 12,
				'default_width' => 3,
				'defaults'      => array(
					'metric' => 'sites',
					'label'  => '',
				),
				'settings'      => array(
					'metric' => array(
						'label'   => __( 'Metric', 'modern-dashboard' ),
						'type'    => 'select',
						'options' => self::metric_options(),
					),
					'label'  => array(
						'label'       => __( 'Label override', 'modern-dashboard' ),
						'type'        => 'text',
						'placeholder' => __( 'Leave empty to use the metric name', 'modern-dashboard' ),
					),
				),
			),
			'chart'       => array(
				'label'         => __( 'Bar chart', 'modern-dashboard' ),
				'description'   => __( 'Ranks the top sites by a metric, or breaks the network down by site status.', 'modern-dashboard' ),
				'category'      => 'data',
				'min_width'     => 4,
				'max_width'     => 12,
				'default_width' => 12,
				'defaults'      => array(
					'source' => 'top_sites',
					'metric' => 'users',
					'limit'  => 8,
					'title'  => '',
				),
				'settings'      => array(
					'source' => array(
						'label'   => __( 'Source', 'modern-dashboard' ),
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 'top_sites',
								'label' => __( 'Top sites by metric', 'modern-dashboard' ),
							),
							array(
								'value' => 'status_breakdown',
								'label' => __( 'Sites by status', 'modern-dashboard' ),
							),
						),
					),
					'metric' => array(
						'label'   => __( 'Metric', 'modern-dashboard' ),
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 'users',
								'label' => __( 'Users', 'modern-dashboard' ),
							),
							array(
								'value' => 'content',
								'label' => __( 'Content', 'modern-dashboard' ),
							),
							array(
								'value' => 'storage',
								'label' => __( 'Uploads', 'modern-dashboard' ),
							),
							array(
								'value' => 'updates',
								'label' => __( 'Pending updates', 'modern-dashboard' ),
							),
						),
						'depends' => array(
							'source' => 'top_sites',
						),
					),
					'limit'  => array(
						'label'   => __( 'Sites to show', 'modern-dashboard' ),
						'type'    => 'number',
						'min'     => 3,
						'max'     => 20,
						'depends' => array(
							'source' => 'top_sites',
						),
					),
					'title'  => array(
						'label'       => __( 'Title override', 'modern-dashboard' ),
						'type'        => 'text',
						'placeholder' => __( 'Leave empty for an automatic title', 'modern-dashboard' ),
					),
				),
			),
			'attention'   => array(
				'label'         => __( 'Needs attention', 'modern-dashboard' ),
				'description'   => __( 'Sites with pending updates, comments to moderate, or a collection problem.', 'modern-dashboard' ),
				'category'      => 'data',
				'min_width'     => 4,
				'max_width'     => 12,
				'default_width' => 7,
				'defaults'      => array(
					'limit' => 10,
					'title' => '',
				),
				'settings'      => array(
					'limit' => array(
						'label' => __( 'Sites to show', 'modern-dashboard' ),
						'type'  => 'number',
						'min'   => 1,
						'max'   => 25,
					),
					'title' => array(
						'label' => __( 'Title override', 'modern-dashboard' ),
						'type'  => 'text',
					),
				),
			),
			'site_list'   => array(
				'label'         => __( 'Site list', 'modern-dashboard' ),
				'description'   => __( 'A compact, ranked table of sites.', 'modern-dashboard' ),
				'category'      => 'data',
				'min_width'     => 6,
				'max_width'     => 12,
				'default_width' => 6,
				'defaults'      => array(
					'orderby' => 'users',
					'order'   => 'desc',
					'limit'   => 5,
					'title'   => '',
				),
				'settings'      => array(
					'orderby' => array(
						'label'   => __( 'Rank by', 'modern-dashboard' ),
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 'users',
								'label' => __( 'Users', 'modern-dashboard' ),
							),
							array(
								'value' => 'content',
								'label' => __( 'Content', 'modern-dashboard' ),
							),
							array(
								'value' => 'storage',
								'label' => __( 'Uploads', 'modern-dashboard' ),
							),
							array(
								'value' => 'updates',
								'label' => __( 'Pending updates', 'modern-dashboard' ),
							),
							array(
								'value' => 'collected_at',
								'label' => __( 'Data age', 'modern-dashboard' ),
							),
						),
					),
					'order'   => array(
						'label'   => __( 'Direction', 'modern-dashboard' ),
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 'desc',
								'label' => __( 'Highest first', 'modern-dashboard' ),
							),
							array(
								'value' => 'asc',
								'label' => __( 'Lowest first', 'modern-dashboard' ),
							),
						),
					),
					'limit'   => array(
						'label' => __( 'Rows', 'modern-dashboard' ),
						'type'  => 'number',
						'min'   => 1,
						'max'   => 25,
					),
					'title'   => array(
						'label' => __( 'Title override', 'modern-dashboard' ),
						'type'  => 'text',
					),
				),
			),
			'environment' => array(
				'label'         => __( 'Network facts', 'modern-dashboard' ),
				'description'   => __( 'WordPress, PHP and database versions, install type and metrics storage mode.', 'modern-dashboard' ),
				'category'      => 'data',
				'min_width'     => 4,
				'max_width'     => 12,
				'default_width' => 5,
				'defaults'      => array( 'title' => '' ),
				'settings'      => array(
					'title' => array(
						'label' => __( 'Title override', 'modern-dashboard' ),
						'type'  => 'text',
					),
				),
			),
			'freshness'   => array(
				'label'         => __( 'Data freshness', 'modern-dashboard' ),
				'description'   => __( 'How current the collected metrics are and when the next refresh runs.', 'modern-dashboard' ),
				'category'      => 'data',
				'min_width'     => 4,
				'max_width'     => 12,
				'default_width' => 6,
				'defaults'      => array(),
				'settings'      => array(),
			),
			'heading'     => array(
				'label'         => __( 'Heading', 'modern-dashboard' ),
				'description'   => __( 'A section title to group the blocks beneath it.', 'modern-dashboard' ),
				'category'      => 'layout',
				'min_width'     => 3,
				'max_width'     => 12,
				'default_width' => 12,
				'defaults'      => array(
					'text'  => '',
					'level' => 2,
				),
				'settings'      => array(
					'text'  => array(
						'label' => __( 'Text', 'modern-dashboard' ),
						'type'  => 'text',
					),
					'level' => array(
						'label'   => __( 'Level', 'modern-dashboard' ),
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 2,
								'label' => __( 'Large', 'modern-dashboard' ),
							),
							array(
								'value' => 3,
								'label' => __( 'Small', 'modern-dashboard' ),
							),
						),
					),
				),
			),
			'text'        => array(
				'label'         => __( 'Note', 'modern-dashboard' ),
				'description'   => __( 'A block of plain text — runbook links, who to call, context for the numbers.', 'modern-dashboard' ),
				'category'      => 'layout',
				'min_width'     => 3,
				'max_width'     => 12,
				'default_width' => 6,
				'defaults'      => array( 'text' => '' ),
				'settings'      => array(
					'text' => array(
						'label' => __( 'Text', 'modern-dashboard' ),
						'type'  => 'textarea',
					),
				),
			),
			'spacer'      => array(
				'label'         => __( 'Spacer', 'modern-dashboard' ),
				'description'   => __( 'Empty space, to push the next block onto its own row.', 'modern-dashboard' ),
				'category'      => 'layout',
				'min_width'     => 1,
				'max_width'     => 12,
				'default_width' => 6,
				'defaults'      => array(),
				'settings'      => array(),
			),
		);
	}

	/**
	 * Metrics the stat block can bind to. Keys match the overview payload.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public static function metric_options(): array {
		return array(
			array(
				'value' => 'sites',
				'label' => __( 'Sites', 'modern-dashboard' ),
			),
			array(
				'value' => 'public',
				'label' => __( 'Public sites', 'modern-dashboard' ),
			),
			array(
				'value' => 'users_unique',
				'label' => __( 'Users (distinct)', 'modern-dashboard' ),
			),
			array(
				'value' => 'users_memberships',
				'label' => __( 'Site memberships', 'modern-dashboard' ),
			),
			array(
				'value' => 'administrators',
				'label' => __( 'Administrators', 'modern-dashboard' ),
			),
			array(
				'value' => 'content',
				'label' => __( 'Posts and pages', 'modern-dashboard' ),
			),
			array(
				'value' => 'posts',
				'label' => __( 'Posts', 'modern-dashboard' ),
			),
			array(
				'value' => 'pages',
				'label' => __( 'Pages', 'modern-dashboard' ),
			),
			array(
				'value' => 'media',
				'label' => __( 'Media items', 'modern-dashboard' ),
			),
			array(
				'value' => 'comments_pending',
				'label' => __( 'Comments awaiting moderation', 'modern-dashboard' ),
			),
			array(
				'value' => 'updates',
				'label' => __( 'Pending updates', 'modern-dashboard' ),
			),
			array(
				'value' => 'storage',
				'label' => __( 'Uploads size', 'modern-dashboard' ),
			),
			array(
				'value' => 'attention',
				'label' => __( 'Sites needing attention', 'modern-dashboard' ),
			),
		);
	}
}
