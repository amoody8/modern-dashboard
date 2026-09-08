/**
 * One top-level menu item in the editor.
 */

import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { __, sprintf } from '@wordpress/i18n';
import { ShowToggle } from '../components/Primitives';

export default function SortableMenuRow( {
	item,
	hidden,
	renamed,
	submenuRules,
	expanded,
	onToggleExpanded,
	onToggleHidden,
	onRename,
	onToggleChildHidden,
	onRenameChild,
} ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: item.slug } );

	const style = {
		transform: CSS.Translate.toString( transform ),
		transition,
	};

	const childHidden = submenuRules.hidden || [];
	const childRenamed = submenuRules.renamed || {};

	return (
		<li
			ref={ setNodeRef }
			style={ style }
			className={ `md-menu-row ${ isDragging ? 'is-dragging' : '' } ${
				hidden ? 'is-hidden' : ''
			}`.trim() }
		>
			<div className="md-menu-row__main">
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
							/* translators: %s: menu item name */
							__( 'Reorder %s', 'modern-dashboard' ),
							item.label
						) }
					</span>
				</button>

				<ShowToggle
					label={ sprintf(
						/* translators: %s: menu item name */
						__( 'Show %s', 'modern-dashboard' ),
						item.label
					) }
					checked={ ! hidden }
					onChange={ () => onToggleHidden( item.slug ) }
				/>

				<input
					type="text"
					className="md-menu-row__label"
					value={ renamed ?? '' }
					placeholder={ item.label }
					aria-label={ sprintf(
						/* translators: %s: menu item name */
						__( 'Rename %s', 'modern-dashboard' ),
						item.label
					) }
					onChange={ ( event ) =>
						onRename( item.slug, event.target.value )
					}
				/>

				<code className="md-menu-row__slug" title={ item.slug }>
					{ item.slug }
				</code>

				{ item.children.length > 0 && (
					<button
						type="button"
						className="md-linkish md-menu-row__expand"
						aria-expanded={ expanded }
						onClick={ () => onToggleExpanded( item.slug ) }
					>
						{ sprintf(
							/* translators: %s: number of submenu items */
							__( '%s submenu items', 'modern-dashboard' ),
							item.children.length
						) }
					</button>
				) }
			</div>

			{ expanded && item.children.length > 0 && (
				<ul className="md-menu-children">
					{ item.children.map( ( child ) => (
						<li key={ child.slug }>
							<ShowToggle
								label={ sprintf(
									/* translators: %s: submenu item name */
									__( 'Show %s', 'modern-dashboard' ),
									child.label
								) }
								checked={ ! childHidden.includes( child.slug ) }
								onChange={ () =>
									onToggleChildHidden( item.slug, child.slug )
								}
							/>

							<input
								type="text"
								className="md-menu-row__label"
								value={ childRenamed[ child.slug ] ?? '' }
								placeholder={ child.label }
								aria-label={ sprintf(
									/* translators: %s: submenu item name */
									__( 'Rename %s', 'modern-dashboard' ),
									child.label
								) }
								onChange={ ( event ) =>
									onRenameChild(
										item.slug,
										child.slug,
										event.target.value
									)
								}
							/>

							<code
								className="md-menu-row__slug"
								title={ child.slug }
							>
								{ child.slug }
							</code>
						</li>
					) ) }
				</ul>
			) }
		</li>
	);
}
