/**
 * Palette entry point.
 *
 * Loads on every admin screen, so it does as little as possible before the
 * shortcut is pressed: mount a host node, render a component that returns null
 * until opened, and nothing else.
 */

import { Component, createRoot } from '@wordpress/element';
import Palette from './palette/Palette';
import './palette.scss';

const HOST_ID = 'modern-dashboard-palette-root';

/**
 * A crash inside the palette must not leave a half-rendered overlay holding the
 * focus trap. Rendering null gives the admin its keyboard back.
 */
class PaletteBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	componentDidCatch( error ) {
		// eslint-disable-next-line no-console
		console.error(
			'Modern Dashboard: the command palette failed to render.',
			error
		);
	}

	render() {
		return this.state.failed ? null : this.props.children;
	}
}

function boot() {
	if ( ! window.modernDashboardPalette ) {
		return;
	}

	// A per-browser opt-out that survives navigation, for anyone who needs the
	// palette gone without waiting on a network setting. localStorage throws in
	// some privacy configurations, so its absence must not break the page.
	try {
		if ( 'off' === window.localStorage?.getItem( 'mdash-palette' ) ) {
			return;
		}
	} catch ( error ) {
		// Ignore: an unreadable localStorage is not a reason to skip the palette.
	}

	if ( document.getElementById( HOST_ID ) ) {
		return;
	}

	const host = document.createElement( 'div' );
	host.id = HOST_ID;
	// Both classes matter: the design tokens are scoped to .modern-dashboard,
	// and the portal renders outside the app tree.
	host.className = 'modern-dashboard modern-dashboard-palette';
	document.body.appendChild( host );

	createRoot( host ).render(
		<PaletteBoundary>
			<Palette />
		</PaletteBoundary>
	);
}

boot();
