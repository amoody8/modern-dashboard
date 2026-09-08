<?php
/**
 * Content counts and last-activity signal.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Data\Collectors;

use ModernDashboard\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class ContentCollector implements Collector {

	public function key(): string {
		return 'content';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function collect( \WP_Site $site, Settings $settings ): array {
		$comments = wp_count_comments();

		return array(
			'posts'            => $this->published( 'post' ),
			'pages'            => $this->published( 'page' ),
			'media'            => $this->attachments(),
			'comments'         => (int) ( $comments->approved ?? 0 ),
			'comments_pending' => (int) ( $comments->moderated ?? 0 ),
			'comments_spam'    => (int) ( $comments->spam ?? 0 ),
			'last_published'   => $this->last_published(),
		);
	}

	private function published( string $post_type ): int {
		if ( ! post_type_exists( $post_type ) ) {
			return 0;
		}

		return (int) ( wp_count_posts( $post_type )->publish ?? 0 );
	}

	/**
	 * Attachments use the `inherit` status rather than `publish`.
	 */
	private function attachments(): int {
		return (int) ( wp_count_posts( 'attachment' )->inherit ?? 0 );
	}

	/**
	 * GMT timestamp of the most recent published post, or null if the site has
	 * never published anything.
	 */
	private function last_published(): ?int {
		$latest = get_posts(
			array(
				'numberposts'      => 1,
				'post_type'        => 'any',
				'post_status'      => 'publish',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);

		if ( array() === $latest ) {
			return null;
		}

		$date = get_post_field( 'post_date_gmt', $latest[0] );

		return $date ? (int) strtotime( $date . ' UTC' ) : null;
	}
}
