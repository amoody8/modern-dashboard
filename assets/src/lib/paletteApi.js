/**
 * REST client for the palette.
 *
 * Deliberately separate from lib/api.js: this bundle loads on every admin
 * screen, and the dashboard client carries the whole surface of the app.
 *
 * It also registers **no** global middleware. `wp-api-fetch` is one shared
 * script, not a copy per bundle, so `apiFetch.use()` mutates a singleton that
 * every other plugin and core itself is using. Two root-URL middlewares on that
 * singleton fight: `use()` prepends, so the last one registered wins, and the
 * loser's requests fall back to the bare `/wp-json/` root with the namespace
 * dropped — a 404 on every call. Passing an absolute `url` skips the whole
 * middleware chain that rewrites paths, so this bundle cannot be broken by, or
 * break, anyone else's registrations.
 */

import apiFetch from '@wordpress/api-fetch';

const boot = window.modernDashboardPalette || {};

/** Root already includes the namespace; keep exactly one trailing slash. */
const root = String( boot.root || '' ).replace( /\/?$/, '/' );

export const config = boot;

/**
 * @param {string} path  Path relative to the plugin's REST namespace.
 * @param {Object} query Query parameters; empty values are omitted.
 *
 * @return {Promise<Object>} The parsed response.
 */
function get( path, query = {} ) {
	const url = new URL( root + path, window.location.origin );

	Object.entries( query ).forEach( ( [ key, value ] ) => {
		if ( undefined !== value && null !== value && '' !== value ) {
			url.searchParams.set( key, String( value ) );
		}
	} );

	return apiFetch( {
		url: url.toString(),
		// Sent per request rather than through createNonceMiddleware, for the
		// same reason: that middleware is global state too.
		headers: boot.nonce ? { 'X-WP-Nonce': boot.nonce } : {},
	} );
}

export const paletteApi = {
	commands: () => get( 'palette/commands' ),

	search: ( term, limit = 20 ) => get( 'palette/search', { term, limit } ),
};
