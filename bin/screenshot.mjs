/**
 * Look at the admin, instead of reading the CSS and hoping.
 *
 * Several layout bugs in this plugin shipped because the stylesheet said one
 * thing and the browser computed another — a more specific selector in another
 * bundle quietly winning. Reading source cannot catch that; measuring can.
 *
 * Usage:
 *   node bin/screenshot.mjs                       the network dashboard
 *   node bin/screenshot.mjs edit.php              any admin path
 *   node bin/screenshot.mjs edit.php out.png      and where to write it
 *
 * Needs `npm run env:start` and `npx playwright install chromium`.
 */

import { chromium } from 'playwright';

const BASE = process.env.MD_SITE || 'http://localhost:8888';
const USER = process.env.MD_USER || 'admin';
const PASS = process.env.MD_PASS || 'password';

const target = process.argv[ 2 ] || 'index.php?page=modern-dashboard';
const out = process.argv[ 3 ] || 'screenshot.png';

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );

await page.goto( `${ BASE }/wp-login.php` );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForLoadState( 'networkidle' );

await page.goto( `${ BASE }/wp-admin/${ target }` );
await page.waitForLoadState( 'networkidle' );
// The React app mounts after load; without this the shot catches the spinner.
await page.waitForTimeout( 1200 );

// Console errors are the other thing source-reading misses.
const problems = [];
page.on( 'console', ( m ) => m.type() === 'error' && problems.push( m.text() ) );
page.on( 'pageerror', ( e ) => problems.push( e.message ) );

const geometry = await page.evaluate( () => {
	const box = ( selector ) => {
		const el = document.querySelector( selector );

		if ( ! el ) {
			return null;
		}

		const r = el.getBoundingClientRect();

		return {
			left: Math.round( r.left ),
			right: Math.round( r.right ),
			top: Math.round( r.top ),
			width: Math.round( r.width ),
		};
	};

	const rail = box( '.mds-nav' );
	const wrap = box( '.wrap' );

	return {
		rail,
		header: box( '.mds-top' ),
		wrap,
		// The gutters, which is what keeps going wrong.
		gapLeft: rail && wrap ? wrap.left - rail.right : null,
		gapRight: wrap ? window.innerWidth - wrap.right : null,
		// A stat card collapsing onto one line is the shape of a layout that
		// lost its flex; its height says so without needing eyes.
		statHeight: ( () => {
			const el = document.querySelector( '.md-stat' );
			return el ? Math.round( el.getBoundingClientRect().height ) : null;
		} )(),
	};
} );

await page.screenshot( { path: out } );
await browser.close();

console.log( JSON.stringify( geometry, null, 2 ) );

if ( problems.length ) {
	console.log( '\nconsole errors:' );
	problems.forEach( ( p ) => console.log( '  ' + p ) );
}

console.log( `\nwrote ${ out }` );
