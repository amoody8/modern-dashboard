/**
 * Edits the selected block.
 *
 * Every control is generated from the block definition's `settings` schema, so
 * a block type registered through the `modern_dashboard_blocks` filter gets a
 * working inspector without any JavaScript being written for it.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Checkbox, Field } from '../components/Primitives';

/**
 * A setting can declare `depends: { otherKey: value }` to hide itself when
 * another setting makes it irrelevant.
 *
 * @param {Object} spec     Setting schema entry.
 * @param {Object} settings Current block settings.
 * @return {boolean} Whether the control applies.
 */
function applies( spec, settings ) {
	if ( ! spec.depends ) {
		return true;
	}

	return Object.entries( spec.depends ).every(
		// Loose compare: select values may be numbers stored as strings.
		// eslint-disable-next-line eqeqeq
		( [ key, value ] ) => settings[ key ] == value
	);
}

function Control( { name, spec, value, onChange } ) {
	if ( 'toggle' === spec.type ) {
		return (
			<Checkbox
				label={ spec.label || name }
				checked={ Boolean( value ) }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
		);
	}

	return (
		<Field label={ spec.label || name } help={ spec.help }>
			{ ( id ) => {
				if ( 'select' === spec.type ) {
					return (
						<select
							id={ id }
							value={ value ?? '' }
							onChange={ ( event ) =>
								onChange( event.target.value )
							}
						>
							{ ( spec.options || [] ).map( ( option ) => (
								<option
									key={ option.value }
									value={ option.value }
								>
									{ option.label }
								</option>
							) ) }
						</select>
					);
				}

				if ( 'number' === spec.type ) {
					return (
						<input
							id={ id }
							type="number"
							min={ spec.min }
							max={ spec.max }
							value={ value ?? '' }
							onChange={ ( event ) =>
								onChange( Number( event.target.value ) )
							}
						/>
					);
				}

				if ( 'textarea' === spec.type ) {
					return (
						<textarea
							id={ id }
							rows={ 4 }
							value={ value ?? '' }
							onChange={ ( event ) =>
								onChange( event.target.value )
							}
						/>
					);
				}

				return (
					<input
						id={ id }
						type="text"
						value={ value ?? '' }
						placeholder={ spec.placeholder }
						onChange={ ( event ) => onChange( event.target.value ) }
					/>
				);
			} }
		</Field>
	);
}

export default function BlockInspector( {
	block,
	definition,
	columns,
	onChangeSettings,
	onChangeWidth,
	onDuplicate,
	onRemove,
} ) {
	if ( ! block || ! definition ) {
		return (
			<aside className="md-inspector">
				<p className="md-inspector__empty">
					{ __( 'Select a block to edit it.', 'modern-dashboard' ) }
				</p>
			</aside>
		);
	}

	const settings = block.settings || {};
	const schema = definition.settings || {};
	const min = definition.min_width || 1;
	const max = definition.max_width || columns;

	const widths = [];

	for ( let width = min; width <= max; width++ ) {
		widths.push( width );
	}

	return (
		<aside className="md-inspector">
			<header className="md-inspector__header">
				<h2>{ definition.label }</h2>
				{ definition.description && (
					<p className="md-inspector__description">
						{ definition.description }
					</p>
				) }
			</header>

			<Field label={ __( 'Width', 'modern-dashboard' ) }>
				{ ( id ) => (
					<select
						id={ id }
						value={ block.width }
						onChange={ ( event ) =>
							onChangeWidth( Number( event.target.value ) )
						}
					>
						{ widths.map( ( width ) => (
							<option key={ width } value={ width }>
								{ sprintf(
									/* translators: 1: number of columns, 2: total columns */
									__(
										'%1$s of %2$s columns',
										'modern-dashboard'
									),
									width,
									columns
								) }
							</option>
						) ) }
					</select>
				) }
			</Field>

			{ Object.entries( schema )
				.filter( ( [ , spec ] ) => applies( spec, settings ) )
				.map( ( [ name, spec ] ) => (
					<Control
						key={ name }
						name={ name }
						spec={ spec }
						value={ settings[ name ] }
						onChange={ ( value ) =>
							onChangeSettings( { ...settings, [ name ]: value } )
						}
					/>
				) ) }

			{ Object.keys( schema ).length === 0 && (
				<p className="md-inspector__empty">
					{ __(
						'This block has nothing to configure.',
						'modern-dashboard'
					) }
				</p>
			) }

			<footer className="md-inspector__footer">
				<button
					type="button"
					className="md-button md-button--ghost"
					onClick={ onDuplicate }
				>
					{ __( 'Duplicate', 'modern-dashboard' ) }
				</button>
				<button
					type="button"
					className="md-button md-button--danger"
					onClick={ onRemove }
				>
					{ __( 'Remove', 'modern-dashboard' ) }
				</button>
			</footer>
		</aside>
	);
}
