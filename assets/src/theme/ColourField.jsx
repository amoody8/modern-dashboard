/**
 * A colour control: swatch picker and hex field, bound to one another.
 */

import { useId } from '@wordpress/element';

export default function ColourField( { label, value, onChange } ) {
	const id = useId();

	return (
		<div className="md-colour">
			<label htmlFor={ id }>{ label }</label>
			<span className="md-colour__controls">
				<input
					id={ id }
					type="color"
					value={ value }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
				<input
					type="text"
					className="md-colour__hex"
					value={ value }
					spellCheck="false"
					aria-label={ label }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
			</span>
		</div>
	);
}
