/**
 * The dashboard builder.
 *
 * Layout is a flow of blocks with 12-column width spans rather than a free x/y
 * grid: spans give real control while staying correct at every screen size,
 * which a freely positioned grid does not without a pile of breakpoint and
 * overlap logic.
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
	rectSortingStrategy,
	sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { Field, Notice, Spinner } from '../components/Primitives';
import AssignmentPanel from './AssignmentPanel';
import BlockInspector from './BlockInspector';
import BlockPalette from './BlockPalette';
import SortableBlock from './SortableBlock';
import { api, config } from '../lib/api';

/**
 * Block ids only have to be unique within a template; the server re-mints any
 * duplicate or missing id on save, so this is a convenience, not a guarantee.
 *
 * @param {string} type Block type.
 * @return {string} A new block id.
 */
function newId( type ) {
	return `blk_${ type }_${ Math.random().toString( 36 ).slice( 2, 10 ) }`;
}

export default function Builder( { overview } ) {
	const columns = config.columns || 12;

	const [ definitions, setDefinitions ] = useState( [] );
	const [ templates, setTemplates ] = useState( [] );
	const [ assignments, setAssignments ] = useState( {} );
	const [ defaultId, setDefaultId ] = useState( '' );
	const [ roles, setRoles ] = useState( [] );

	const [ draft, setDraft ] = useState( null );
	const [ selectedId, setSelectedId ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );

	const sensors = useSensors(
		useSensor( PointerSensor, {
			// Without a small threshold a click on the handle registers as a
			// zero-distance drag and selection stops working.
			activationConstraint: { distance: 4 },
		} ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	useEffect( () => {
		Promise.all( [ api.blocks(), api.templates() ] )
			.then( ( [ blockData, templateData ] ) => {
				setDefinitions( blockData.blocks || [] );
				setTemplates( templateData.templates || [] );
				setAssignments( templateData.assignments || {} );
				setDefaultId( templateData.default || '' );
				setRoles( templateData.roles || [] );

				const first =
					( templateData.templates || [] ).find(
						( t ) => t.id === templateData.default
					) || ( templateData.templates || [] )[ 0 ];

				if ( first ) {
					setDraft( structuredClone( first ) );
				}
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__( 'Could not load the builder.', 'modern-dashboard' )
				)
			);
	}, [] );

	const definitionFor = useCallback(
		( type ) => definitions.find( ( item ) => item.type === type ) || null,
		[ definitions ]
	);

	const selectedBlock = useMemo(
		() =>
			( draft?.blocks || [] ).find(
				( block ) => block.id === selectedId
			) || null,
		[ draft, selectedId ]
	);

	const mutate = ( updater ) => {
		setDraft( ( current ) => ( current ? updater( current ) : current ) );
		setDirty( true );
		setStatus( null );
	};

	const addBlock = ( type ) => {
		const definition = definitionFor( type );

		if ( ! definition ) {
			return;
		}

		const block = {
			id: newId( type ),
			type,
			width: definition.default_width || definition.max_width || columns,
			settings: { ...( definition.defaults || {} ) },
		};

		mutate( ( current ) => ( {
			...current,
			blocks: [ ...current.blocks, block ],
		} ) );
		setSelectedId( block.id );
	};

	const updateBlock = ( id, patch ) =>
		mutate( ( current ) => ( {
			...current,
			blocks: current.blocks.map( ( block ) =>
				block.id === id ? { ...block, ...patch } : block
			),
		} ) );

	const duplicateBlock = ( id ) =>
		mutate( ( current ) => {
			const index = current.blocks.findIndex(
				( block ) => block.id === id
			);

			if ( index === -1 ) {
				return current;
			}

			const copy = {
				...structuredClone( current.blocks[ index ] ),
				id: newId( current.blocks[ index ].type ),
			};
			const blocks = [ ...current.blocks ];

			blocks.splice( index + 1, 0, copy );

			return { ...current, blocks };
		} );

	const removeBlock = ( id ) => {
		mutate( ( current ) => ( {
			...current,
			blocks: current.blocks.filter( ( block ) => block.id !== id ),
		} ) );
		setSelectedId( null );
	};

	const onDragEnd = ( event ) => {
		const { active, over } = event;

		if ( ! over || active.id === over.id ) {
			return;
		}

		mutate( ( current ) => {
			const from = current.blocks.findIndex(
				( block ) => block.id === active.id
			);
			const to = current.blocks.findIndex(
				( block ) => block.id === over.id
			);

			if ( from === -1 || to === -1 ) {
				return current;
			}

			return {
				...current,
				blocks: arrayMove( current.blocks, from, to ),
			};
		} );
	};

	const save = () => {
		setSaving( true );
		setError( null );

		api.saveTemplate( draft )
			.then( ( saved ) => {
				// Take the server's copy: it has sanitized widths, filled in
				// defaults and minted ids, so the canvas shows what was stored.
				setDraft( saved );
				setTemplates( ( current ) => {
					const rest = current.filter(
						( item ) => item.id !== saved.id
					);

					return [ ...rest, saved ].sort( ( a, b ) =>
						a.name.localeCompare( b.name )
					);
				} );
				setDirty( false );
				setStatus( __( 'Dashboard saved.', 'modern-dashboard' ) );
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Saving failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setSaving( false ) );
	};

	const switchTemplate = ( id ) => {
		const found = templates.find( ( item ) => item.id === id );

		if ( ! found ) {
			return;
		}

		setDraft( structuredClone( found ) );
		setSelectedId( null );
		setDirty( false );
		setStatus( null );
	};

	const createTemplate = () => {
		setDraft( {
			id: '',
			name: __( 'New dashboard', 'modern-dashboard' ),
			blocks: [],
		} );
		setSelectedId( null );
		setDirty( true );
		setStatus( null );
	};

	const removeTemplate = () => {
		if ( ! draft?.id ) {
			return;
		}

		setError( null );

		api.deleteTemplate( draft.id )
			.then( ( data ) => {
				setTemplates( data.templates || [] );
				setDefaultId( data.default || '' );
				setStatus( __( 'Dashboard deleted.', 'modern-dashboard' ) );

				const next = ( data.templates || [] ).find(
					( t ) => t.id === data.default
				);

				setDraft( next ? structuredClone( next ) : null );
				setSelectedId( null );
				setDirty( false );
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Deleting failed.', 'modern-dashboard' )
				)
			);
	};

	if ( error && ! draft ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! draft ) {
		return (
			<Spinner
				label={ __( 'Loading the builder…', 'modern-dashboard' ) }
			/>
		);
	}

	const blockIds = draft.blocks.map( ( block ) => block.id );

	return (
		<div className="md-builder">
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

			<div className="md-builder__bar">
				<Field label={ __( 'Editing', 'modern-dashboard' ) }>
					{ ( id ) => (
						<select
							id={ id }
							value={ draft.id }
							onChange={ ( event ) =>
								switchTemplate( event.target.value )
							}
						>
							{ ! draft.id && (
								<option value="">
									{ __(
										'New dashboard (unsaved)',
										'modern-dashboard'
									) }
								</option>
							) }
							{ templates.map( ( template ) => (
								<option
									key={ template.id }
									value={ template.id }
								>
									{ template.name }
									{ template.id === defaultId
										? ` — ${ __(
												'default',
												'modern-dashboard'
										  ) }`
										: '' }
								</option>
							) ) }
						</select>
					) }
				</Field>

				<Field
					label={ __( 'Name', 'modern-dashboard' ) }
					className="md-field--grow"
				>
					{ ( id ) => (
						<input
							id={ id }
							type="text"
							value={ draft.name }
							onChange={ ( event ) =>
								mutate( ( c ) => ( {
									...c,
									name: event.target.value,
								} ) )
							}
						/>
					) }
				</Field>

				<div className="md-builder__actions">
					<button
						type="button"
						className="button"
						onClick={ createTemplate }
					>
						{ __( 'New', 'modern-dashboard' ) }
					</button>
					<button
						type="button"
						className="button md-danger"
						onClick={ removeTemplate }
						disabled={ ! draft.id || draft.id === defaultId }
						title={
							draft.id === defaultId
								? __(
										'Make another dashboard the default first.',
										'modern-dashboard'
								  )
								: undefined
						}
					>
						{ __( 'Delete', 'modern-dashboard' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
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

			<div className="md-builder__body">
				<BlockPalette blocks={ definitions } onAdd={ addBlock } />

				<div className="md-canvas">
					{ draft.blocks.length === 0 ? (
						<p className="md-canvas__empty">
							{ __(
								'Empty dashboard. Add a block from the list on the left.',
								'modern-dashboard'
							) }
						</p>
					) : (
						<DndContext
							sensors={ sensors }
							collisionDetection={ closestCenter }
							onDragEnd={ onDragEnd }
						>
							<SortableContext
								items={ blockIds }
								strategy={ rectSortingStrategy }
							>
								<div className="md-grid">
									{ draft.blocks.map( ( block ) => (
										<SortableBlock
											key={ block.id }
											block={ block }
											definition={ definitionFor(
												block.type
											) }
											overview={ overview }
											columns={ columns }
											selected={ block.id === selectedId }
											onSelect={ setSelectedId }
										/>
									) ) }
								</div>
							</SortableContext>
						</DndContext>
					) }
				</div>

				<BlockInspector
					block={ selectedBlock }
					definition={
						selectedBlock
							? definitionFor( selectedBlock.type )
							: null
					}
					columns={ columns }
					onChangeSettings={ ( settings ) =>
						updateBlock( selectedBlock.id, { settings } )
					}
					onChangeWidth={ ( width ) =>
						updateBlock( selectedBlock.id, { width } )
					}
					onDuplicate={ () => duplicateBlock( selectedBlock.id ) }
					onRemove={ () => removeBlock( selectedBlock.id ) }
				/>
			</div>

			<AssignmentPanel
				templates={ templates }
				roles={ roles }
				assignments={ assignments }
				defaultId={ defaultId }
				onSaved={ ( data ) => {
					setAssignments( data.assignments );
					setDefaultId( data.default );
					setStatus( __( 'Assignments saved.', 'modern-dashboard' ) );
				} }
				onError={ setError }
			/>

			<p className="md-builder__hint">
				{ sprintf(
					/* translators: %s: number of columns */
					__(
						'Blocks flow left to right across a %s-column grid. Drag the handle to reorder, or focus it and use the arrow keys.',
						'modern-dashboard'
					),
					columns
				) }
			</p>
		</div>
	);
}
