/**
 * Thin REST client bound to the plugin's namespace.
 */

import apiFetch from '@wordpress/api-fetch';

const boot = window.modernDashboard || {};

if ( boot.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

if ( boot.root ) {
	// `root` already includes the namespace, so paths passed below are relative
	// to `modern-dashboard/v1`.
	apiFetch.use(
		apiFetch.createRootURLMiddleware( boot.root.replace( /\/?$/, '/' ) )
	);
}

export const config = boot;

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
		apiFetch( { path: `overview${ query( { fresh: fresh ? 1 : '' } ) }` } ),

	sites: ( params ) => apiFetch( { path: `sites${ query( params ) }` } ),

	site: ( id ) => apiFetch( { path: `sites/${ id }` } ),

	refreshSite: ( id ) =>
		apiFetch( { path: `sites/${ id }/refresh`, method: 'POST' } ),

	refreshBatch: () => apiFetch( { path: 'refresh', method: 'POST' } ),

	settings: () => apiFetch( { path: 'settings' } ),

	saveSettings: ( data ) =>
		apiFetch( { path: 'settings', method: 'POST', data } ),
};
