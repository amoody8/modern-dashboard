/**
 * Thin REST client bound to the plugin's namespace.
 */

import apiFetch from '@wordpress/api-fetch';

const boot = window.modernDashboard || {};

/** Root already includes the namespace; keep exactly one trailing slash. */
const root = String( boot.root || '' ).replace( /\/?$/, '/' );

export const config = boot;

/**
 * Call the plugin's REST namespace.
 *
 * Builds an absolute URL rather than registering root/nonce middleware.
 * `wp-api-fetch` is a single shared script, so `apiFetch.use()` mutates state
 * that core and every other plugin also use: two root-URL middlewares fight
 * (the last registered wins, since `use()` prepends), and the loser's requests
 * fall back to the bare `/wp-json/` root with the namespace stripped, 404ing
 * every call. An absolute `url` bypasses the rewriting chain entirely.
 *
 * @param {string} path    Path relative to the namespace.
 * @param {Object} options Extra apiFetch options.
 *
 * @return {Promise<Object>} The parsed response.
 */
function request( path, options = {} ) {
	return apiFetch( {
		...options,
		url: root + path,
		headers: {
			...( options.headers || {} ),
			...( boot.nonce ? { 'X-WP-Nonce': boot.nonce } : {} ),
		},
	} );
}

/**
 * Build a query string from defined, non-empty values only.
 *
 * @param {Object} params Query parameters.
 * @return {string} Query string including a leading `?`, or an empty string.
 */
function query( params ) {
	const search = new URLSearchParams();

	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	} );

	const serialised = search.toString();

	return serialised ? `?${ serialised }` : '';
}

export const api = {
	overview: ( fresh = false ) =>
		request( `overview${ query( { fresh: fresh ? 1 : '' } ) }` ),

	sites: ( params ) => request( `sites${ query( params ) }` ),

	auditLog: ( params ) => request( `audit${ query( params ) }` ),

	roles: () => request( 'roles' ),

	saveRoles: ( data ) => request( 'roles', { method: 'POST', data } ),

	previewRoles: ( data ) =>
		request( 'roles/preview', { method: 'POST', data } ),

	resetCapabilityCatalogue: () =>
		request( 'roles/catalogue', { method: 'DELETE' } ),

	site: ( id ) => request( `sites/${ id }` ),

	refreshSite: ( id ) =>
		request( `sites/${ id }/refresh`, { method: 'POST' } ),

	refreshBatch: () => request( 'refresh', { method: 'POST' } ),

	settings: () => request( 'settings' ),

	saveSettings: ( data ) => request( 'settings', { method: 'POST', data } ),

	blocks: () => request( 'blocks' ),

	templates: () => request( 'templates' ),

	activeTemplate: () => request( 'templates/active' ),

	saveTemplate: ( data ) => request( 'templates', { method: 'POST', data } ),

	deleteTemplate: ( id ) =>
		request( `templates/${ id }`, { method: 'DELETE' } ),

	saveAssignments: ( data ) =>
		request( 'templates/assignments', { method: 'POST', data } ),

	menu: () => request( 'menu' ),

	saveMenu: ( data ) => request( 'menu', { method: 'POST', data } ),

	resetMenuCatalogue: () => request( 'menu/catalogue', { method: 'DELETE' } ),

	theme: () => request( 'theme' ),

	saveTheme: ( data ) => request( 'theme', { method: 'POST', data } ),

	siteTheme: ( id ) => request( `theme/site/${ id }` ),

	saveSiteTheme: ( id, data ) =>
		request( `theme/site/${ id }`, { method: 'POST', data } ),

	clearSiteTheme: ( id ) =>
		request( `theme/site/${ id }`, { method: 'DELETE' } ),
};
