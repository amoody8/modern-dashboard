/**
 * One block on the builder canvas.
 *
 * Drag listeners live on the handle, not the whole cell: the block preview
 * underneath contains real buttons and links, and a drag surface over the top of
 * them would swallow every click.
 */

import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { __, sprintf } from '@wordpress/i18n';
import { BlockRenderer } from '../components/DashboardRenderer';

export default function SortableBlock( {
	block,
	definition,
	overview,
	selected,
	onSelect,
	columns,
} ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: block.id } );

	const style = {
		'--md-span': block.width,
		'--md-span-sm': block.width >= 7 ? 2 : 1,
		transform: CSS.Translate.toString( transform ),
		transition,
	};

	const className = [
		'md-grid__cell',
		'md-canvas__cell',
		selected ? 'is-selected' : '',
		isDragging ? 'is-dragging' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	const label = definition?.label || block.type;

	return (
		<div ref={ setNodeRef } className={ className } style={ style }>
			<div className="md-canvas__chrome">
				<button
					type="button"
					className="md-canvas__handle"
					ref={ setActivatorNodeRef }
					{ ...attributes }
					{ ...listeners }
				>
					<span aria-hidden="true">⠿</span>
					<span className="screen-reader-text">
						{ sprintf(
							/* translators: %s: block name */
							__( 'Reorder %s', 'modern-dashboard' ),
							label
						) }
					</span>
				</button>

				<button
					type="button"
					className="md-canvas__select"
					aria-pressed={ selected }
					onClick={ () => onSelect( block.id ) }
				>
					{ label }
					<span className="md-canvas__width">
						{ sprintf(
							/* translators: 1: column span, 2: total columns */
							__( '%1$s/%2$s', 'modern-dashboard' ),
							block.width,
							columns
						) }
					</span>
				</button>
			</div>

			{ /* Pointer events are disabled on the preview in CSS: on the canvas a
			     block is something you arrange, not something you operate. */ }
			<div className="md-canvas__preview">
				<BlockRenderer block={ block } overview={ overview } />
			</div>
		</div>
	);
}
