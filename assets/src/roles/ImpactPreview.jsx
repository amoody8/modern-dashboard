/**
 * What a save would actually do.
 *
 * Deliberately shown before saving rather than after: every other safeguard in
 * this feature helps somebody recover from a mistake, and this is the one that
 * stops them making it.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { Notice } from '../components/Primitives';
import { Panel } from '../components/Surfaces';

/**
 * @param {Object} props         Component props.
 * @param {Object} props.preview Impact payload from the server.
 *
 * @return {JSX.Element} The preview.
 */
export default function ImpactPreview( { preview } ) {
	const { changes, sites, partial, sitesCounted, enabling, disabling } =
		preview;

	return (
		<Panel title={ __( 'What this will change', 'modern-dashboard' ) }>
			{ enabling && (
				<Notice tone="warn">
					{ __(
						'This turns role editing on. Until now these rules have had no effect.',
						'modern-dashboard'
					) }
				</Notice>
			) }

			{ disabling && (
				<Notice tone="info">
					{ __(
						'This turns role editing off. Every site returns to its own roles.',
						'modern-dashboard'
					) }
				</Notice>
			) }

			{ 0 === changes.length && ! enabling && ! disabling && (
				<p className="md-impact__none">
					{ __( 'Nothing would change.', 'modern-dashboard' ) }
				</p>
			) }

			{ changes.map( ( change ) => (
				<div key={ change.role } className="md-impact">
					<div className="md-impact__who">
						<strong>{ change.label }</strong>
						<span>
							{ sprintf(
								/* translators: 1: number of people, 2: number of sites. */
								_n(
									'%1$d person across %2$d site',
									'%1$d people across %2$d sites',
									change.users,
									'modern-dashboard'
								),
								change.users,
								sites
							) }
						</span>
					</div>

					<ul className="md-impact__caps">
						{ change.revoke.map( ( cap ) => (
							<li
								key={ `r-${ cap }` }
								className="md-impact__cap md-impact__cap--revoke"
							>
								{ sprintf(
									/* translators: %s: capability name. */
									__( 'loses %s', 'modern-dashboard' ),
									cap
								) }
							</li>
						) ) }
						{ change.grant.map( ( cap ) => (
							<li
								key={ `g-${ cap }` }
								className="md-impact__cap md-impact__cap--grant"
							>
								{ sprintf(
									/* translators: %s: capability name. */
									__( 'gains %s', 'modern-dashboard' ),
									cap
								) }
							</li>
						) ) }
						{ change.restore.map( ( cap ) => (
							<li key={ `s-${ cap }` } className="md-impact__cap">
								{ sprintf(
									/* translators: %s: capability name. */
									__(
										'returns to normal: %s',
										'modern-dashboard'
									),
									cap
								) }
							</li>
						) ) }
					</ul>
				</div>
			) ) }

			{ partial && (
				<p className="md-impact__partial">
					{ sprintf(
						/* translators: 1: sites counted, 2: total sites. */
						__(
							'Counted %1$d of %2$d sites. The real number of people affected is higher.',
							'modern-dashboard'
						),
						sitesCounted,
						sites
					) }
				</p>
			) }
		</Panel>
	);
}
