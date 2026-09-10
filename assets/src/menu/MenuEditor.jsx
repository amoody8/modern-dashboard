/**
 * Network-defined admin menus, per role.
 *
 * The list of menu items is observed rather than enumerated: WordPress only
 * knows a site's menu while that site renders an admin page, so the catalogue
 * fills in as sites are visited. The empty state says so plainly rather than
 * looking broken.
 */

import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	DndContext,
	KeyboardSensor,
	PointerSensor,
	closestCenter,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	arrayMove,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Checkbox, Field, Notice, Spinner } from '../components/Primitives';
import SortableMenuRow from './SortableMenuRow';
import { api } from '../lib/api';
import { formatRelative } from '../lib/format';
import { Panel } from '../components/Surfaces';

const EMPTY_RULE = { hidden: [], renamed: {}, order: [], submenus: {} };

/**
 * @param {Array} items Catalogue items.
 * @param {Array} order Preferred slug order.
 * @return {Array} Items ordered by the rule, with unlisted items after.
 */
function applyOrder( items, order ) {
	if ( ! order || order.length === 0 ) {
		return items;
	}

	const bySlug = new Map( items.map( ( item ) => [ item.slug, item ] ) );
	const first = order.map( ( slug ) => bySlug.get( slug ) ).filter( Boolean );
	const seen = new Set( first.map( ( item ) => item.slug ) );

	return [ ...first, ...items.filter( ( item ) => ! seen.has( item.slug ) ) ];
}

export default function MenuEditor() {
	const [ items, setItems ] = useState( [] );
	const [ updated, setUpdated ] = useState( 0 );
	const [ rules, setRules ] = useState( null );
	const [ roles, setRoles ] = useState( [] );
	const [ bypassArg, setBypassArg ] = useState( 'mdash-menu' );

	const [ role, setRole ] = useState( '' );
	const [ expanded, setExpanded ] = useState( {} );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );

	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 4 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	useEffect( () => {
		api.menu()
			.then( ( data ) => {
				setItems( data.items || [] );
				setUpdated( data.updated || 0 );
				setRules( data.rules );
				setRoles( data.roles || [] );
				setBypassArg( data.bypass_arg || 'mdash-menu' );
				setRole( ( data.roles || [] )[ 0 ]?.value || '' );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__(
							'Could not load the menu editor.',
							'modern-dashboard'
						)
				)
			);
	}, [] );

	const roleRules = useMemo(
		() =>
			rules?.roles?.[ role ]
				? { ...EMPTY_RULE, ...rules.roles[ role ] }
				: EMPTY_RULE,
		[ rules, role ]
	);

	const ordered = useMemo(
		() => applyOrder( items, roleRules.order ),
		[ items, roleRules.order ]
	);

	const mutateRole = useCallback(
		( updater ) => {
			setRules( ( current ) => {
				const existing = {
					...EMPTY_RULE,
					...( current.roles[ role ] || {} ),
				};

				return {
					...current,
					roles: { ...current.roles, [ role ]: updater( existing ) },
				};
			} );
			setDirty( true );
			setStatus( null );
		},
		[ role ]
	);

	const toggleHidden = ( slug ) =>
		mutateRole( ( current ) => ( {
			...current,
			hidden: current.hidden.includes( slug )
				? current.hidden.filter( ( value ) => value !== slug )
				: [ ...current.hidden, slug ],
		} ) );

	const rename = ( slug, label ) =>
		mutateRole( ( current ) => {
			const renamed = { ...current.renamed };

			// An empty field means "leave the original name alone", so the entry
			// is removed rather than stored as an empty string.
			if ( label.trim() === '' ) {
				delete renamed[ slug ];
			} else {
				renamed[ slug ] = label;
			}

			return { ...current, renamed };
		} );

	const toggleChildHidden = ( parent, slug ) =>
		mutateRole( ( current ) => {
			const sub = {
				hidden: [],
				renamed: {},
				...( current.submenus[ parent ] || {} ),
			};

			sub.hidden = sub.hidden.includes( slug )
				? sub.hidden.filter( ( value ) => value !== slug )
				: [ ...sub.hidden, slug ];

			return {
				...current,
				submenus: { ...current.submenus, [ parent ]: sub },
			};
		} );

	const renameChild = ( parent, slug, label ) =>
		mutateRole( ( current ) => {
			const sub = {
				hidden: [],
				renamed: {},
				...( current.submenus[ parent ] || {} ),
			};
			const renamed = { ...sub.renamed };

			if ( label.trim() === '' ) {
				delete renamed[ slug ];
			} else {
				renamed[ slug ] = label;
			}

			return {
				...current,
				submenus: {
					...current.submenus,
					[ parent ]: { ...sub, renamed },
				},
			};
		} );

	const onDragEnd = ( event ) => {
		const { active, over } = event;

		if ( ! over || active.id === over.id ) {
			return;
		}

		const slugs = ordered.map( ( item ) => item.slug );
		const from = slugs.indexOf( active.id );
		const to = slugs.indexOf( over.id );

		if ( from === -1 || to === -1 ) {
			return;
		}

		mutateRole( ( current ) => ( {
			...current,
			order: arrayMove( slugs, from, to ),
		} ) );
	};

	const save = () => {
		setSaving( true );
		setError( null );

		api.saveMenu( rules )
			.then( ( saved ) => {
				setRules( saved );
				setDirty( false );
				setStatus( __( 'Menu rules saved.', 'modern-dashboard' ) );
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Saving failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setSaving( false ) );
	};

	const resetRole = () =>
		mutateRole( () => ( {
			hidden: [],
			renamed: {},
			order: [],
			submenus: {},
		} ) );

	const rebuildCatalogue = () => {
		setError( null );

		api.resetMenuCatalogue()
			.then( () => {
				setItems( [] );
				setUpdated( 0 );
				setStatus(
					__(
						'Catalogue cleared. It refills as admin pages are visited across the network.',
						'modern-dashboard'
					)
				);
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Reset failed.', 'modern-dashboard' )
				)
			);
	};

	if ( error && ! rules ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! rules ) {
		return (
			<Spinner
				label={ __( 'Loading the menu editor…', 'modern-dashboard' ) }
			/>
		);
	}

	return (
		<div className="md-menus">
			{ status && (
				<Notice tone="good" onDismiss={ () => setStatus( null ) }>
					{ status }
				</Notice>
			) }
			{ error && (
				<Notice tone="bad" onDismiss={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Notice tone="info">
				<strong>
					{ __(
						'Hiding a menu item is cosmetic.',
						'modern-dashboard'
					) }
				</strong>{ ' ' }
				{ __(
					'It tidies the screen but grants and revokes nothing — the page is still reachable by its URL for anyone whose role allows it. Use roles and capabilities for access control.',
					'modern-dashboard'
				) }
			</Notice>

			<Panel title={ __( 'Menu customisation', 'modern-dashboard' ) }>
				<Checkbox
					label={ __(
						'Apply menu rules across the network',
						'modern-dashboard'
					) }
					checked={ rules.enabled }
					onChange={ ( event ) => {
						setRules( ( current ) => ( {
							...current,
							enabled: event.target.checked,
						} ) );
						setDirty( true );
					} }
				/>

				<Checkbox
					label={ __(
						'Never modify the menu for network administrators (recommended)',
						'modern-dashboard'
					) }
					checked={ rules.exempt_super_admins }
					onChange={ ( event ) => {
						setRules( ( current ) => ( {
							...current,
							exempt_super_admins: event.target.checked,
						} ) );
						setDirty( true );
					} }
				/>

				<p className="md-panel__intro">
					{ sprintf(
						/* translators: %s: query argument name, e.g. mdash-menu */
						__(
							'Network admin screens are never modified, so this editor always stays reachable. If a site administrator is left without the menu items they need, adding ?%s=off to any admin URL shows them the untouched menu.',
							'modern-dashboard'
						),
						bypassArg
					) }
				</p>
			</Panel>

			<div className="md-builder__bar">
				<Field label={ __( 'Editing rules for', 'modern-dashboard' ) }>
					{ ( id ) => (
						<select
							id={ id }
							value={ role }
							onChange={ ( event ) =>
								setRole( event.target.value )
							}
						>
							{ roles.map( ( item ) => (
								<option key={ item.value } value={ item.value }>
									{ item.label }
								</option>
							) ) }
						</select>
					) }
				</Field>

				<div className="md-builder__actions">
					<button
						type="button"
						className="md-button md-button--ghost"
						onClick={ rebuildCatalogue }
					>
						{ __( 'Rebuild catalogue', 'modern-dashboard' ) }
					</button>
					<button
						type="button"
						className="md-button md-button--danger"
						onClick={ resetRole }
					>
						{ __( 'Clear this role', 'modern-dashboard' ) }
					</button>
					<button
						type="button"
						className="md-button"
						onClick={ save }
						disabled={ saving || ! dirty }
					>
						{ saving
							? __( 'Saving…', 'modern-dashboard' )
							: __( 'Save', 'modern-dashboard' ) }
					</button>
				</div>
			</div>

			{ dirty && (
				<p className="md-builder__dirty">
					{ __( 'Unsaved changes.', 'modern-dashboard' ) }
				</p>
			) }

			{ ordered.length === 0 ? (
				<Notice tone="info">
					{ __(
						'No menu items observed yet. WordPress only exposes a site’s admin menu while that site is rendering an admin page, so the catalogue fills in as you and your users visit sites in the network. Open any site’s dashboard and come back.',
						'modern-dashboard'
					) }
				</Notice>
			) : (
				<DndContext
					sensors={ sensors }
					collisionDetection={ closestCenter }
					onDragEnd={ onDragEnd }
				>
					<SortableContext
						items={ ordered.map( ( item ) => item.slug ) }
						strategy={ verticalListSortingStrategy }
					>
						<ul className="md-menu-list">
							{ ordered.map( ( item ) => (
								<SortableMenuRow
									key={ item.slug }
									item={ item }
									hidden={ roleRules.hidden.includes(
										item.slug
									) }
									renamed={ roleRules.renamed[ item.slug ] }
									submenuRules={
										roleRules.submenus[ item.slug ] || {}
									}
									expanded={ Boolean(
										expanded[ item.slug ]
									) }
									onToggleExpanded={ ( slug ) =>
										setExpanded( ( current ) => ( {
											...current,
											[ slug ]: ! current[ slug ],
										} ) )
									}
									onToggleHidden={ toggleHidden }
									onRename={ rename }
									onToggleChildHidden={ toggleChildHidden }
									onRenameChild={ renameChild }
								/>
							) ) }
						</ul>
					</SortableContext>
				</DndContext>
			) }

			<p className="md-builder__hint">
				{ updated > 0
					? sprintf(
							/* translators: %s: relative time */
							__(
								'Catalogue last changed %s.',
								'modern-dashboard'
							),
							formatRelative( updated )
					  )
					: __( 'Catalogue is empty.', 'modern-dashboard' ) }
			</p>
		</div>
	);
}
