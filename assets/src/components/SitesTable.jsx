/**
 * Sortable, filterable list of every site in the network.
 */

import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge, EmptyState, Field, Notice, Spinner } from './Primitives';
import DataTable from './DataTable';
import { api } from '../lib/api';
import { attentionLabels } from '../lib/labels';
import {
	formatAddress,
	formatBytes,
	formatNumber,
	formatRelative,
} from '../lib/format';

/**
 * Columns are fully declarative so DataTable can draw them: the renderer for a
 * cell lives beside the column it belongs to, rather than in a parallel block
 * of markup that has to be kept in the same order.
 *
 * @param {Object}   labels       Attention-reason labels.
 * @param {Function} onSelectSite Called with a blog ID.
 *
 * @return {Object[]} Column definitions.
 */
const columnsFor = ( labels, onSelectSite ) => [
	{
		key: 'name',
		label: __( 'Site', 'modern-dashboard' ),
		render: ( site ) => (
			<>
				<button
					type="button"
					className="md-linkish md-site-name"
					onClick={ () => onSelectSite( site.blog_id ) }
				>
					{ site.name }
				</button>
				<span className="md-site-address">
					{ formatAddress( site.domain, site.path ) }
				</span>
				<span className="md-site-flags">
					{ site.never_collected && (
						<Badge tone="muted">
							{ __( 'Not collected', 'modern-dashboard' ) }
						</Badge>
					) }
					{ site.attention
						.filter( ( reason ) => reason !== 'updates' )
						.map( ( reason ) => {
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
			</>
		),
	},
	{
		key: 'users',
		label: __( 'Users', 'modern-dashboard' ),
		align: 'end',
		numeric: true,
		render: ( site ) => formatNumber( site.totals.users ),
	},
	{
		key: 'content',
		label: __( 'Content', 'modern-dashboard' ),
		align: 'end',
		numeric: true,
		render: ( site ) => formatNumber( site.totals.content ),
	},
	{
		key: 'updates',
		label: __( 'Updates', 'modern-dashboard' ),
		align: 'end',
		numeric: true,
		render: ( site ) =>
			site.totals.updates > 0 ? (
				<Badge tone="warn">
					{ formatNumber( site.totals.updates ) }
				</Badge>
			) : (
				formatNumber( site.totals.updates )
			),
	},
	{
		key: 'storage',
		label: __( 'Uploads', 'modern-dashboard' ),
		align: 'end',
		numeric: true,
		render: ( site ) => formatBytes( site.totals.storage ),
	},
	{
		key: 'last_published',
		label: __( 'Last post', 'modern-dashboard' ),
		render: ( site ) => formatRelative( site.content?.last_published ),
	},
	{
		key: 'collected_at',
		label: __( 'Collected', 'modern-dashboard' ),
		render: ( site ) => formatRelative( site.collected_at ),
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

	const labels = attentionLabels();

	// Above the error guard: a hook after an early return is skipped on the
	// render that takes it, which changes hook order between renders.
	const columns = useMemo(
		() => columnsFor( labels, onSelectSite ),
		[ labels, onSelectSite ]
	);

	if ( error ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

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
				<div className={ loading ? 'is-refreshing' : undefined }>
					<DataTable
						columns={ columns }
						rows={ result.items }
						rowKey={ ( site ) => site.blog_id }
						orderby={ query.orderby }
						order={ query.order }
						onSort={ sortBy }
						isSelected={ ( site ) => site.blog_id === selectedId }
						caption={ __(
							'Sites in this network',
							'modern-dashboard'
						) }
					/>
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
