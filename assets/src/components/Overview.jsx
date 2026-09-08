/**
 * Network overview: headline numbers plus the sites that need a human.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge, EmptyState, Notice, Spinner, StatCard } from './Primitives';
import { attentionLabels } from '../lib/labels';
import { formatBytes, formatNumber, formatRelative } from '../lib/format';
import { config } from '../lib/api';

function Freshness( { freshness } ) {
	const parts = [];

	if ( freshness.never_collected > 0 ) {
		parts.push(
			sprintf(
				/* translators: %s: number of sites */
				_n(
					'%s site not collected yet',
					'%s sites not collected yet',
					freshness.never_collected,
					'modern-dashboard'
				),
				formatNumber( freshness.never_collected )
			)
		);
	}

	if ( freshness.stale > 0 ) {
		parts.push(
			sprintf(
				/* translators: %s: number of sites */
				_n(
					'%s site is stale',
					'%s sites are stale',
					freshness.stale,
					'modern-dashboard'
				),
				formatNumber( freshness.stale )
			)
		);
	}

	parts.push(
		sprintf(
			/* translators: %s: relative time such as "in 12 minutes" */
			__( 'next refresh %s', 'modern-dashboard' ),
			freshness.next_run
				? formatRelative( freshness.next_run )
				: __( 'not scheduled', 'modern-dashboard' )
		)
	);

	return <p className="md-freshness">{ parts.join( ' · ' ) }</p>;
}

function AttentionRow( { site, onSelect } ) {
	const labels = attentionLabels();

	return (
		<li className="md-attention__row">
			<button
				type="button"
				className="md-linkish"
				onClick={ () => onSelect( site.blog_id ) }
			>
				{ site.name }
			</button>
			<span className="md-attention__reasons">
				{ site.reasons.map( ( reason ) => {
					const meta = labels[ reason ] || {
						label: reason,
						tone: 'muted',
					};

					return (
						<Badge key={ reason } tone={ meta.tone }>
							{ meta.label }
						</Badge>
					);
				} ) }
			</span>
		</li>
	);
}

export default function Overview( {
	overview,
	loading,
	error,
	onSelectSite,
	visibleCards,
} ) {
	if ( error ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! overview ) {
		return (
			<Spinner
				label={ __( 'Reading the network…', 'modern-dashboard' ) }
			/>
		);
	}

	const {
		totals,
		users,
		attention,
		attention_count: attentionCount,
		freshness,
		environment,
	} = overview;
	const shows = ( card ) => visibleCards.includes( card );
	const updates = totals.plugin_updates + totals.theme_updates;

	return (
		<div
			className={ loading ? 'md-overview is-refreshing' : 'md-overview' }
		>
			<div className="md-stats">
				{ shows( 'sites' ) && (
					<StatCard
						label={ __( 'Sites', 'modern-dashboard' ) }
						value={ formatNumber( totals.sites ) }
						hint={ sprintf(
							/* translators: 1: public site count, 2: non-public site count */
							__(
								'%1$s public · %2$s not public',
								'modern-dashboard'
							),
							formatNumber( totals.public ),
							formatNumber( totals.private )
						) }
					/>
				) }

				{ shows( 'users' ) && (
					<StatCard
						label={ __( 'Users', 'modern-dashboard' ) }
						value={ formatNumber( users.unique ) }
						hint={ sprintf(
							/* translators: %s: number of site memberships */
							__( '%s site memberships', 'modern-dashboard' ),
							formatNumber( users.memberships )
						) }
					/>
				) }

				{ shows( 'content' ) && (
					<StatCard
						label={ __( 'Content', 'modern-dashboard' ) }
						value={ formatNumber( totals.posts + totals.pages ) }
						hint={ sprintf(
							/* translators: 1: post count, 2: page count, 3: media count */
							__(
								'%1$s posts · %2$s pages · %3$s media',
								'modern-dashboard'
							),
							formatNumber( totals.posts ),
							formatNumber( totals.pages ),
							formatNumber( totals.media )
						) }
					/>
				) }

				{ shows( 'updates' ) && (
					<StatCard
						label={ __( 'Pending updates', 'modern-dashboard' ) }
						value={ formatNumber( updates ) }
						tone={ updates > 0 ? 'warn' : 'good' }
						hint={ sprintf(
							/* translators: 1: plugin update count, 2: theme update count */
							__(
								'%1$s plugin · %2$s theme',
								'modern-dashboard'
							),
							formatNumber( totals.plugin_updates ),
							formatNumber( totals.theme_updates )
						) }
						footer={
							environment.core_update ? (
								<a
									href={ config.links?.updates }
									className="md-linkish"
								>
									{ sprintf(
										/* translators: %s: WordPress version number */
										__(
											'WordPress %s is available',
											'modern-dashboard'
										),
										environment.core_update
									) }
								</a>
							) : null
						}
					/>
				) }

				{ shows( 'storage' ) && (
					<StatCard
						label={ __( 'Uploads', 'modern-dashboard' ) }
						value={ formatBytes( totals.storage_bytes ) }
						hint={
							totals.storage_partial
								? __(
										'Partial — some scans timed out',
										'modern-dashboard'
								  )
								: __( 'Across every site', 'modern-dashboard' )
						}
						tone={ totals.storage_partial ? 'warn' : 'neutral' }
					/>
				) }

				{ shows( 'attention' ) && (
					<StatCard
						label={ __( 'Needs attention', 'modern-dashboard' ) }
						value={ formatNumber( attentionCount ) }
						tone={ attentionCount > 0 ? 'warn' : 'good' }
						hint={
							attentionCount > 0
								? __(
										'Sites with something outstanding',
										'modern-dashboard'
								  )
								: __(
										'Nothing outstanding',
										'modern-dashboard'
								  )
						}
					/>
				) }
			</div>

			<Freshness freshness={ freshness } />

			{ shows( 'attention' ) && (
				<section className="md-panel">
					<header className="md-panel__header">
						<h2>
							{ __(
								'Sites needing attention',
								'modern-dashboard'
							) }
						</h2>
						{ attentionCount > attention.length && (
							<span className="md-panel__meta">
								{ sprintf(
									/* translators: 1: shown count, 2: total count */
									__(
										'showing %1$s of %2$s',
										'modern-dashboard'
									),
									formatNumber( attention.length ),
									formatNumber( attentionCount )
								) }
							</span>
						) }
					</header>

					{ attention.length === 0 ? (
						<EmptyState
							title={ __(
								'Everything is quiet',
								'modern-dashboard'
							) }
							description={ __(
								'No site in the network has pending updates, comments waiting, or a collection problem.',
								'modern-dashboard'
							) }
						/>
					) : (
						<ul className="md-attention">
							{ attention.map( ( site ) => (
								<AttentionRow
									key={ site.blog_id }
									site={ site }
									onSelect={ onSelectSite }
								/>
							) ) }
						</ul>
					) }
				</section>
			) }

			<section className="md-panel">
				<header className="md-panel__header">
					<h2>{ __( 'Network', 'modern-dashboard' ) }</h2>
				</header>
				<dl className="md-facts">
					<div>
						<dt>{ __( 'WordPress', 'modern-dashboard' ) }</dt>
						<dd>{ environment.wp_version }</dd>
					</div>
					<div>
						<dt>{ __( 'PHP', 'modern-dashboard' ) }</dt>
						<dd>{ environment.php_version }</dd>
					</div>
					<div>
						<dt>{ __( 'Database', 'modern-dashboard' ) }</dt>
						<dd>{ environment.db_version }</dd>
					</div>
					<div>
						<dt>{ __( 'Install type', 'modern-dashboard' ) }</dt>
						<dd>
							{ environment.subdomain
								? __( 'Subdomain', 'modern-dashboard' )
								: __( 'Subdirectory', 'modern-dashboard' ) }
						</dd>
					</div>
					<div>
						<dt>{ __( 'Metrics storage', 'modern-dashboard' ) }</dt>
						<dd>
							{ environment.site_meta
								? __( 'Site meta', 'modern-dashboard' )
								: __(
										'Network options (run a network upgrade for site meta)',
										'modern-dashboard'
								  ) }
						</dd>
					</div>
				</dl>
			</section>
		</div>
	);
}
