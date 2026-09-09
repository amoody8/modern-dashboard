/**
 * The surfaces every screen is built from.
 *
 * Before these existed the panel header was hand-rolled in thirteen places
 * across seven files, each free to drift. A new subsystem should never write
 * that markup again — it composes these instead, and inherits the look.
 */

import { __ } from '@wordpress/i18n';
import { Spinner, Notice, EmptyState } from './Primitives';

/**
 * A titled surface. The default card everything sits in.
 *
 * @param {Object}  props           Component props.
 * @param {string}  props.title     Panel title.
 * @param {string}  props.subtitle  Optional line under the title.
 * @param {Object}  props.actions   Controls rendered at the header's trailing edge.
 * @param {boolean} props.flush     Drop the body padding, for tables that own their own.
 * @param {string}  props.className Extra classes.
 * @param {Object}  props.children  Panel body.
 *
 * @return {JSX.Element} The panel.
 */
export function Panel( {
	title,
	subtitle,
	actions,
	flush = false,
	className = '',
	children,
} ) {
	return (
		<section className={ `md-panel ${ className }`.trim() }>
			{ ( title || actions ) && (
				<header className="md-panel__header">
					<div className="md-panel__heading">
						{ title && (
							<h2 className="md-panel__title">{ title }</h2>
						) }
						{ subtitle && (
							<p className="md-panel__subtitle">{ subtitle }</p>
						) }
					</div>
					{ actions && (
						<div className="md-panel__actions">{ actions }</div>
					) }
				</header>
			) }
			<div className={ `md-panel__body${ flush ? ' is-flush' : '' }` }>
				{ children }
			</div>
		</section>
	);
}

/**
 * The page header: what this screen is, and the controls that act on all of it.
 *
 * @param {Object} props          Component props.
 * @param {string} props.title    Screen title.
 * @param {string} props.subtitle Context line — counts, freshness.
 * @param {Object} props.actions  Primary controls.
 *
 * @return {JSX.Element} The header.
 */
export function PageHeader( { title, subtitle, actions } ) {
	return (
		<header className="md-page-header">
			<div className="md-page-header__heading">
				<h1 className="md-page-header__title">{ title }</h1>
				{ subtitle && (
					<p className="md-page-header__subtitle">{ subtitle }</p>
				) }
			</div>
			{ actions && (
				<div className="md-page-header__actions">{ actions }</div>
			) }
		</header>
	);
}

/**
 * A row of headline numbers.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.children StatCard elements.
 *
 * @return {JSX.Element} The row.
 */
export function StatRow( { children } ) {
	return <div className="md-stat-row">{ children }</div>;
}

/**
 * Controls that filter or search the content below them.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.children Controls.
 * @param {Object} props.trailing Right-aligned content, usually a result count.
 *
 * @return {JSX.Element} The toolbar.
 */
export function Toolbar( { children, trailing } ) {
	return (
		<div className="md-toolbar">
			{ children }
			{ trailing && (
				<div className="md-toolbar__trailing">{ trailing }</div>
			) }
		</div>
	);
}

/**
 * A group of mutually exclusive filters.
 *
 * Preferred over a `<select>` when there are four or fewer options: the choices
 * stay visible, which matters for a filter that changes what the table means.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.label    Accessible group label.
 * @param {Object[]} props.options  `{ value, label }` entries.
 * @param {string}   props.value    Selected value.
 * @param {Function} props.onChange Called with the new value.
 *
 * @return {JSX.Element} The control.
 */
export function SegmentedControl( { label, options, value, onChange } ) {
	return (
		<div className="md-segmented" role="group" aria-label={ label }>
			{ options.map( ( option ) => (
				<button
					key={ option.value }
					type="button"
					className="md-segmented__option"
					aria-pressed={ option.value === value }
					onClick={ () => onChange( option.value ) }
				>
					{ option.label }
				</button>
			) ) }
		</div>
	);
}

/**
 * Resolves the three states every async panel has, so each screen stops
 * writing its own `if (error) … if (!data) …` ladder.
 *
 * @param {Object}  props                  Component props.
 * @param {boolean} props.loading          Whether the first load is in flight.
 * @param {string}  props.error            Error message, if any.
 * @param {boolean} props.isEmpty          Whether the loaded data has nothing to show.
 * @param {string}  props.emptyTitle       Empty-state heading.
 * @param {string}  props.emptyDescription Empty-state body.
 * @param {Object}  props.children         Content, rendered only when there is some.
 *
 * @return {JSX.Element} The resolved state.
 */
export function AsyncState( {
	loading,
	error,
	isEmpty,
	emptyTitle,
	emptyDescription,
	children,
} ) {
	if ( error ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( loading ) {
		return <Spinner />;
	}

	if ( isEmpty ) {
		return (
			<EmptyState
				title={
					emptyTitle || __( 'Nothing here yet', 'modern-dashboard' )
				}
				description={ emptyDescription }
			/>
		);
	}

	return children;
}
