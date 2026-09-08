/**
 * Environment facts, freshness, and the plain layout blocks.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { formatNumber, formatRelative } from '../lib/format';

export function EnvironmentBlock( { block, overview } ) {
	const settings = block.settings || {};
	const environment = overview?.environment || {};

	return (
		<section className="md-panel md-panel--flush">
			<header className="md-panel__header">
				<h2>
					{ settings.title || __( 'Network', 'modern-dashboard' ) }
				</h2>
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
							: __( 'Network options', 'modern-dashboard' ) }
					</dd>
				</div>
			</dl>
		</section>
	);
}

export function FreshnessBlock( { overview } ) {
	const freshness = overview?.freshness || {};
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

	return (
		<div className="md-freshness-block">
			<span className="md-stat__label">
				{ __( 'Data freshness', 'modern-dashboard' ) }
			</span>
			<p>{ parts.join( ' · ' ) }</p>
		</div>
	);
}

export function HeadingBlock( { block } ) {
	const settings = block.settings || {};
	const text = settings.text || __( 'Section', 'modern-dashboard' );

	// The inspector offers two sizes rather than arbitrary levels, so the
	// document outline stays predictable however the dashboard is arranged.
	return Number( settings.level ) === 3 ? (
		<h3 className="md-block-heading md-block-heading--small">{ text }</h3>
	) : (
		<h2 className="md-block-heading">{ text }</h2>
	);
}

export function TextBlock( { block } ) {
	const settings = block.settings || {};

	if ( ! settings.text ) {
		return null;
	}

	return <p className="md-block-text">{ settings.text }</p>;
}

export function SpacerBlock() {
	return <div className="md-block-spacer" aria-hidden="true" />;
}
