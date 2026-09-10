/**
 * Display formatting helpers.
 */

import { __, _n, sprintf } from '@wordpress/i18n';

const numberFormatter = new Intl.NumberFormat();

/**
 * @param {number|null|undefined} value Raw number.
 * @return {string} Localised number, or an em dash when there is nothing to show.
 */
export function formatNumber( value ) {
	if ( value === null || value === undefined || Number.isNaN( value ) ) {
		return '—';
	}

	return numberFormatter.format( value );
}

/**
 * @param {number|null|undefined} bytes Byte count.
 * @return {string} Human readable size.
 */
export function formatBytes( bytes ) {
	if ( bytes === null || bytes === undefined || Number.isNaN( bytes ) ) {
		return '—';
	}

	if ( bytes < 1024 ) {
		return `${ bytes } B`;
	}

	const units = [ 'KB', 'MB', 'GB', 'TB', 'PB' ];
	let value = bytes / 1024;
	let unit = 0;

	while ( value >= 1024 && unit < units.length - 1 ) {
		value /= 1024;
		unit += 1;
	}

	return `${ value.toFixed( value >= 10 || unit === 0 ? 0 : 1 ) } ${
		units[ unit ]
	}`;
}

/**
 * Coarse relative time. Deliberately low precision — these timestamps come from
 * a cache that is minutes old by design, so pretending to second accuracy
 * would overstate what we know.
 *
 * @param {number|null|undefined} timestamp Unix timestamp in seconds.
 * @return {string} Relative description.
 */
export function formatRelative( timestamp ) {
	if ( ! timestamp ) {
		return __( 'never', 'modern-dashboard' );
	}

	const seconds = Math.floor( Date.now() / 1000 ) - timestamp;

	if ( seconds < 0 ) {
		const ahead = Math.abs( seconds );

		if ( ahead < 60 ) {
			return __( 'in under a minute', 'modern-dashboard' );
		}

		const minutes = Math.round( ahead / 60 );

		return sprintf(
			/* translators: %s: number of minutes */
			_n( 'in %s minute', 'in %s minutes', minutes, 'modern-dashboard' ),
			formatNumber( minutes )
		);
	}

	if ( seconds < 60 ) {
		return __( 'just now', 'modern-dashboard' );
	}

	const scales = [
		[
			60,
			60,
			( n ) =>
				/* translators: %s: number of minutes */
				_n( '%s minute ago', '%s minutes ago', n, 'modern-dashboard' ),
		],
		[
			3600,
			24,
			( n ) =>
				/* translators: %s: number of hours */
				_n( '%s hour ago', '%s hours ago', n, 'modern-dashboard' ),
		],
		[
			86400,
			30,
			( n ) =>
				/* translators: %s: number of days */
				_n( '%s day ago', '%s days ago', n, 'modern-dashboard' ),
		],
		[
			2592000,
			12,
			( n ) =>
				/* translators: %s: number of months */
				_n( '%s month ago', '%s months ago', n, 'modern-dashboard' ),
		],
	];

	for ( const [ divisor, limit, template ] of scales ) {
		const amount = Math.floor( seconds / divisor );

		if ( amount < limit ) {
			return sprintf( template( amount ), formatNumber( amount ) );
		}
	}

	const years = Math.floor( seconds / 31536000 );

	return sprintf(
		/* translators: %s: number of years */
		_n( '%s year ago', '%s years ago', years, 'modern-dashboard' ),
		formatNumber( years )
	);
}

/**
 * @param {string} domain Site domain.
 * @param {string} path   Site path such as `/team/`.
 * @return {string} Compact site address for table display.
 */
export function formatAddress( domain, path ) {
	return `${ domain }${ path === '/' ? '' : path }`.replace( /\/$/, '' );
}

/**
 * Precise relative time, for timestamps we actually know precisely.
 *
 * `formatRelative` is deliberately coarse because metrics come from a cache
 * that is minutes old — claiming second accuracy there would overstate what we
 * know. An audit entry is the opposite: it records the moment something
 * happened, so "12 seconds ago" is true and useful, and rounding it to
 * "moments ago" throws away the ordering the reader is scanning for.
 *
 * @param {number|null|undefined} timestamp Unix timestamp in seconds.
 * @return {string} Relative description.
 */
export function formatExactRelative( timestamp ) {
	if ( ! timestamp ) {
		return '—';
	}

	const seconds = Math.max( 0, Math.floor( Date.now() / 1000 ) - timestamp );

	if ( seconds < 60 ) {
		/* translators: %d: number of seconds. */
		const label = _n(
			'%d second ago',
			'%d seconds ago',
			seconds,
			'modern-dashboard'
		);

		return sprintf( label, seconds );
	}

	const minutes = Math.floor( seconds / 60 );

	if ( minutes < 60 ) {
		/* translators: %d: number of minutes. */
		const label = _n(
			'%d minute ago',
			'%d minutes ago',
			minutes,
			'modern-dashboard'
		);

		return sprintf( label, minutes );
	}

	const hours = Math.floor( minutes / 60 );

	if ( hours < 24 ) {
		/* translators: %d: number of hours. */
		const label = _n(
			'%d hour ago',
			'%d hours ago',
			hours,
			'modern-dashboard'
		);

		return sprintf( label, hours );
	}

	const days = Math.floor( hours / 24 );

	if ( days < 30 ) {
		/* translators: %d: number of days. */
		const label = _n(
			'%d day ago',
			'%d days ago',
			days,
			'modern-dashboard'
		);

		return sprintf( label, days );
	}

	return formatDate( timestamp );
}

/**
 * Absolute local time, for the title attribute behind a relative one.
 *
 * @param {number|null|undefined} timestamp Unix timestamp in seconds.
 * @return {string} Localised date and time.
 */
export function formatDate( timestamp ) {
	if ( ! timestamp ) {
		return '—';
	}

	return new Date( timestamp * 1000 ).toLocaleString();
}
