/**
 * Small shared presentational pieces.
 */

import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function Spinner( { label } ) {
	return (
		<p className="md-loading" role="status">
			<span className="md-loading__dot" aria-hidden="true" />
			{ label || __( 'Loading…', 'modern-dashboard' ) }
		</p>
	);
}

export function Notice( { tone = 'info', children, onDismiss } ) {
	return (
		<div
			className={ `md-notice md-notice--${ tone }` }
			role={ tone === 'bad' ? 'alert' : 'status' }
		>
			<div>{ children }</div>
			{ onDismiss && (
				<button
					type="button"
					className="md-notice__close"
					onClick={ onDismiss }
				>
					<span className="screen-reader-text">
						{ __( 'Dismiss', 'modern-dashboard' ) }
					</span>
					<span aria-hidden="true">×</span>
				</button>
			) }
		</div>
	);
}

export function Badge( { tone = 'muted', children } ) {
	return (
		<span className={ `md-badge md-badge--${ tone }` }>{ children }</span>
	);
}

export function StatCard( { label, value, hint, tone = 'neutral', footer } ) {
	return (
		<div className={ `md-stat md-stat--${ tone }` }>
			<span className="md-stat__label">{ label }</span>
			<strong className="md-stat__value">{ value }</strong>
			{ hint && <span className="md-stat__hint">{ hint }</span> }
			{ footer && <div className="md-stat__footer">{ footer }</div> }
		</div>
	);
}

export function EmptyState( { title, description } ) {
	return (
		<div className="md-empty">
			{ /* An h3, not a strong: the styles target a heading, and an
			     inline element with no margin sits jammed against its
			     description. This had been rendering unstyled everywhere. */ }
			<h3>{ title }</h3>
			{ description && <p>{ description }</p> }
		</div>
	);
}

/**
 * A labelled control. The label is wired to the input by id rather than by
 * nesting, so screen readers announce it reliably and clicking the label always
 * focuses the right control.
 *
 * @param {Object}   props           Component props.
 * @param {string}   props.label     Visible (or screen-reader-only) label text.
 * @param {boolean}  props.hideLabel Render the label for assistive tech only.
 * @param {string}   props.help      Optional help text below the control.
 * @param {string}   props.className Extra class names for the wrapper.
 * @param {Function} props.children  Render function receiving the control id.
 * @return {JSX.Element} The field.
 */
export function Field( {
	label,
	hideLabel = false,
	help,
	className = '',
	children,
} ) {
	const id = useId();

	return (
		<div className={ `md-field ${ className }`.trim() }>
			<label
				htmlFor={ id }
				className={ hideLabel ? 'screen-reader-text' : undefined }
			>
				{ label }
			</label>
			{ children( id ) }
			{ help && <span className="md-field__help">{ help }</span> }
		</div>
	);
}

/**
 * @param {Object}   props          Component props.
 * @param {string}   props.label    Label text.
 * @param {boolean}  props.checked  Checked state.
 * @param {Function} props.onChange Change handler.
 * @param {boolean}  props.disabled Whether the control is disabled.
 * @return {JSX.Element} The checkbox.
 */
export function Checkbox( { label, checked, onChange, disabled = false } ) {
	const id = useId();

	return (
		<div className="md-check">
			<input
				id={ id }
				type="checkbox"
				checked={ checked }
				disabled={ disabled }
				onChange={ onChange }
			/>
			<label htmlFor={ id }>{ label }</label>
		</div>
	);
}

/**
 * A checkbox whose label is visually hidden but still bound to it by id, for
 * dense rows where a visible label would not fit.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.label    Label text, announced to assistive tech.
 * @param {boolean}  props.checked  Checked state.
 * @param {Function} props.onChange Change handler.
 * @return {JSX.Element} The checkbox.
 */
export function ShowToggle( { label, checked, onChange } ) {
	const id = useId();

	return (
		<span className="md-show-toggle">
			<input
				id={ id }
				type="checkbox"
				checked={ checked }
				onChange={ onChange }
			/>
			<label htmlFor={ id } className="screen-reader-text">
				{ label }
			</label>
		</span>
	);
}
