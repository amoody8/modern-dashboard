/**
 * Keeps focus inside the palette while it is open, and puts it back afterwards.
 */

import { useEffect } from '@wordpress/element';

const FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

/**
 * @param {Object}  containerRef React ref for the palette container.
 * @param {boolean} active       Whether the trap is engaged.
 */
export function useFocusTrap( containerRef, active ) {
	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}

		const container = containerRef.current;

		if ( ! container ) {
			return undefined;
		}

		// ownerDocument rather than the global: this renders through a portal,
		// so the container is the authority on which document it lives in.
		const ownerDocument = container.ownerDocument;
		const previous = ownerDocument.activeElement;
		const wrap = ownerDocument.getElementById( 'wpwrap' );

		// Makes the rest of the admin inert to screen readers. `inert` would be
		// tidier but still needs a polyfill, and this bundle takes no new
		// dependencies.
		wrap?.setAttribute( 'aria-hidden', 'true' );

		const onKeyDown = ( event ) => {
			if ( 'Tab' !== event.key ) {
				return;
			}

			// Recomputed per keypress: the result list changes as the user
			// types, so a list captured on open would be stale immediately.
			const focusable = [
				...container.querySelectorAll( FOCUSABLE ),
			].filter( ( element ) => null !== element.offsetParent );

			if ( ! focusable.length ) {
				return;
			}

			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && ownerDocument.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if (
				! event.shiftKey &&
				ownerDocument.activeElement === last
			) {
				event.preventDefault();
				first.focus();
			}
		};

		container.addEventListener( 'keydown', onKeyDown );

		return () => {
			container.removeEventListener( 'keydown', onKeyDown );
			wrap?.removeAttribute( 'aria-hidden' );

			// Only restore to something still in the document: a command may
			// have navigated, or the list that held it may have re-rendered,
			// and focusing a detached node silently drops focus to <body>.
			if ( previous && ownerDocument.contains( previous ) ) {
				previous.focus();
			}
		};
	}, [ active, containerRef ] );
}
