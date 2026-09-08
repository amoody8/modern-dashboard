/**
 * Sortable, filterable list of every site in the network.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge, EmptyState, Field, Notice, Spinner } from './Primitives';
import { api } from '../lib/api';
import { attentionLabels } from '../lib/labels';
import {
	formatAddress,
	formatBytes,
	formatNumber,
	formatRelative,
} from '../lib/format';

const COLUMNS = [
	{ key: 'name', label: __( 'Site', 'modern-dashboard' ), sortable: true },
	{
		key: 'users',
		label: __( 'Users', 'modern-dashboard' ),
		sortable: true,
		numeric: true,
	},
	{
		key: 'content',
		label: __( 'Content', 'modern-dashboard' ),
		sortable: true,
		numeric: true,
	},
	{
		key: 'updates',
		label: __( 'Updates', 'modern-dashboard' ),
		sortable: true,
		numeric: true,
	},
	{
		key: 'storage',
		label: __( 'Uploads', 'modern-dashboard' ),
		sortable: true,
		numeric: true,
	},
	{
		key: 'last_published',
		label: __( 'Last post', 'modern-dashboard' ),
		sortable: true,
	},
	{
		key: 'collected_at',
		label: __( 'Collected', 'modern-dashboard' ),
		sortable: true,
	},
];

const STATUSES = () => [
	{ value: 'all', label: __( 'All statuses', 'modern-dashboard' ) },
	{ value: 'public', label: __( 'Public', 'modern-dashboard' ) },
	{ value: 'private', label: __( 'Not public', 'modern-dashboard' ) },
	{ value: 'archived', label: __( 'Archived', 'modern-dashboard' ) },
	{ value: 'spam', label: __( 'Spam', 'modern-dashboard' ) },
	{ value: 'deleted', label: __( 'Deleted', 'modern-dashboard' ) },
];

const FLAGS = () => [
	{ value: 'all', label: __( 'Everything', 'modern-dashboard' ) },
	{ value: 'attention', label: __( 'Needs attention', 'modern-dashboard' ) },
	{ value: 'needs_updates', label: __( 'Has updates', 'modern-dashboard' ) },
	{ value: 'inactive', label: __( 'Inactive', 'modern-dashboard' ) },
	{ value: 'stale', label: __( 'Stale data', 'modern-dashboard' ) },
];

/**
 * @param {boolean} active Whether the column is the active sort column.
 * @param {string}  order  Current sort direction.
 * @return {string} An `aria-sort` value.
 */
function ariaSort( active, order ) {
	if ( ! active ) {
		return 'none';
	}

	return order === 'asc' ? 'ascending' : 'descending';
}

/**
 * @param {boolean} active Whether the column is the active sort column.
 * @param {string}  order  Current sort direction.
 * @return {string} A direction arrow, or an empty string when inactive.
 */
function sortArrow( active, order ) {
	if ( ! active ) {
		return '';
	}

	return order === 'asc' ? '\u25b2' : '\u25bc';
}

export default function SitesTable( {
	onSelectSite,
	selectedId,
	refreshToken,
} ) {
	const [ query, setQuery ] = useState( {
		search: '',
		status: 'all',
		flag: 'all',
		orderby: 'name',
		order: 'asc',
		page: 1,
		per_page: 20,
	} );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	// Guards against an earlier, slower request overwriting a newer one.
	const requestId = useRef( 0 );

	useEffect( () => {
		const timer = setTimeout( () => {
			setQuery( ( current ) =>
				current.search === searchInput
					? current
					: { ...current, search: searchInput, page: 1 }
			);
		}, 250 );

		return () => clearTimeout( timer );
	}, [ searchInput ] );

	useEffect( () => {
		const id = ++requestId.current;

		setLoading( true );

		api.sites( query )
			.then( ( data ) => {
				if ( id === requestId.current ) {
					setResult( data );
					setError( null );
				}
			} )
			.catch( ( err ) => {
				if ( id === requestId.current ) {
					setError(
						err.message ||
							__(
								'Could not load the site list.',
								'modern-dashboard'
							)
					);
				}
			} )
			.finally( () => {
				if ( id === requestId.current ) {
					setLoading( false );
				}
			} );
	}, [ query, refreshToken ] );

	const sortBy = useCallback( ( key ) => {
		setQuery( ( current ) => ( {
			...current,
			orderby: key,
			order:
				current.orderby === key && current.order === 'asc'
					? 'desc'
					: 'asc',
			page: 1,
		} ) );
	}, [] );

	const setPage = useCallback( ( page ) => {
		setQuery( ( current ) => ( { ...current, page } ) );
	}, [] );

	if ( error ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	const labels = attentionLabels();

	return (
		<div className="md-sites">
			<div className="md-filters">
				<Field
					label={ __( 'Search sites', 'modern-dashboard' ) }
					hideLabel
					className="md-field--grow"
				>
					{ ( id ) => (
						<input
							id={ id }
							type="search"
							value={ searchInput }
							placeholder={ __(
								'Search by name or address…',
								'modern-dashboard'
							) }
							onChange={ ( event ) =>
								setSearchInput( event.target.value )
							}
						/>
					) }
				</Field>

				<Field
					label={ __( 'Filter by status', 'modern-dashboard' ) }
					hideLabel
				>
					{ ( id ) => (
						<select
							id={ id }
							value={ query.status }
							onChange={ ( event ) =>
								setQuery( ( current ) => ( {
									...current,
									status: event.target.value,
									page: 1,
								} ) )
							}
						>
							{ STATUSES().map( ( option ) => (
								<option
									key={ option.value }
									value={ option.value }
								>
									{ option.label }
								</option>
							) ) }
						</select>
					) }
				</Field>

				<Field
					label={ __( 'Filter by state', 'modern-dashboard' ) }
					hideLabel
				>
					{ ( id ) => (
						<select
							id={ id }
							value={ query.flag }
							onChange={ ( event ) =>
								setQuery( ( current ) => ( {
									...current,
									flag: event.target.value,
									page: 1,
								} ) )
							}
						>
							{ FLAGS().map( ( option ) => (
								<option
									key={ option.value }
									value={ option.value }
								>
									{ option.label }
								</option>
							) ) }
						</select>
					) }
				</Field>
			</div>

			{ ! result && loading && (
				<Spinner label={ __( 'Loading sites…', 'modern-dashboard' ) } />
			) }

			{ result && result.items.length === 0 && (
				<EmptyState
					title={ __(
						'No sites match those filters',
						'modern-dashboard'
					) }
					description={ __(
						'Try clearing the search or widening the status filter.',
						'modern-dashboard'
					) }
				/>
			) }

			{ result && result.items.length > 0 && (
				<div
					className={
						loading
							? 'md-table-wrap is-refreshing'
							: 'md-table-wrap'
					}
				>
					<table className="md-table">
						<thead>
							<tr>
								{ COLUMNS.map( ( column ) => {
									const active = query.orderby === column.key;

									return (
										<th
											key={ column.key }
											className={
												column.numeric
													? 'md-numeric'
													: undefined
											}
											aria-sort={ ariaSort(
												active,
												query.order
											) }
										>
											{ column.sortable ? (
												<button
													type="button"
													className="md-sort"
													onClick={ () =>
														sortBy( column.key )
													}
												>
													{ column.label }
													<span
														aria-hidden="true"
														className="md-sort__arrow"
													>
														{ sortArrow(
															active,
															query.order
														) }
													</span>
												</button>
											) : (
												column.label
											) }
										</th>
									);
								} ) }
							</tr>
						</thead>
						<tbody>
							{ result.items.map( ( site ) => (
								<tr
									key={ site.blog_id }
									className={
										site.blog_id === selectedId
											? 'is-selected'
											: undefined
									}
								>
									<td>
										<button
											type="button"
											className="md-linkish md-site-name"
											onClick={ () =>
												onSelectSite( site.blog_id )
											}
										>
											{ site.name }
										</button>
										<span className="md-site-address">
											{ formatAddress(
												site.domain,
												site.path
											) }
										</span>
										<span className="md-site-flags">
											{ site.never_collected && (
												<Badge tone="muted">
													{ __(
														'Not collected',
														'modern-dashboard'
													) }
												</Badge>
											) }
											{ site.attention
												.filter(
													( reason ) =>
														reason !== 'updates'
												)
												.map( ( reason ) => {
													const meta = labels[
														reason
													] || {
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
									</td>
									<td className="md-numeric">
										{ formatNumber( site.totals.users ) }
									</td>
									<td className="md-numeric">
										{ formatNumber( site.totals.content ) }
									</td>
									<td className="md-numeric">
										{ site.totals.updates > 0 ? (
											<Badge tone="warn">
												{ formatNumber(
													site.totals.updates
												) }
											</Badge>
										) : (
											formatNumber( site.totals.updates )
										) }
									</td>
									<td className="md-numeric">
										{ formatBytes( site.totals.storage ) }
									</td>
									<td>
										{ formatRelative(
											site.content?.last_published
										) }
									</td>
									<td>
										{ formatRelative( site.collected_at ) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }

			{ result && result.pages > 1 && (
				<nav
					className="md-pagination"
					aria-label={ __( 'Site list pages', 'modern-dashboard' ) }
				>
					<button
						type="button"
						className="button"
						disabled={ result.page <= 1 }
						onClick={ () => setPage( result.page - 1 ) }
					>
						{ __( 'Previous', 'modern-dashboard' ) }
					</button>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages, 3: total sites */
							__(
								'Page %1$s of %2$s · %3$s sites',
								'modern-dashboard'
							),
							formatNumber( result.page ),
							formatNumber( result.pages ),
							formatNumber( result.total )
						) }
					</span>
					<button
						type="button"
						className="button"
						disabled={ result.page >= result.pages }
						onClick={ () => setPage( result.page + 1 ) }
					>
						{ __( 'Next', 'modern-dashboard' ) }
					</button>
				</nav>
			) }

			{ result && result.pages === 1 && result.items.length > 0 && (
				<p className="md-pagination__summary">
					{ sprintf(
						/* translators: %s: number of sites */
						_n(
							'%s site',
							'%s sites',
							result.total,
							'modern-dashboard'
						),
						formatNumber( result.total )
					) }
				</p>
			) }
		</div>
	);
}
