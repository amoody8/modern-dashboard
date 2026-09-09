/**
 * REST client for the palette.
 *
 * Deliberately separate from lib/api.js: this bundle loads on every admin
 * screen, and the dashboard client carries the whole surface of the app.
 */

import apiFetch from '@wordpress/api-fetch';

const boot = window.modernDashboardPalette || {};

if ( boot.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

if ( boot.root ) {
	// `root` already includes the namespace, so paths below are relative to it.
	apiFetch.use(
		apiFetch.createRootURLMiddleware( boot.root.replace( /\/?$/, '/' ) )
	);
}

export const config = boot;

export const paletteApi = {
	commands: () => apiFetch( { path: 'palette/commands' } ),

	search: ( term, limit = 20 ) =>
		apiFetch( {
			path: `palette/search?term=${ encodeURIComponent(
				term
			) }&limit=${ limit }`,
		} ),
};
