/**
 * The list of block types that can be added, grouped by category.
 */

import { __ } from '@wordpress/i18n';

const CATEGORY_LABELS = () => ( {
	data: __( 'Data', 'modern-dashboard' ),
	layout: __( 'Layout', 'modern-dashboard' ),
} );

export default function BlockPalette( { blocks, onAdd } ) {
	const labels = CATEGORY_LABELS();
	const grouped = {};

	blocks.forEach( ( block ) => {
		const category = block.category || 'data';

		grouped[ category ] = grouped[ category ] || [];
		grouped[ category ].push( block );
	} );

	return (
		<aside className="md-palette">
			<h2 className="md-palette__title">
				{ __( 'Add a block', 'modern-dashboard' ) }
			</h2>

			{ Object.entries( grouped ).map( ( [ category, items ] ) => (
				<div key={ category } className="md-palette__group">
					<h3>{ labels[ category ] || category }</h3>
					<ul>
						{ items.map( ( block ) => (
							<li key={ block.type }>
								<button
									type="button"
									className="md-palette__item"
									onClick={ () => onAdd( block.type ) }
								>
									<strong>{ block.label }</strong>
									{ block.description && (
										<span>{ block.description }</span>
									) }
								</button>
							</li>
						) ) }
					</ul>
				</div>
			) ) }
		</aside>
	);
}
