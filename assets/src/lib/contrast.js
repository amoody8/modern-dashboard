/**
 * WCAG contrast, so illegible colour choices are caught before they are saved
 * rather than after somebody cannot read their own admin.
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {string} hex A #rgb or #rrggbb colour.
 * @return {number[]|null} [r, g, b] in 0–255, or null if unparseable.
 */
export function parseHex( hex ) {
	if ( typeof hex !== 'string' ) {
		return null;
	}

	const value = hex.trim().replace( /^#/, '' );
	const full =
		value.length === 3 ? value.replace( /./g, ( c ) => c + c ) : value;

	if ( ! /^[0-9a-f]{6}$/i.test( full ) ) {
		return null;
	}

	return [
		parseInt( full.slice( 0, 2 ), 16 ),
		parseInt( full.slice( 2, 4 ), 16 ),
		parseInt( full.slice( 4, 6 ), 16 ),
	];
}

/**
 * @param {number[]} rgb Channel values in 0–255.
 * @return {number} Relative luminance per WCAG 2.
 */
export function relativeLuminance( rgb ) {
	const [ r, g, b ] = rgb.map( ( channel ) => {
		const c = channel / 255;

		return c <= 0.03928
			? c / 12.92
			: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	} );

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * @param {string} foreground Foreground colour.
 * @param {string} background Background colour.
 * @return {number|null} Contrast ratio, or null when either colour is unparseable.
 */
export function contrastRatio( foreground, background ) {
	const fg = parseHex( foreground );
	const bg = parseHex( background );

	if ( ! fg || ! bg ) {
		return null;
	}

	const a = relativeLuminance( fg );
	const b = relativeLuminance( bg );
	const lighter = Math.max( a, b );
	const darker = Math.min( a, b );

	return ( lighter + 0.05 ) / ( darker + 0.05 );
}

/**
 * Judge a ratio for admin chrome, which is normal-size body text: 4.5:1 is the
 * AA threshold, and below 3:1 is unusable rather than merely tight.
 *
 * @param {number|null} ratio Contrast ratio.
 * @return {{tone: string, message: string}|null} A verdict, or null if unknown.
 */
export function contrastVerdict( ratio ) {
	if ( ratio === null ) {
		return null;
	}

	const shown = ratio.toFixed( 2 );

	if ( ratio >= 4.5 ) {
		return {
			tone: 'good',
			message: sprintf(
				/* translators: %s: contrast ratio, e.g. 7.21 */
				__( '%s:1 — meets AA for body text', 'modern-dashboard' ),
				shown
			),
		};
	}

	if ( ratio >= 3 ) {
		return {
			tone: 'warn',
			message: sprintf(
				/* translators: %s: contrast ratio, e.g. 3.40 */
				__(
					'%s:1 — below the 4.5:1 needed for body text; readable only at large sizes',
					'modern-dashboard'
				),
				shown
			),
		};
	}

	return {
		tone: 'bad',
		message: sprintf(
			/* translators: %s: contrast ratio, e.g. 1.80 */
			__( '%s:1 — too low to read', 'modern-dashboard' ),
			shown
		),
	};
}
