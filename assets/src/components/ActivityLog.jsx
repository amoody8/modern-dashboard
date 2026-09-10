/**
 * The activity log screen.
 *
 * Built on the shared surfaces rather than by copying SitesTable, which
 * predates them and hand-rolls its own toolbar, states and pagination.
 *
 * One thing this screen does that the others do not: it shows *exact* times.
 * Metrics come from a cache that is minutes old, so `formatRelative` is
 * deliberately coarse there. An audit entry records the moment something
 * happened, so seconds are meaningful and the absolute time sits behind a
 * `<time>` title for anyone who needs to correlate with another system.
 */

import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../lib/api';
import { formatDate, formatExactRelative } from '../lib/format';
import { Badge, EmptyState, Field, Notice, Spinner } from './Primitives';
import DataTable from './DataTable';
import { Panel, PageHeader, SegmentedControl, Toolbar } from './Surfaces';

/** Ranges offered as presets. `0` means "everything". */
const RANGES = () => [
	{ value: '1', label: __( '24 hours', 'modern-dashboard' ) },
	{ value: '7', label: __( '7 days', 'modern-dashboard' ) },
	{ value: '30', label: __( '30 days', 'modern-dashboard' ) },
	{ value: '0', label: __( 'All', 'modern-dashboard' ) },
];

/** Event groups, matching the `group` filter the endpoint accepts. */
const GROUPS = () => [
	{ value: '', label: __( 'Everything', 'modern-dashboard' ) },
	{ value: 'post', label: __( 'Content', 'modern-dashboard' ) },
	{ value: 'user', label: __( 'People', 'modern-dashboard' ) },
	{ value: 'plugin', label: __( 'Plugins', 'modern-dashboard' ) },
	{ value: 'theme', label: __( 'Themes', 'modern-dashboard' ) },
	{ value: 'site', label: __( 'Sites', 'modern-dashboard' ) },
];

/** Tone per group, so a row's nature reads before its text does. */
const GROUP_TONE = {
	post: 'info',
	user: 'muted',
	plugin: 'warn',
	theme: 'muted',
	site: 'good',
};

/**
 * Actions that describe something being removed. Worth marking, because
 * "deleted" is the entry people scan for when something has gone missing.
 */
const DESTRUCTIVE = [
	'post.deleted',
	'post.trashed',
	'user.deleted',
	'site.deleted',
];

export default function ActivityLog() {
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );

	const [ query, setQuery ] = useState( {
		group: '',
		range: '7',
		search: '',
		page: 1,
		per_page: 50,
	} );

	const requestId = useRef( 0 );

	// Debounced into the query, matching the idiom every other search box in
	// this plugin uses.
	useEffect( () => {
		const timer = setTimeout( () => {
			setQuery( ( current ) =>
				current.search === search
					? current
					: { ...current, search, page: 1 }
			);
		}, 250 );

		return () => clearTimeout( timer );
	}, [ search ] );

	useEffect( () => {
		let active = true;
		const id = ++requestId.current;

		setLoading( true );

		const since =
			'0' === query.range
				? 0
				: Math.floor( Date.now() / 1000 ) -
				  Number( query.range ) * 86400;

		api.auditLog( {
			group: query.group,
			search: query.search,
			since: since || '',
			page: query.page,
			per_page: query.per_page,
		} )
			.then( ( data ) => {
				if ( active && id === requestId.current ) {
					setResult( data );
					setError( null );
				}
			} )
			.catch( ( err ) => {
				if ( active && id === requestId.current ) {
					setError(
						err.message ||
							__( 'Could not load activity.', 'modern-dashboard' )
					);
				}
			} )
			.finally( () => {
				if ( active && id === requestId.current ) {
					setLoading( false );
				}
			} );

		return () => {
			active = false;
		};
	}, [ query ] );

	const setFilter = useCallback( ( key, value ) => {
		setQuery( ( current ) => ( { ...current, [ key ]: value, page: 1 } ) );
	}, [] );

	const columns = useMemo(
		() => [
			{
				key: 'recordedAt',
				label: __( 'When', 'modern-dashboard' ),
				sortable: false,
				width: '150px',
				render: ( row ) => (
					<time
						dateTime={ new Date(
							row.recordedAt * 1000
						).toISOString() }
						title={ formatDate( row.recordedAt ) }
					>
						{ formatExactRelative( row.recordedAt ) }
					</time>
				),
			},
			{
				key: 'action',
				label: __( 'Event', 'modern-dashboard' ),
				sortable: false,
				width: '170px',
				render: ( row ) => (
					<Badge
						tone={
							DESTRUCTIVE.includes( row.action )
								? 'bad'
								: GROUP_TONE[ row.group ] || 'muted'
						}
					>
						{ row.action }
					</Badge>
				),
			},
			{
				key: 'objectName',
				label: __( 'What', 'modern-dashboard' ),
				sortable: false,
				render: ( row ) => (
					<span className="md-activity__what">
						<span className="md-activity__object">
							{ row.objectName || '—' }
						</span>
						{ describeContext( row ) && (
							<span className="md-activity__context">
								{ describeContext( row ) }
							</span>
						) }
					</span>
				),
			},
			{
				key: 'userLogin',
				label: __( 'Who', 'modern-dashboard' ),
				sortable: false,
				width: '150px',
				render: ( row ) =>
					row.userLogin || __( 'System', 'modern-dashboard' ),
			},
			{
				key: 'siteName',
				label: __( 'Site', 'modern-dashboard' ),
				sortable: false,
				width: '160px',
				render: ( row ) => row.siteName || `#${ row.blogId }`,
			},
		],
		[]
	);

	if ( error && ! result ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	const items = result?.items || [];
	const disabled = result && false === result.enabled;

	return (
		<div className="md-activity">
			<PageHeader
				title={ __( 'Activity', 'modern-dashboard' ) }
				subtitle={ __(
					'Administrative changes across every site in the network.',
					'modern-dashboard'
				) }
			/>

			{ disabled && (
				<Notice tone="info">
					{ __(
						'Activity logging is switched off, so this shows only what was recorded before. Turn it on under Settings.',
						'modern-dashboard'
					) }
				</Notice>
			) }

			<Panel flush>
				<Toolbar
					trailing={
						result
							? sprintf(
									/* translators: %d: number of matching events. */
									_n(
										'%d event',
										'%d events',
										result.total,
										'modern-dashboard'
									),
									result.total
							  )
							: null
					}
				>
					<Field
						label={ __( 'Search activity', 'modern-dashboard' ) }
						hideLabel
						className="md-field--grow"
					>
						{ ( id ) => (
							<input
								id={ id }
								type="search"
								className="md-search"
								placeholder={ __(
									'Search by name or user…',
									'modern-dashboard'
								) }
								value={ search }
								onChange={ ( event ) =>
									setSearch( event.target.value )
								}
							/>
						) }
					</Field>

					<Field
						label={ __( 'Event type', 'modern-dashboard' ) }
						hideLabel
					>
						{ ( id ) => (
							<select
								id={ id }
								value={ query.group }
								onChange={ ( event ) =>
									setFilter( 'group', event.target.value )
								}
							>
								{ GROUPS().map( ( option ) => (
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

					<SegmentedControl
						label={ __( 'Time range', 'modern-dashboard' ) }
						options={ RANGES() }
						value={ query.range }
						onChange={ ( value ) => setFilter( 'range', value ) }
					/>
				</Toolbar>

				{ loading && ! result && (
					<Spinner
						label={ __( 'Loading activity…', 'modern-dashboard' ) }
					/>
				) }

				{ result && 0 === items.length && (
					<EmptyState
						title={ __(
							'Nothing recorded yet',
							'modern-dashboard'
						) }
						description={ __(
							'Administrative changes will appear here as they happen.',
							'modern-dashboard'
						) }
					/>
				) }

				{ items.length > 0 && (
					<div className={ loading ? 'is-refreshing' : undefined }>
						<DataTable
							columns={ columns }
							rows={ items }
							rowKey={ ( row ) => row.id }
							caption={ __(
								'Network activity',
								'modern-dashboard'
							) }
						/>
					</div>
				) }
			</Panel>

			{ result && result.pages > 1 && (
				<nav className="md-pagination">
					<button
						type="button"
						className="button"
						disabled={ query.page <= 1 }
						onClick={ () =>
							setQuery( ( c ) => ( { ...c, page: c.page - 1 } ) )
						}
					>
						{ __( 'Previous', 'modern-dashboard' ) }
					</button>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages. */
							__( 'Page %1$d of %2$d', 'modern-dashboard' ),
							result.page,
							result.pages
						) }
					</span>
					<button
						type="button"
						className="button"
						disabled={ query.page >= result.pages }
						onClick={ () =>
							setQuery( ( c ) => ( { ...c, page: c.page + 1 } ) )
						}
					>
						{ __( 'Next', 'modern-dashboard' ) }
					</button>
				</nav>
			) }
		</div>
	);
}

/**
 * A one-line summary of an entry's context, when it has one worth showing.
 *
 * Kept deliberately short: the table is for scanning, and an entry whose detail
 * matters is better read in full than crammed into a cell.
 *
 * @param {Object} row Log entry.
 *
 * @return {string} Summary, or an empty string.
 */
function describeContext( row ) {
	const context = row.context || {};

	if ( context.from && context.to ) {
		/* translators: 1: previous value, 2: new value. */
		const label = __( '%1$s → %2$s', 'modern-dashboard' );

		return sprintf( label, context.from, context.to );
	}

	if ( context.file ) {
		return String( context.file );
	}

	return '';
}
