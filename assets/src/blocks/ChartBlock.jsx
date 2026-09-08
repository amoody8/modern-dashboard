/**
 * Ranked horizontal bar chart.
 *
 * Form: horizontal bars, because the job is magnitude across named things and
 * site names are long. One series, so there is no legend — the title says what
 * is plotted — and no gridlines, because every bar carries its value at the tip.
 *
 * Colour: a single hue. The plugin's WordPress-admin accent (#2271b1) validates
 * on the light surface but measures 2.88:1 on the dark one, under the 3:1 floor,
 * so dark mode steps to #3987e5. Both are set as `--md-chart-bar` in the
 * stylesheet rather than being hardcoded here.
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { EmptyState, Spinner } from '../components/Primitives';
import { useSites } from '../lib/useSites';
import { formatNumber } from '../lib/format';
import {
	readSiteMetric,
	siteMetricFormatter,
	siteMetricLabel,
} from '../lib/metrics';

/**
 * @param {Object} props       Component props.
 * @param {string} props.title Chart title.
 * @param {Array}  props.rows  Rows of { key, label, value, display }.
 * @return {JSX.Element} The chart.
 */
function BarChart( { title, rows } ) {
	const [ showTable, setShowTable ] = useState( false );

	if ( rows.length === 0 ) {
		return (
			<EmptyState
				title={ __( 'Nothing to chart yet', 'modern-dashboard' ) }
				description={ __(
					'Once sites have been collected their numbers show up here.',
					'modern-dashboard'
				) }
			/>
		);
	}

	// Scale against the largest value so the longest bar fills the track. A zero
	// max would divide by zero, so it floors at 1.
	const max = Math.max( 1, ...rows.map( ( row ) => row.value ) );

	return (
		<figure className="md-chart">
			<figcaption className="md-chart__head">
				<span className="md-chart__title">{ title }</span>
				<button
					type="button"
					className="md-linkish md-chart__toggle"
					aria-pressed={ showTable }
					onClick={ () => setShowTable( ( on ) => ! on ) }
				>
					{ showTable
						? __( 'Show chart', 'modern-dashboard' )
						: __( 'Show as table', 'modern-dashboard' ) }
				</button>
			</figcaption>

			{ showTable ? (
				<table className="md-chart__table">
					<thead>
						<tr>
							<th>{ __( 'Site', 'modern-dashboard' ) }</th>
							<th className="md-numeric">{ title }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.key }>
								<td>{ row.label }</td>
								<td className="md-numeric">{ row.display }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) : (
				<ol className="md-chart__rows">
					{ rows.map( ( row ) => (
						<li
							key={ row.key }
							className="md-chart__row"
							tabIndex={ 0 }
							aria-label={ sprintf(
								/* translators: 1: site name, 2: value */
								__( '%1$s: %2$s', 'modern-dashboard' ),
								row.label,
								row.display
							) }
						>
							<span
								className="md-chart__label"
								title={ row.label }
							>
								{ row.label }
							</span>
							<span className="md-chart__track">
								<span
									className="md-chart__bar"
									style={ {
										width: `${ Math.max(
											1,
											( row.value / max ) * 100
										) }%`,
									} }
								/>
							</span>
							<span className="md-chart__value">
								{ row.display }
							</span>
						</li>
					) ) }
				</ol>
			) }
		</figure>
	);
}

/**
 * Sites ranked by a metric.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.settings Block settings.
 * @return {JSX.Element} The chart.
 */
function TopSites( { settings } ) {
	const metric = settings.metric || 'users';
	const limit = Number( settings.limit ) || 8;
	const { items, loading, error } = useSites( {
		orderby: metric === 'collected_at' ? 'collected_at' : metric,
		order: 'desc',
		per_page: limit,
	} );

	if ( loading ) {
		return <Spinner />;
	}

	if ( error ) {
		return (
			<EmptyState
				title={ __( 'Could not load site data', 'modern-dashboard' ) }
				description={ error }
			/>
		);
	}

	const format = siteMetricFormatter( metric );

	const rows = items.map( ( site ) => {
		const value = readSiteMetric( site, metric );

		return {
			key: site.blog_id,
			label: site.name,
			value,
			display: format( value ),
		};
	} );

	const title =
		settings.title ||
		sprintf(
			/* translators: %s: metric name such as "Users" */
			__( 'Top sites by %s', 'modern-dashboard' ),
			siteMetricLabel( metric ).toLowerCase()
		);

	return <BarChart title={ title } rows={ rows } />;
}

/**
 * Site counts broken down by status.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.settings Block settings.
 * @param {Object} props.overview Network overview payload.
 * @return {JSX.Element} The chart.
 */
function StatusBreakdown( { settings, overview } ) {
	const totals = overview?.totals || {};

	const rows = [
		{
			key: 'public',
			label: __( 'Public', 'modern-dashboard' ),
			value: totals.public || 0,
		},
		{
			key: 'private',
			label: __( 'Not public', 'modern-dashboard' ),
			value: totals.private || 0,
		},
		{
			key: 'archived',
			label: __( 'Archived', 'modern-dashboard' ),
			value: totals.archived || 0,
		},
		{
			key: 'spam',
			label: __( 'Spam', 'modern-dashboard' ),
			value: totals.spam || 0,
		},
		{
			key: 'deleted',
			label: __( 'Deleted', 'modern-dashboard' ),
			value: totals.deleted || 0,
		},
	]
		.filter( ( row ) => row.value > 0 )
		.map( ( row ) => ( { ...row, display: formatNumber( row.value ) } ) );

	return (
		<BarChart
			title={
				settings.title || __( 'Sites by status', 'modern-dashboard' )
			}
			rows={ rows }
		/>
	);
}

export default function ChartBlock( { block, overview } ) {
	const settings = block.settings || {};

	return settings.source === 'status_breakdown' ? (
		<StatusBreakdown settings={ settings } overview={ overview } />
	) : (
		<TopSites settings={ settings } />
	);
}
