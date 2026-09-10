/**
 * One group of capabilities.
 *
 * Groups carry their own note where the names mislead — the posts family being
 * the case that matters, since revoking "Edit posts" does not stop anybody
 * editing a post that is already published.
 */

import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   props           Component props.
 * @param {Object}   props.group     Group definition from the server.
 * @param {Function} props.isOn      Whether a capability is effectively on.
 * @param {Function} props.isChanged Whether this draft changes it.
 * @param {Function} props.onToggle  Called with ( cap, nextValue ).
 *
 * @return {JSX.Element} The group.
 */
export default function CapabilityGroup( {
	group,
	isOn,
	isChanged,
	onToggle,
} ) {
	return (
		<section className="md-capgroup">
			<header className="md-capgroup__header">
				<h3 className="md-capgroup__title">{ group.label }</h3>
				{ group.note && (
					<p className="md-capgroup__note">{ group.note }</p>
				) }
			</header>

			<ul className="md-capgroup__list">
				{ group.caps.map( ( cap ) => (
					<CapabilityRow
						key={ cap.cap }
						cap={ cap }
						on={ isOn( cap.cap ) }
						changed={ isChanged( cap.cap ) }
						onToggle={ onToggle }
					/>
				) ) }
			</ul>
		</section>
	);
}

/**
 * One capability.
 *
 * Its own component so it can hold a `useId`: the label and its input need a
 * real `htmlFor` pairing, and a hook cannot run inside the parent's map.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.cap      Capability definition.
 * @param {boolean}  props.on       Whether it is effectively on.
 * @param {boolean}  props.changed  Whether this draft changes it.
 * @param {Function} props.onToggle Called with ( cap, nextValue ).
 *
 * @return {JSX.Element} The row.
 */
function CapabilityRow( { cap, on, changed, onToggle } ) {
	const id = useId();
	const disabled = cap.locked || cap.network;

	return (
		<li
			className={ [
				'md-cap',
				on ? 'is-on' : '',
				changed ? 'is-changed' : '',
				disabled ? 'is-disabled' : '',
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ /* Input beside the label rather than wrapping it, matching the
			     Checkbox primitive — the pairing is the htmlFor, and a label
			     whose text sits inside nested spans is not one an accessibility
			     checker can read. */ }
			<input
				id={ id }
				type="checkbox"
				checked={ on }
				disabled={ disabled }
				onChange={ ( event ) =>
					onToggle( cap.cap, event.target.checked )
				}
			/>
			<label className="md-cap__label" htmlFor={ id }>
				<span className="md-cap__name">{ cap.label }</span>
				<span className="md-cap__slug">{ cap.cap }</span>
			</label>

			<span className="md-cap__flags">
				{ changed && (
					<span className="md-cap__flag md-cap__flag--changed">
						{ __( 'Changed', 'modern-dashboard' ) }
					</span>
				) }
				{ cap.locked && (
					<span className="md-cap__flag">
						{ __( 'Protected', 'modern-dashboard' ) }
					</span>
				) }
				{ cap.network && ! cap.locked && (
					<span className="md-cap__flag">
						{ __( 'Network only', 'modern-dashboard' ) }
					</span>
				) }
			</span>
		</li>
	);
}
