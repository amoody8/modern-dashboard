/**
 * Shared, cached access to the sites endpoint.
 *
 * Several blocks on one dashboard can ask for the same ranked slice of sites.
 * Caching the in-flight promise by query means they share a single request
 * instead of each firing their own.
 */

import { useEffect, useState } from '@wordpress/element';
import { api } from './api';

const cache = new Map();

/**
 * Drop everything cached. Called after a refresh so blocks re-read.
 */
export function clearSitesCache() {
	cache.clear();
}

/**
 * @param {Object} params Query for the sites endpoint.
 * @return {{items: Array, loading: boolean, error: string|null}} Fetch state.
 */
export function useSites( params ) {
	const key = JSON.stringify( params );
	const [ state, setState ] = useState( {
		items: [],
		loading: true,
		error: null,
	} );

	useEffect( () => {
		let active = true;

		if ( ! cache.has( key ) ) {
			cache.set( key, api.sites( JSON.parse( key ) ) );
		}

		setState( ( current ) => ( { ...current, loading: true } ) );

		cache
			.get( key )
			.then( ( data ) => {
				if ( active ) {
					setState( {
						items: data.items || [],
						loading: false,
						error: null,
					} );
				}
			} )
			.catch( ( err ) => {
				// A failed promise must not stay cached, or every later render
				// replays the same failure without ever retrying.
				cache.delete( key );

				if ( active ) {
					setState( {
						items: [],
						loading: false,
						error: err.message || 'Request failed',
					} );
				}
			} );

		return () => {
			active = false;
		};
	}, [ key ] );

	return state;
}
