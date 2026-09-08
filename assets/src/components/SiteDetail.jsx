/**
 * Drill-down panel for one site.
 */

import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Badge, Notice, Spinner } from './Primitives';
import { api } from '../lib/api';
import { attentionLabels } from '../lib/labels';
import {
	formatAddress,
	formatBytes,
	formatNumber,
	formatRelative,
} from '../lib/format';

function Row( { label, children } ) {
	return (
		<div className="md-detail__row">
			<dt>{ label }</dt>
			<dd>{ children }</dd>
		</div>
	);
}

export default function SiteDetail( {
	blogId,
	canManage,
	onClose,
	onRefreshed,
} ) {
	const [ site, setSite ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let active = true;

		setLoading( true );
		setSite( null );
		setError( null );

		api.site( blogId )
			.then( ( data ) => active && setSite( data ) )
			.catch( ( err ) => {
				if ( ! active ) {
					return;
				}

				// A 404 here means "never collected", which is a normal state for
				// a brand new site rather than a failure.
				setError(
					err.code === 'modern_dashboard_not_collected'
						? __(
								'This site has not been collected yet. Refresh it to gather its metrics.',
								'modern-dashboard'
						  )
						: err.message ||
								__(
									'Could not load this site.',
									'modern-dashboard'
								)
				);
			} )
			.finally( () => active && setLoading( false ) );

		return () => {
			active = false;
		};
	}, [ blogId ] );

	useEffect( () => {
		const onKey = ( event ) => event.key === 'Escape' && onClose();

		document.addEventListener( 'keydown', onKey );

		return () => document.removeEventListener( 'keydown', onKey );
	}, [ onClose ] );

	const refresh = () => {
		setRefreshing( true );
		setError( null );

		api.refreshSite( blogId )
			.then( ( data ) => {
				setSite( data );
				onRefreshed();
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__( 'Refreshing that site failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setRefreshing( false ) );
	};

	const labels = attentionLabels();

	return (
		<aside
			className="md-detail"
			aria-label={ __( 'Site details', 'modern-dashboard' ) }
		>
			<header className="md-detail__header">
				<div>
					<h2>
						{ site
							? site.name
							: sprintf(
									/* translators: %s: site ID */ __(
										'Site %s',
										'modern-dashboard'
									),
									blogId
							  ) }
					</h2>
					{ site && (
						<a
							href={ site.url }
							className="md-detail__url"
							target="_blank"
							rel="noreferrer"
						>
							{ formatAddress( site.domain, site.path ) }
						</a>
					) }
				</div>
				<button
					type="button"
					className="md-detail__close"
					onClick={ onClose }
				>
					<span className="screen-reader-text">
						{ __( 'Close', 'modern-dashboard' ) }
					</span>
					<span aria-hidden="true">×</span>
				</button>
			</header>

			{ error && (
				<Notice tone={ site ? 'bad' : 'info' }>{ error }</Notice>
			) }
			{ loading && <Spinner /> }

			{ site && (
				<>
					{ site.attention.length > 0 && (
						<p className="md-detail__badges">
							{ site.attention.map( ( reason ) => {
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
						</p>
					) }

					<dl className="md-detail__list">
						<Row label={ __( 'Users', 'modern-dashboard' ) }>
							{ formatNumber( site.users?.total ) }
							{ site.users?.roles && (
								<span className="md-detail__sub">
									{ Object.entries( site.users.roles )
										.slice( 0, 4 )
										.map(
											( [ role, count ] ) =>
												`${ role }: ${ formatNumber(
													count
												) }`
										)
										.join( ' · ' ) }
								</span>
							) }
						</Row>

						<Row label={ __( 'Content', 'modern-dashboard' ) }>
							{ sprintf(
								/* translators: 1: posts, 2: pages, 3: media items */
								__(
									'%1$s posts · %2$s pages · %3$s media',
									'modern-dashboard'
								),
								formatNumber( site.content?.posts ),
								formatNumber( site.content?.pages ),
								formatNumber( site.content?.media )
							) }
						</Row>

						<Row label={ __( 'Comments', 'modern-dashboard' ) }>
							{ sprintf(
								/* translators: 1: approved comments, 2: comments awaiting moderation, 3: spam comments */
								__(
									'%1$s approved · %2$s pending · %3$s spam',
									'modern-dashboard'
								),
								formatNumber( site.content?.comments ),
								formatNumber( site.content?.comments_pending ),
								formatNumber( site.content?.comments_spam )
							) }
						</Row>

						<Row
							label={ __( 'Last published', 'modern-dashboard' ) }
						>
							{ formatRelative( site.content?.last_published ) }
						</Row>

						<Row label={ __( 'Theme', 'modern-dashboard' ) }>
							{ site.updates?.theme?.name }{ ' ' }
							<span className="md-detail__sub">
								{ site.updates?.theme?.version }
							</span>
							{ site.updates?.theme?.update_available && (
								<Badge tone="warn">
									{ sprintf(
										/* translators: %s: version number */
										__(
											'%s available',
											'modern-dashboard'
										),
										site.updates.theme.update_version
									) }
								</Badge>
							) }
						</Row>

						<Row label={ __( 'Plugins', 'modern-dashboard' ) }>
							{ sprintf(
								/* translators: %s: number of active plugins */
								__( '%s active', 'modern-dashboard' ),
								formatNumber( site.updates?.active_plugins )
							) }
							{ site.updates?.outdated_plugins?.length > 0 && (
								<ul className="md-detail__plugins">
									{ site.updates.outdated_plugins.map(
										( plugin ) => (
											<li key={ plugin.file }>
												{ plugin.name }
												<span className="md-detail__sub">
													{ plugin.current } →{ ' ' }
													{ plugin.new }
												</span>
											</li>
										)
									) }
								</ul>
							) }
						</Row>

						<Row label={ __( 'Uploads', 'modern-dashboard' ) }>
							{ site.storage?.skipped
								? __(
										'Not scanned — storage collection is off',
										'modern-dashboard'
								  )
								: formatBytes( site.storage?.bytes ) }
							{ site.storage?.partial && (
								<span className="md-detail__sub">
									{ __(
										'Scan timed out before finishing',
										'modern-dashboard'
									) }
								</span>
							) }
						</Row>

						<Row label={ __( 'Registered', 'modern-dashboard' ) }>
							{ formatRelative( site.registered ) }
						</Row>

						<Row label={ __( 'Collected', 'modern-dashboard' ) }>
							{ formatRelative( site.collected_at ) }
							<span className="md-detail__sub">
								{ sprintf(
									/* translators: %s: duration in milliseconds */
									__( 'took %sms', 'modern-dashboard' ),
									formatNumber( site.collection_ms )
								) }
							</span>
						</Row>
					</dl>

					{ site.errors?.length > 0 && (
						<Notice tone="bad">
							<strong>
								{ __(
									'Collection problems',
									'modern-dashboard'
								) }
							</strong>
							<ul>
								{ site.errors.map( ( item ) => (
									<li key={ item.collector }>
										{ item.collector }: { item.message }
									</li>
								) ) }
							</ul>
						</Notice>
					) }

					<footer className="md-detail__footer">
						<a
							className="button"
							href={ `${ site.url }/wp-admin/` }
						>
							{ __( 'Open site admin', 'modern-dashboard' ) }
						</a>
						{ canManage && (
							<button
								type="button"
								className="button button-primary"
								onClick={ refresh }
								disabled={ refreshing }
							>
								{ refreshing
									? __( 'Refreshing…', 'modern-dashboard' )
									: __( 'Refresh now', 'modern-dashboard' ) }
							</button>
						) }
					</footer>
				</>
			) }

			{ ! site && ! loading && canManage && (
				<footer className="md-detail__footer">
					<button
						type="button"
						className="button button-primary"
						onClick={ refresh }
						disabled={ refreshing }
					>
						{ refreshing
							? __( 'Collecting…', 'modern-dashboard' )
							: __(
									'Collect this site now',
									'modern-dashboard'
							  ) }
					</button>
				</footer>
			) }
		</aside>
	);
}
