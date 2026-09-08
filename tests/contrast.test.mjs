/**
 * Tests for the WCAG contrast maths behind the branding editor's legibility
 * warnings. Pure functions, so they run under plain `node --test` with no
 * WordPress and no DOM.
 *
 * Run with `npm test`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	contrastRatio,
	contrastVerdict,
	parseHex,
	relativeLuminance,
} from '../assets/src/lib/contrast.js';

describe( 'parseHex', () => {
	it( 'parses six-digit hex', () => {
		assert.deepEqual( parseHex( '#336699' ), [ 0x33, 0x66, 0x99 ] );
	} );

	it( 'expands shorthand to the same colour', () => {
		assert.deepEqual( parseHex( '#369' ), parseHex( '#336699' ) );
	} );

	it( 'tolerates a missing hash and surrounding space', () => {
		assert.deepEqual( parseHex( '  336699 ' ), [ 0x33, 0x66, 0x99 ] );
	} );

	it( 'rejects anything that is not hex', () => {
		for ( const value of [ 'red', '#12345', 'rgb(0,0,0)', '', null, undefined, 42 ] ) {
			assert.equal( parseHex( value ), null, `expected ${ String( value ) } to be rejected` );
		}
	} );
} );

describe( 'relativeLuminance', () => {
	it( 'is 0 for black and 1 for white', () => {
		assert.equal( relativeLuminance( [ 0, 0, 0 ] ), 0 );
		assert.equal( relativeLuminance( [ 255, 255, 255 ] ), 1 );
	} );
} );

describe( 'contrastRatio', () => {
	it( 'gives the maximum 21:1 for black on white', () => {
		assert.equal( contrastRatio( '#ffffff', '#000000' ), 21 );
	} );

	it( 'is symmetric', () => {
		assert.equal( contrastRatio( '#123456', '#fedcba' ), contrastRatio( '#fedcba', '#123456' ) );
	} );

	it( 'gives 1:1 for a colour against itself', () => {
		assert.equal( contrastRatio( '#2271b1', '#2271b1' ), 1 );
	} );

	it( 'matches the known ratio for the WordPress admin blue on white', () => {
		// 5.17:1 — the value the branding editor reports for the default accent.
		assert.ok( Math.abs( contrastRatio( '#2271b1', '#ffffff' ) - 5.17 ) < 0.01 );
	} );

	it( 'returns null when either colour is unparseable', () => {
		assert.equal( contrastRatio( 'red', '#ffffff' ), null );
		assert.equal( contrastRatio( '#ffffff', 'nope' ), null );
	} );
} );

describe( 'contrastVerdict', () => {
	it( 'passes at or above the 4.5:1 body-text threshold', () => {
		assert.equal( contrastVerdict( 4.5 ).tone, 'good' );
		assert.equal( contrastVerdict( 21 ).tone, 'good' );
	} );

	it( 'warns between 3:1 and 4.5:1', () => {
		assert.equal( contrastVerdict( 3 ).tone, 'warn' );
		assert.equal( contrastVerdict( 4.49 ).tone, 'warn' );
	} );

	it( 'fails below 3:1', () => {
		assert.equal( contrastVerdict( 2.99 ).tone, 'bad' );
		assert.equal( contrastVerdict( 1 ).tone, 'bad' );
	} );

	it( 'reports the ratio to two decimals', () => {
		assert.match( contrastVerdict( 5.166 ).message, /5\.17:1/ );
	} );

	it( 'has no verdict for an unknown ratio', () => {
		assert.equal( contrastVerdict( null ), null );
	} );
} );
