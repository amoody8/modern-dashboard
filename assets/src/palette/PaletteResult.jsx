/**
 * One row in the palette's result list.
 */

import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   props          Component props.
 * @param {Object}   props.result   The result to render.
 * @param {boolean}  props.selected Whether this row is the active option.
 * @param {string}   props.id       DOM id, referenced by aria-activedescendant.
 * @param {Function} props.onRun    Called when the row is chosen.
 * @param {Function} props.onHover  Called when the pointer moves onto the row.
 */
export default function PaletteResult( {
	result,
	selected,
	id,
	onRun,
	onHover,
} ) {
	const meta = [];

	if ( result.siteName ) {
		meta.push( result.siteName );
	}

	if ( result.sub && result.sub !== result.siteName ) {
		meta.push( result.sub );
	}

	return (
		/*
		 * A combobox keeps DOM focus in the input and tracks the active option
		 * with aria-activedescendant — that is what lets a screen reader
		 * announce results while the user keeps typing. Arrow keys and Enter are
		 * handled on the dialog, so these rows are deliberately not focusable
		 * and carry no key handler of their own.
		 */
		/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/interactive-supports-focus */
		<div
			id={ id }
			role="option"
			aria-selected={ selected }
			className={ `md-palette__result${
				selected ? ' is-selected' : ''
			}` }
			onClick={ () => onRun( result ) }
			onMouseMove={ onHover }
		>
			<span
				className={ `md-palette__icon dashicons dashicons-${
					result.icon || iconFor( result.type )
				}` }
				aria-hidden="true"
			/>
			<span className="md-palette__label">
				<span className="md-palette__title">
					{ result.title || result.label }
				</span>
				{ meta.length > 0 && (
					<span className="md-palette__meta">
						{ meta.join( ' · ' ) }
					</span>
				) }
			</span>
			{ 'draft' === result.status && (
				<span className="md-palette__badge">
					{ __( 'Draft', 'modern-dashboard' ) }
				</span>
			) }
		</div>
	);
}

/**
 * @param {string} type Result type.
 *
 * @return {string} Dashicon slug.
 */
function iconFor( type ) {
	switch ( type ) {
		case 'site':
			return 'admin-multisite';
		case 'user':
			return 'admin-users';
		case 'post':
			return 'admin-post';
		default:
			return 'admin-generic';
	}
}
