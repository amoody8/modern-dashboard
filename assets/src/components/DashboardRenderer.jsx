/**
 * Renders a saved template.
 *
 * Shared by the live Overview tab and the builder's canvas, so what an editor
 * arranges is literally what a viewer gets — there is no second, diverging
 * "preview" implementation to drift out of sync.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Notice, Spinner } from './Primitives';
import { getRenderer } from '../blocks';

/**
 * @param {Object}   props              Component props.
 * @param {Object}   props.block        The block to render.
 * @param {Object}   props.overview     Network overview payload.
 * @param {Function} props.onSelectSite Called with a blog ID when a site is clicked.
 * @return {JSX.Element} The rendered block, or a placeholder if the type is unknown.
 */
export function BlockRenderer( { block, overview, onSelectSite } ) {
	const Renderer = getRenderer( block.type );

	if ( ! Renderer ) {
		return (
			<Notice tone="bad">
				{ sprintf(
					/* translators: %s: block type name */
					__(
						'No renderer is registered for the “%s” block.',
						'modern-dashboard'
					),
					block.type
				) }
			</Notice>
		);
	}

	return (
		<Renderer
			block={ block }
			overview={ overview }
			onSelectSite={ onSelectSite }
		/>
	);
}

export default function DashboardRenderer( {
	template,
	overview,
	loading,
	error,
	onSelectSite,
} ) {
	if ( error ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! template || ! overview ) {
		return (
			<Spinner
				label={ __( 'Reading the network…', 'modern-dashboard' ) }
			/>
		);
	}

	const blocks = template.blocks || [];

	if ( blocks.length === 0 ) {
		return (
			<Notice tone="info">
				{ __(
					'This dashboard has no blocks yet. Add some in the Builder tab.',
					'modern-dashboard'
				) }
			</Notice>
		);
	}

	return (
		<div className={ loading ? 'md-grid is-refreshing' : 'md-grid' }>
			{ blocks.map( ( block ) => (
				<div
					key={ block.id }
					className="md-grid__cell"
					style={ {
						'--md-span': block.width,
						'--md-span-sm': block.width >= 7 ? 2 : 1,
					} }
				>
					<BlockRenderer
						block={ block }
						overview={ overview }
						onSelectSite={ onSelectSite }
					/>
				</div>
			) ) }
		</div>
	);
}
