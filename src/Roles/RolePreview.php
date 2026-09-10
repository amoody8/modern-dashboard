<?php
/**
 * What a draft would actually change.
 *
 * The other safeguards in this feature — the protected floor, the bypass
 * argument, super-admin exemption — are all about recovering from a mistake.
 * This one is about not making it. A checkbox labelled "Edit published posts"
 * is abstract; "affects 14 people across 4 sites" is not, and the second is
 * what makes somebody pause.
 *
 * Counting users per role means asking every site, which is exactly the kind of
 * work this plugin does on cron rather than on a page load. It is acceptable
 * here because it happens once, when a person clicks a button and is waiting
 * for the answer — but it is bounded, and it says so when it stops counting.
 *
 * @package ModernDashboard
 */

declare( strict_types = 1 );

namespace ModernDashboard\Roles;

use ModernDashboard\Data\MetricsRepository;

defined( 'ABSPATH' ) || exit;

final class RolePreview {

	/**
	 * Sites counted before the preview reports a partial answer.
	 *
	 * A network larger than this cannot be surveyed inside a request, and a
	 * number that silently undercounts is worse than one that admits its limit.
	 */
	private const MAX_SITES = 60;

	private RoleRules $rules;
	private MetricsRepository $repository;

	public function __construct( RoleRules $rules, MetricsRepository $repository ) {
		$this->rules      = $rules;
		$this->repository = $repository;
	}

	/**
	 * Describe the difference between what is stored and what is proposed.
	 *
	 * @param array<string,mixed> $draft The unsaved rules.
	 *
	 * @return array<string,mixed>
	 */
	public function impact( array $draft ): array {
		$current  = $this->rules->all();
		$proposed = $this->rules->sanitize( array_merge( $current, $draft ) );

		$site_ids = $this->repository->site_ids();
		$excluded = array_map( 'intval', (array) ( $proposed['excluded_sites'] ?? array() ) );
		$affected = array_values( array_diff( $site_ids, $excluded ) );

		$partial = count( $affected ) > self::MAX_SITES;
		$counted = $partial ? array_slice( $affected, 0, self::MAX_SITES ) : $affected;

		$changes = array();

		foreach ( $this->changed_roles( $current, $proposed ) as $slug => $delta ) {
			$changes[] = array(
				'role'    => $slug,
				'label'   => $this->role_label( $slug ),
				'users'   => $this->count_users( $slug, $counted ),
				'grant'   => array_values( $delta['grant'] ),
				'revoke'  => array_values( $delta['revoke'] ),
				'restore' => array_values( $delta['restore'] ),
			);
		}

		return array(
			'changes'      => $changes,
			'sites'        => count( $affected ),
			'sitesCounted' => count( $counted ),
			'partial'      => $partial,
			'enabling'     => empty( $current['enabled'] ) && ! empty( $proposed['enabled'] ),
			'disabling'    => ! empty( $current['enabled'] ) && empty( $proposed['enabled'] ),
		);
	}

	/**
	 * Roles whose rule differs between stored and proposed, and how.
	 *
	 * `restore` is the third case and the one people forget: a capability that
	 * was being revoked and no longer is comes *back*, which is as much a change
	 * as taking one away.
	 *
	 * @param array<string,mixed> $current  Stored rules.
	 * @param array<string,mixed> $proposed Sanitized draft.
	 *
	 * @return array<string,array<string,string[]>>
	 */
	private function changed_roles( array $current, array $proposed ): array {
		$slugs = array_unique(
			array_merge(
				array_keys( (array) ( $current['roles'] ?? array() ) ),
				array_keys( (array) ( $proposed['roles'] ?? array() ) )
			)
		);

		$changes = array();

		foreach ( $slugs as $slug ) {
			$was = (array) ( $current['roles'][ $slug ] ?? array(
				'grant'  => array(),
				'revoke' => array(),
			) );
			$now = (array) ( $proposed['roles'][ $slug ] ?? array(
				'grant'  => array(),
				'revoke' => array(),
			) );

			$delta = array(
				'grant'   => array_diff( (array) ( $now['grant'] ?? array() ), (array) ( $was['grant'] ?? array() ) ),
				'revoke'  => array_diff( (array) ( $now['revoke'] ?? array() ), (array) ( $was['revoke'] ?? array() ) ),
				'restore' => array_merge(
					array_diff( (array) ( $was['revoke'] ?? array() ), (array) ( $now['revoke'] ?? array() ) ),
					array_diff( (array) ( $was['grant'] ?? array() ), (array) ( $now['grant'] ?? array() ) )
				),
			);

			if ( array() === $delta['grant'] && array() === $delta['revoke'] && array() === $delta['restore'] ) {
				continue;
			}

			$changes[ (string) $slug ] = $delta;
		}

		return $changes;
	}

	/**
	 * How many people hold a role, across the sites being counted.
	 *
	 * Deliberately a headcount of *memberships*, not distinct people: somebody
	 * who is an editor on three sites is affected three times over, and that is
	 * the number that describes the blast radius.
	 *
	 * @param string $slug     Role slug.
	 * @param int[]  $site_ids Sites to count.
	 */
	private function count_users( string $slug, array $site_ids ): int {
		$total = 0;

		foreach ( $site_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );

			try {
				$counts = count_users();
				$total += (int) ( $counts['avail_roles'][ $slug ] ?? 0 );
			} catch ( \Throwable $e ) {
				// One unreadable site must not cost the whole preview.
				continue;
			} finally {
				restore_current_blog();
			}
		}

		return $total;
	}

	private function role_label( string $slug ): string {
		$names = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();

		return isset( $names[ $slug ] )
			? translate_user_role( (string) $names[ $slug ] )
			: $slug;
	}
}
