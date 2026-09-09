/**
 * Blocks that render a list of sites.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Badge, EmptyState, Spinner } from '../components/Primitives';
import { attentionLabels } from '../lib/labels';
import { useSites } from '../lib/useSites';
import { formatRelative } from '../lib/format';
import {
	readSiteMetric,
	siteMetricFormatter,
	siteMetricLabel,
} from '../lib/metrics';
import { Panel } from '../components/Surfaces';

export function AttentionBlock( { block, overview, onSelectSite } ) {
	const settings = block.settings || {};
	const limit = Number( settings.limit ) || 10;
	const attention = ( overview?.attention || [] ).slice( 0, limit );
	const labels = attentionLabels();

	return (
		<Panel
			title={
				settings.title ||
				__( 'Sites needing attention', 'modern-dashboard' )
			}
			flush
		>
			{ attention.length === 0 ? (
				<EmptyState
					title={ __( 'Everything is quiet', 'modern-dashboard' ) }
					description={ __(
						'No site has pending updates, comments waiting, or a collection problem.',
						'modern-dashboard'
					) }
				/>
			) : (
				<ul className="md-attention">
					{ attention.map( ( site ) => (
						<li key={ site.blog_id } className="md-attention__row">
							<button
								type="button"
								className="md-linkish"
								onClick={ () =>
									onSelectSite && onSelectSite( site.blog_id )
								}
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
										<Badge
											key={ reason }
											tone={ meta.tone }
										>
											{ meta.label }
										</Badge>
									);
								} ) }
							</span>
						</li>
					) ) }
				</ul>
			) }
		</Panel>
	);
}

export function SiteListBlock( { block, onSelectSite } ) {
	const settings = block.settings || {};
	const metric = settings.orderby || 'users';
	const { items, loading, error } = useSites( {
		orderby: metric,
		order: settings.order === 'asc' ? 'asc' : 'desc',
		per_page: Number( settings.limit ) || 5,
	} );

	const format = siteMetricFormatter( metric );

	return (
		<Panel
			title={
				settings.title ||
				sprintf(
					/* translators: %s: metric name such as "Users" */
					__( 'Sites by %s', 'modern-dashboard' ),
					siteMetricLabel( metric ).toLowerCase()
				)
			}
			flush
		>
			{ loading && <Spinner /> }
			{ error && (
				<EmptyState
					title={ __( 'Could not load sites', 'modern-dashboard' ) }
					description={ error }
				/>
			) }

			{ ! loading && ! error && (
				<table className="md-table md-table--compact">
					<tbody>
						{ items.map( ( site ) => (
							<tr key={ site.blog_id }>
								<td>
									<button
										type="button"
										className="md-linkish"
										onClick={ () =>
											onSelectSite &&
											onSelectSite( site.blog_id )
										}
									>
										{ site.name }
									</button>
								</td>
								<td className="md-numeric">
									{ 'collected_at' === metric
										? formatRelative( site.collected_at )
										: format(
												readSiteMetric( site, metric )
										  ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</Panel>
	);
}
