/**
 * Dashboard shell: tabs, global refresh, and the site drill-down.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Builder from '../builder/Builder';
import MenuEditor from '../menu/MenuEditor';
import ThemeEditor from '../theme/ThemeEditor';
import DashboardRenderer from './DashboardRenderer';
import SettingsPanel from './SettingsPanel';
import SiteDetail from './SiteDetail';
import SitesTable from './SitesTable';
import { Notice } from './Primitives';
import { api, config } from '../lib/api';
import { clearSitesCache } from '../lib/useSites';

const TABS = () => [
	{
		key: 'overview',
		label: __( 'Overview', 'modern-dashboard' ),
		manage: false,
	},
	{ key: 'sites', label: __( 'Sites', 'modern-dashboard' ), manage: false },
	{
		key: 'builder',
		label: __( 'Builder', 'modern-dashboard' ),
		manage: true,
	},
	{
		key: 'menus',
		label: __( 'Menus', 'modern-dashboard' ),
		manage: true,
	},
	{
		key: 'branding',
		label: __( 'Branding', 'modern-dashboard' ),
		manage: true,
	},
	{
		key: 'settings',
		label: __( 'Settings', 'modern-dashboard' ),
		manage: true,
	},
];

/**
 * The tab named by the URL fragment, when it is one we actually have.
 *
 * @return {string} Tab key.
 */
function tabFromHash() {
	const key = window.location.hash.replace( /^#/, '' );

	return TABS().some( ( item ) => item.key === key ) ? key : 'overview';
}

export default function App() {
	const canManage = Boolean( config.canManage );

	const [ tab, setTab ] = useState( tabFromHash );
	const [ overview, setOverview ] = useState( null );
	const [ template, setTemplate ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ selectedId, setSelectedId ] = useState( null );
	const [ refreshToken, setRefreshToken ] = useState( 0 );
	const [ collecting, setCollecting ] = useState( false );
	const [ message, setMessage ] = useState( null );

	// The palette navigates here with a #tab fragment, and reflecting the tab in
	// the URL also makes these screens linkable and the back button work.
	useEffect( () => {
		const onHashChange = () => setTab( tabFromHash() );

		window.addEventListener( 'hashchange', onHashChange );

		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	const selectTab = useCallback( ( key ) => {
		setTab( key );

		if ( window.location.hash !== `#${ key }` ) {
			window.history.replaceState( null, '', `#${ key }` );
		}
	}, [] );

	const loadOverview = useCallback( ( fresh = false ) => {
		setLoading( true );

		return api
			.overview( fresh )
			.then( ( data ) => {
				setOverview( data );
				setError( null );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__(
							'Could not load the network overview.',
							'modern-dashboard'
						)
				)
			)
			.finally( () => setLoading( false ) );
	}, [] );

	const loadTemplate = useCallback( () => {
		return api
			.activeTemplate()
			.then( setTemplate )
			.catch( ( err ) =>
				setError(
					err.message ||
						__(
							'Could not load the dashboard layout.',
							'modern-dashboard'
						)
				)
			);
	}, [] );

	useEffect( () => {
		loadOverview();
	}, [ loadOverview, refreshToken ] );

	useEffect( () => {
		loadTemplate();
	}, [ loadTemplate ] );

	// Blocks cache their slice of the sites list, so a collection run has to
	// invalidate that too or the numbers on the dashboard stay stale.
	useEffect( () => {
		if ( refreshToken > 0 ) {
			clearSitesCache();
		}
	}, [ refreshToken ] );

	const collectNow = () => {
		setCollecting( true );
		setMessage( null );

		api.refreshBatch()
			.then( ( data ) => {
				setMessage(
					data.count === 0
						? __(
								'Nothing collected — every site is already up to date, or another batch is still running.',
								'modern-dashboard'
						  )
						: sprintf(
								/* translators: %s: number of sites */
								_n(
									'Collected %s site.',
									'Collected %s sites.',
									data.count,
									'modern-dashboard'
								),
								data.count
						  )
				);
				setRefreshToken( ( token ) => token + 1 );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__(
							'Running a collection batch failed.',
							'modern-dashboard'
						)
				)
			)
			.finally( () => setCollecting( false ) );
	};

	const onSiteRefreshed = useCallback( () => {
		clearSitesCache();
		setRefreshToken( ( token ) => token + 1 );
	}, [] );

	return (
		<div className="md-app">
			<header className="md-header">
				<div>
					<h1>{ __( 'Network Dashboard', 'modern-dashboard' ) }</h1>
					<p className="md-header__sub">
						{ overview?.environment?.network_name ||
							__( 'WordPress Multisite', 'modern-dashboard' ) }
					</p>
				</div>

				{ canManage && (
					<div className="md-header__actions">
						<button
							type="button"
							className="button"
							onClick={ () => loadOverview( true ) }
							disabled={ loading }
						>
							{ __( 'Reload', 'modern-dashboard' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ collectNow }
							disabled={ collecting }
						>
							{ collecting
								? __( 'Collecting…', 'modern-dashboard' )
								: __(
										'Collect a batch now',
										'modern-dashboard'
								  ) }
						</button>
					</div>
				) }
			</header>

			{ message && (
				<Notice tone="good" onDismiss={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }

			<nav
				className="md-tabs"
				aria-label={ __( 'Dashboard sections', 'modern-dashboard' ) }
			>
				{ TABS()
					.filter( ( item ) => ! item.manage || canManage )
					.map( ( item ) => (
						<button
							key={ item.key }
							type="button"
							className={
								tab === item.key ? 'md-tab is-active' : 'md-tab'
							}
							aria-current={
								tab === item.key ? 'page' : undefined
							}
							onClick={ () => selectTab( item.key ) }
						>
							{ item.label }
						</button>
					) ) }
			</nav>

			<div className="md-body">
				<div className="md-body__main">
					{ tab === 'overview' && (
						<DashboardRenderer
							template={ template }
							overview={ overview }
							loading={ loading }
							error={ error }
							onSelectSite={ setSelectedId }
						/>
					) }

					{ tab === 'sites' && (
						<SitesTable
							onSelectSite={ setSelectedId }
							selectedId={ selectedId }
							refreshToken={ refreshToken }
						/>
					) }

					{ tab === 'builder' && canManage && (
						<Builder overview={ overview } />
					) }

					{ tab === 'menus' && canManage && <MenuEditor /> }

					{ tab === 'branding' && canManage && <ThemeEditor /> }

					{ tab === 'settings' && canManage && (
						<SettingsPanel
							onSaved={ () =>
								setRefreshToken( ( token ) => token + 1 )
							}
						/>
					) }
				</div>

				{ selectedId && (
					<SiteDetail
						blogId={ selectedId }
						canManage={ canManage }
						onClose={ () => setSelectedId( null ) }
						onRefreshed={ onSiteRefreshed }
					/>
				) }
			</div>
		</div>
	);
}
