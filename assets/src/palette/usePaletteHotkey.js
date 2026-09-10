/**
 * The keyboard shortcuts that open the palette.
 *
 * WordPress core has bound Cmd/Ctrl+K to its own command palette since 6.3, and
 * the block editor binds it to "insert link". Both live inside the editor; the
 * rest of wp-admin leaves it free. So this listens in the capture phase — it
 * needs to see the event before anything else — but declines whenever the user
 * is typing, which is exactly when the editor's binding matters. Everywhere
 * else the palette answers.
 *
 * Cmd/Ctrl+Shift+P is the unconditional alternative: nothing in core claims it,
 * so there is always a way in, including from inside the editor.
 *
 * Known limit: since 6.3 the block editor canvas is an iframe, and keydowns
 * inside it never reach this document. Neither shortcut fires while the cursor
 * is in the canvas. Reaching into the iframe would mean re-attaching a listener
 * on every canvas remount, which breaks on every editor release — so the canvas
 * is deliberately unsupported, and Cmd+K there stays the editor's own.
 */

import { useEffect } from '@wordpress/element';

const EDITABLE_TAGS = [ 'INPUT', 'TEXTAREA', 'SELECT' ];

const EDITOR_CONTAINERS = [
	'.block-editor-writing-flow',
	'.editor-styles-wrapper',
	'.CodeMirror',
	'.wp-editor-area',
];

/**
 * @param {EventTarget|null} target Event target.
 *
 * @return {boolean} Whether the user is typing into something.
 */
function isEditableTarget( target ) {
	if ( ! target || 1 !== target.nodeType ) {
		return false;
	}

	if (
		EDITABLE_TAGS.includes( target.tagName ) ||
		target.isContentEditable
	) {
		return true;
	}

	return EDITOR_CONTAINERS.some( ( selector ) =>
		target.closest?.( selector )
	);
}

/**
 * @param {Function} onTrigger Called when the shortcut fires.
 * @param {boolean}  isOpen    Whether the palette is currently open.
 */
export function usePaletteHotkey( onTrigger, isOpen = false ) {
	useEffect( () => {
		const onKey = ( event ) => {
			const modifier = event.metaKey || event.ctrlKey;

			if ( ! modifier || event.altKey ) {
				return;
			}

			const key = event.key.toLowerCase();

			if ( 'p' === key && event.shiftKey ) {
				event.preventDefault();
				onTrigger();

				return;
			}

			if ( 'k' !== key || event.shiftKey ) {
				return;
			}

			// Typing: leave the key to whoever owns the field — except our own
			// input. Without that exception the shortcut is dead once the
			// palette has focus, so a second press escapes to whatever else is
			// listening instead of closing the thing already on screen.
			if ( ! isOpen && isEditableTarget( event.target ) ) {
				return;
			}

			event.preventDefault();
			onTrigger();
		};

		document.addEventListener( 'keydown', onKey, true );

		return () => document.removeEventListener( 'keydown', onKey, true );
	}, [ onTrigger, isOpen ] );
}
