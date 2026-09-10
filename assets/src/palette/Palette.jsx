/**
 * The command palette overlay.
 *
 * Renders nothing at all until opened, so the cost on a screen where nobody
 * presses the shortcut is one keydown listener.
 */

import {
	createPortal,
	useCallback,
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Spinner, Notice, EmptyState } from '../components/Primitives';
import { paletteApi, config } from '../lib/paletteApi';
import { mergeResults } from '../lib/rank';
import { useFocusTrap } from './useFocusTrap';
import { usePaletteHotkey } from './usePaletteHotkey';
import PaletteResults from './PaletteResults';

/** Commands cannot change within a page load, so they are fetched once. */
let commandCache = null;

const EMPTY_REMOTE = {
	live: [],
	index: [],
	sites: [],
	stale: null,
	partial: false,
};

/** Matches the debounce every other search box in this plugin uses. */
const DEBOUNCE_MS = 250;

/** Below this, only commands are filtered — a one-letter network query is noise. */
const MIN_TERM = 2;

export default function Palette() {
	const [ open, setOpen ] = useState( false );
	const [ term, setTerm ] = useState( '' );
	const [ commands, setCommands ] = useState( commandCache );
	const [ groups, setGroups ] = useState( {} );
	const [ remote, setRemote ] = useState( EMPTY_REMOTE );
	const [ selected, setSelected ] = useState( 0 );
	const [ status, setStatus ] = useState( 'idle' );
	const [ error, setError ] = useState( null );

	const containerRef = useRef( null );
	const inputRef = useRef( null );
	const requestId = useRef( 0 );

	const listId = useId();

	const reset = useCallback( () => {
		setTerm( '' );
		setRemote( EMPTY_REMOTE );
		setSelected( 0 );
		setError( null );
		setStatus( 'idle' );
	}, [] );

	// Toggling rather than opening: pressing the shortcut again is how people
	// dismiss a palette they opened by accident. Closing this way clears the
	// search too, so reopening does not present a stale term and its results.
	//
	// The reset happens here rather than inside the setOpen updater — an
	// updater must be pure, and StrictMode calls it twice.
	const toggle = useCallback( () => {
		if ( open ) {
			reset();
		}

		setOpen( ! open );
	}, [ open, reset ] );

	usePaletteHotkey( toggle, open );
	useFocusTrap( containerRef, open );

	const close = useCallback( () => {
		setOpen( false );
		reset();
	}, [ reset ] );

	// Commands arrive once and stay; they are what makes the first keystroke
	// feel instant while the network query is still in flight.
	useEffect( () => {
		if ( ! open || commands ) {
			return;
		}

		paletteApi
			.commands()
			.then( ( data ) => {
				commandCache = data.commands;
				setCommands( data.commands );
				setGroups( data.groups || {} );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__( 'Could not load commands.', 'modern-dashboard' )
				)
			);
	}, [ open, commands ] );

	useEffect( () => {
		if ( open ) {
			inputRef.current?.focus();
		}
	}, [ open ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		const trimmed = term.trim();

		if ( trimmed.length < MIN_TERM ) {
			setRemote( EMPTY_REMOTE );
			setStatus( 'idle' );

			return undefined;
		}

		let active = true;
		const id = ++requestId.current;

		const timer = setTimeout( () => {
			setStatus( 'loading' );

			paletteApi
				.search( trimmed )
				.then( ( data ) => {
					// `active` guards unmount; the id guards a slow earlier
					// response landing after a newer one.
					if ( active && id === requestId.current ) {
						setRemote( data );
						setError( null );
						setStatus( 'idle' );
					}
				} )
				.catch( ( err ) => {
					if ( active && id === requestId.current ) {
						setError(
							err.message ||
								__( 'Search failed.', 'modern-dashboard' )
						);
						setStatus( 'error' );
					}
				} );
		}, DEBOUNCE_MS );

		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ term, open ] );

	const results = useMemo(
		() =>
			mergeResults(
				{
					commands: commands || [],
					live: remote.live,
					index: remote.index,
					sites: remote.sites,
				},
				term
			),
		[ commands, remote, term ]
	);

	useEffect( () => setSelected( 0 ), [ term ] );

	const run = useCallback(
		( result ) => {
			if ( 'client' === result.action?.type ) {
				if ( 'disable-palette' === result.action.handler ) {
					const url = new URL( window.location.href );
					url.searchParams.set(
						config.bypassArg || 'mdash-palette',
						'off'
					);
					window.location.assign( url.toString() );
				}

				return;
			}

			const url = result.action?.url || result.url;

			if ( url ) {
				window.location.assign( url );

				return;
			}

			close();
		},
		[ close ]
	);

	const onKeyDown = ( event ) => {
		if ( 'Escape' === event.key ) {
			// SiteDetail listens for Escape on the document. Without this, one
			// press would close the palette and the site panel underneath it.
			event.stopPropagation();
			close();

			return;
		}

		if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			setSelected(
				( current ) => ( current + 1 ) % Math.max( results.length, 1 )
			);

			return;
		}

		if ( 'ArrowUp' === event.key ) {
			event.preventDefault();
			setSelected(
				( current ) =>
					( current - 1 + Math.max( results.length, 1 ) ) %
					Math.max( results.length, 1 )
			);

			return;
		}

		if ( 'Enter' === event.key && results[ selected ] ) {
			event.preventDefault();
			run( results[ selected ] );
		}
	};

	if ( ! open ) {
		return null;
	}

	const showEmpty = 'loading' !== status && 0 === results.length;

	return createPortal(
		// The token class travels with the overlay because this renders into
		// document.body, outside the host element that carries it. Without it
		// every var() here resolves to nothing and the panel is transparent.
		<div className="modern-dashboard-palette md-palette__overlay">
			{ /* eslint-disable-next-line jsx-a11y/no-static-element-interactions */ }
			<div
				className="md-palette__scrim"
				// mousedown, not click: a selection drag that ends on the scrim
				// should not dismiss the panel it started in.
				onMouseDown={ close }
			/>
			{ /* Arrow keys and Enter belong to the combobox as a whole, so the
			     dialog is where the key handler lives. */ }
			{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */ }
			<div
				className="md-palette"
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Command palette', 'modern-dashboard' ) }
				ref={ containerRef }
				onKeyDown={ onKeyDown }
			>
				<div className="md-palette__field">
					<span
						className="dashicons dashicons-search"
						aria-hidden="true"
					/>
					<input
						ref={ inputRef }
						type="text"
						className="md-palette__input"
						role="combobox"
						aria-expanded="true"
						aria-controls={ listId }
						aria-activedescendant={
							results[ selected ]
								? `${ listId }-option-${ selected }`
								: undefined
						}
						aria-autocomplete="list"
						placeholder={ __(
							'Search sites, content and commands…',
							'modern-dashboard'
						) }
						value={ term }
						onChange={ ( event ) => setTerm( event.target.value ) }
					/>
					{ 'loading' === status && (
						<Spinner
							label={ __( 'Searching…', 'modern-dashboard' ) }
						/>
					) }
				</div>

				{ error && <Notice tone="bad">{ error }</Notice> }

				{ showEmpty ? (
					<EmptyState
						title={ __( 'No matches', 'modern-dashboard' ) }
						description={ __(
							'Try a different word, or a site name.',
							'modern-dashboard'
						) }
					/>
				) : (
					<PaletteResults
						results={ results }
						groups={ groups }
						selected={ selected }
						listId={ listId }
						onRun={ run }
						onSelect={ setSelected }
					/>
				) }

				<div className="md-palette__footer">
					<span>
						{ sprintf(
							/* translators: %d: number of results. */
							_n(
								'%d result',
								'%d results',
								results.length,
								'modern-dashboard'
							),
							results.length
						) }
					</span>
					{ remote.partial && (
						<span>
							{ __(
								'Network content search is off on large networks.',
								'modern-dashboard'
							) }
						</span>
					) }
					{ ! remote.partial && remote.stale && (
						<span>
							{ sprintf(
								/* translators: %s: human-readable time difference, e.g. "2 hours". */
								__(
									'Network results as of %s ago.',
									'modern-dashboard'
								),
								humanAge( remote.stale )
							) }
						</span>
					) }
				</div>
			</div>
			<div
				className="screen-reader-text"
				role="status"
				aria-live="polite"
			>
				{ sprintf(
					/* translators: %d: number of results. */
					_n(
						'%d result',
						'%d results',
						results.length,
						'modern-dashboard'
					),
					results.length
				) }
			</div>
		</div>,
		document.body
	);
}

/**
 * @param {number} timestamp Unix seconds.
 *
 * @return {string} Rough age, for the freshness footer.
 */
function humanAge( timestamp ) {
	const seconds = Math.max( 0, Math.floor( Date.now() / 1000 ) - timestamp );
	const minutes = Math.round( seconds / 60 );

	if ( minutes < 60 ) {
		/* translators: %d: number of minutes. */
		const label = _n(
			'%d minute',
			'%d minutes',
			minutes,
			'modern-dashboard'
		);

		return sprintf( label, minutes );
	}

	const hours = Math.round( minutes / 60 );

	if ( hours < 24 ) {
		/* translators: %d: number of hours. */
		const label = _n( '%d hour', '%d hours', hours, 'modern-dashboard' );

		return sprintf( label, hours );
	}

	const days = Math.round( hours / 24 );

	/* translators: %d: number of days. */
	return sprintf( _n( '%d day', '%d days', days, 'modern-dashboard' ), days );
}
