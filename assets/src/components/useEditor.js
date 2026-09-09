/**
 * The state every editor in this plugin was reimplementing.
 *
 * Builder, MenuEditor and ThemeEditor each carried their own `dirty`, `saving`,
 * `status` and `error`, their own mutate-and-mark-dirty wrapper, and their own
 * save chain that adopts the server's response as the new draft. The shapes had
 * already drifted — one showed an unsaved-changes line, the others did not.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   options              Hook options.
 * @param {Function} options.load         Returns a promise for the initial draft.
 * @param {Function} options.save         Takes the draft, returns a promise of the saved one.
 * @param {string}   options.savedMessage Confirmation shown after a save.
 *
 * @return {Object} Editor state and the handlers that drive it.
 */
export function useEditor( { load, save, savedMessage } ) {
	const [ draft, setDraft ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );

	const mounted = useRef( true );

	useEffect( () => {
		mounted.current = true;

		return () => {
			mounted.current = false;
		};
	}, [] );

	useEffect( () => {
		setLoading( true );

		load()
			.then( ( data ) => {
				if ( mounted.current ) {
					setDraft( data );
					setError( null );
				}
			} )
			.catch( ( err ) => {
				if ( mounted.current ) {
					setError(
						err.message ||
							__(
								'Could not load this editor.',
								'modern-dashboard'
							)
					);
				}
			} )
			.finally( () => {
				if ( mounted.current ) {
					setLoading( false );
				}
			} );
		// `load` is expected to be stable; re-running on every render would
		// refetch continuously.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	/**
	 * Apply a change to the draft and mark it unsaved.
	 *
	 * @param {Function|Object} updater Next draft, or a function producing it.
	 */
	const mutate = useCallback( ( updater ) => {
		setDraft( ( current ) =>
			'function' === typeof updater ? updater( current ) : updater
		);
		setDirty( true );
		setStatus( null );
	}, [] );

	const commit = useCallback( () => {
		setSaving( true );
		setStatus( null );

		return save( draft )
			.then( ( saved ) => {
				if ( ! mounted.current ) {
					return;
				}

				// Adopt what the server stored rather than what was sent: the
				// sanitizer may have clamped or dropped values, and showing the
				// unclamped draft would misreport what was saved.
				if ( saved ) {
					setDraft( saved );
				}

				setDirty( false );
				setError( null );
				setStatus( savedMessage || __( 'Saved.', 'modern-dashboard' ) );
			} )
			.catch( ( err ) => {
				if ( mounted.current ) {
					setError(
						err.message ||
							__( 'Saving failed.', 'modern-dashboard' )
					);
				}
			} )
			.finally( () => {
				if ( mounted.current ) {
					setSaving( false );
				}
			} );
	}, [ draft, save, savedMessage ] );

	// A dirty editor warns before the tab closes. Switching tabs inside the app
	// still discards silently — that needs router-level state this app does not
	// have, and a half-guard that only catches page unload is worse than none
	// if it implies the other case is covered too.
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );

		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	return {
		draft,
		setDraft,
		mutate,
		commit,
		dirty,
		saving,
		loading,
		status,
		error,
		setError,
		setStatus,
	};
}
