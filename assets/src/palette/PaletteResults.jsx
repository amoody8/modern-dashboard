/**
 * The palette's result list, grouped by section.
 */

import { __ } from '@wordpress/i18n';
import PaletteResult from './PaletteResult';

/**
 * @param {Object}   props          Component props.
 * @param {Object[]} props.results  Ordered, already merged results.
 * @param {Object}   props.groups   Group id => label, from the server.
 * @param {number}   props.selected Index of the active result.
 * @param {string}   props.listId   DOM id of the listbox.
 * @param {Function} props.onRun    Called when a result is chosen.
 * @param {Function} props.onSelect Called when a result becomes active.
 */
export default function PaletteResults( {
	results,
	groups,
	selected,
	listId,
	onRun,
	onSelect,
} ) {
	// Grouping preserves the merged order: a section appears where its
	// best-scoring member landed, so ranking still drives the layout.
	const sections = [];
	const indexOf = new Map();

	results.forEach( ( result, index ) => {
		const key = result.group || result.type;

		if ( ! indexOf.has( key ) ) {
			indexOf.set( key, sections.length );
			sections.push( { key, label: labelFor( key, groups ), items: [] } );
		}

		sections[ indexOf.get( key ) ].items.push( { result, index } );
	} );

	return (
		<div className="md-palette__results" role="listbox" id={ listId }>
			{ sections.map( ( section ) => (
				<div
					key={ section.key }
					role="group"
					aria-label={ section.label }
					className="md-palette__group"
				>
					<div className="md-palette__group-label" aria-hidden="true">
						{ section.label }
					</div>
					{ section.items.map( ( { result, index } ) => (
						<PaletteResult
							key={ `${ result.type }-${ result.blogId ?? 0 }-${
								result.id
							}` }
							id={ `${ listId }-option-${ index }` }
							result={ result }
							selected={ index === selected }
							onRun={ onRun }
							onHover={ () => onSelect( index ) }
						/>
					) ) }
				</div>
			) ) }
		</div>
	);
}

/**
 * @param {string} key    Group id.
 * @param {Object} groups Group labels from the server.
 *
 * @return {string} Display label.
 */
function labelFor( key, groups ) {
	if ( groups && groups[ key ] ) {
		return groups[ key ];
	}

	switch ( key ) {
		case 'post':
			return __( 'Content', 'modern-dashboard' );
		case 'site':
			return __( 'Sites', 'modern-dashboard' );
		case 'user':
			return __( 'Users', 'modern-dashboard' );
		default:
			return __( 'Results', 'modern-dashboard' );
	}
}
