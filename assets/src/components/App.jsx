/**
 * Dashboard shell: tabs, global refresh, and the site drill-down.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Overview from './Overview';
import SettingsPanel from './SettingsPanel';
import SiteDetail from './SiteDetail';
import SitesTable from './SitesTable';
import { Notice } from './Primitives';
import { api, config } from '../lib/api';

const TABS = () => [
	{ key: 'overview', label: __( 'Overview', 'modern-dashboard' ) },
	{ key: 'sites', label: __( 'Sites', 'modern-dashboard' ) },
	{ key: 'settings', label: __( 'Settings', 'modern-dashboard' ) },
];

export default function App() {
	const canManage = Boolean( config.canManage );

	const [ tab, setTab ] = useState( 'overview' );
	const [ overview, setOverview ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ selectedId, setSelectedId ] = useState( null );
	const [ refreshToken, setRefreshToken ] = useState( 0 );
	const [ collecting, setCollecting ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const [ visibleCards, setVisibleCards ] = useState(
		config.settings?.visible_cards || config.cards || []
	);

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

	useEffect( () => {
		loadOverview();
	}, [ loadOverview, refreshToken ] );

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
					.filter( ( item ) => item.key !== 'settings' || canManage )
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
							onClick={ () => setTab( item.key ) }
						>
							{ item.label }
						</button>
					) ) }
			</nav>

			<div className="md-body">
				<div className="md-body__main">
					{ tab === 'overview' && (
						<Overview
							overview={ overview }
							loading={ loading }
							error={ error }
							visibleCards={ visibleCards }
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

					{ tab === 'settings' && canManage && (
						<SettingsPanel
							onSaved={ ( settings ) => {
								setVisibleCards( settings.visible_cards );
								setRefreshToken( ( token ) => token + 1 );
							} }
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
