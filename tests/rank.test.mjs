/**
 * Ranking and merging rules for the command palette.
 *
 * Scores are asserted relative to one another rather than as exact numbers:
 * the tiers are the contract, the constants are tuning.
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { fuzzyScore, scoreResult, mergeResults } from '../assets/src/lib/rank.js';

describe( 'fuzzyScore', () => {
	it( 'scores an exact match highest', () => {
		assert.equal( fuzzyScore( 'settings', 'Settings' ), 1 );
	} );

	it( 'matches everything when the term is empty', () => {
		assert.equal( fuzzyScore( '', 'anything' ), 1 );
	} );

	it( 'returns zero when the haystack is empty', () => {
		assert.equal( fuzzyScore( 'x', '' ), 0 );
	} );

	it( 'ranks prefix above word-boundary above substring', () => {
		const prefix = fuzzyScore( 'set', 'settings' );
		const boundary = fuzzyScore( 'set', 'network settings' );
		const substring = fuzzyScore( 'set', 'unset value' );

		assert.ok( prefix > boundary, 'prefix should beat word boundary' );
		assert.ok( boundary > substring, 'word boundary should beat substring' );
	} );

	it( 'ranks any direct match above a scattered subsequence', () => {
		const substring = fuzzyScore( 'set', 'unset value' );
		const scattered = fuzzyScore( 'set', 'site export tool' );

		assert.ok( substring > scattered );
		assert.ok( scattered > 0, 'a subsequence should still match' );
	} );

	it( 'rewards contiguity within a subsequence', () => {
		const together = fuzzyScore( 'abc', 'xabcx' );
		const apart = fuzzyScore( 'abc', 'axbxc' );

		assert.ok( together > apart );
	} );

	it( 'returns zero when the letters are out of order', () => {
		assert.equal( fuzzyScore( 'cba', 'abc' ), 0 );
	} );

	it( 'ignores case', () => {
		assert.equal( fuzzyScore( 'PLUGINS', 'plugins' ), 1 );
	} );
} );

describe( 'scoreResult', () => {
	it( 'ranks a command above a post with the same title', () => {
		const command = scoreResult( { type: 'command', title: 'Settings', priority: 50 }, 'settings' );
		const post = scoreResult( { type: 'post', title: 'Settings', priority: 50 }, 'settings' );

		assert.ok( command > post );
	} );

	it( 'scores a keyword match below a title match', () => {
		const title = scoreResult( { type: 'command', title: 'Plugins' }, 'plugins' );
		const keyword = scoreResult( { type: 'command', title: 'Extensions', keywords: [ 'plugins' ] }, 'plugins' );

		assert.ok( title > keyword );
	} );

	it( 'prefers the live copy of an otherwise identical result', () => {
		const live = scoreResult( { type: 'post', title: 'Launch plan', source: 'live' }, 'launch' );
		const indexed = scoreResult( { type: 'post', title: 'Launch plan', source: 'index' }, 'launch' );

		assert.ok( live > indexed );
	} );

	it( 'lets a lower priority break a tie', () => {
		const first = scoreResult( { type: 'command', title: 'Posts', priority: 10 }, 'posts' );
		const second = scoreResult( { type: 'command', title: 'Posts', priority: 90 }, 'posts' );

		assert.ok( first > second );
	} );
} );

describe( 'mergeResults', () => {
	const command = ( id, label, extra = {} ) => ( { id, label, group: 'navigate', priority: 50, ...extra } );

	it( 'collapses the same post seen live and indexed, keeping the live copy', () => {
		const merged = mergeResults(
			{
				commands: [],
				live: [ { type: 'post', id: 5, blogId: 2, title: 'Launch plan', source: 'live', url: 'live-url' } ],
				index: [ { type: 'post', id: 5, blogId: 2, title: 'Launch plan', source: 'index', url: 'index-url' } ],
				sites: [],
			},
			'launch'
		);

		assert.equal( merged.length, 1 );
		assert.equal( merged[ 0 ].url, 'live-url' );
	} );

	it( 'keeps the same id on different sites apart', () => {
		const merged = mergeResults(
			{
				commands: [],
				live: [],
				index: [
					{ type: 'post', id: 5, blogId: 1, title: 'Plan', source: 'index' },
					{ type: 'post', id: 5, blogId: 2, title: 'Plan', source: 'index' },
				],
				sites: [],
			},
			'plan'
		);

		assert.equal( merged.length, 2 );
	} );

	it( 'breaks a near-tie toward the local result', () => {
		const merged = mergeResults(
			{
				commands: [],
				live: [ { type: 'post', id: 1, blogId: 1, title: 'Quarterly report', source: 'live' } ],
				index: [ { type: 'post', id: 2, blogId: 9, title: 'Quarterly report', source: 'index' } ],
				sites: [],
			},
			'quarterly'
		);

		assert.equal( merged[ 0 ].blogId, 1 );
	} );

	it( 'still lets a decisively better network match win', () => {
		// The local result only matches as a scattered subsequence; the network
		// one is an exact title. The local bonus must not override that.
		const merged = mergeResults(
			{
				commands: [],
				live: [ { type: 'post', id: 1, blogId: 1, title: 'Report on quarterly yields', source: 'live' } ],
				index: [ { type: 'post', id: 2, blogId: 9, title: 'Roy', source: 'index' } ],
				sites: [],
			},
			'roy'
		);

		assert.equal( merged[ 0 ].blogId, 9 );
	} );

	it( 'caps how many results one group can contribute', () => {
		const live = Array.from( { length: 20 }, ( _, i ) => ( {
			type: 'post',
			id: i,
			blogId: 1,
			title: `Report ${ i }`,
			source: 'live',
		} ) );

		const merged = mergeResults(
			{ commands: [ command( 'go.posts', 'Reports' ) ], live, index: [], sites: [] },
			'report'
		);

		assert.ok( merged.some( ( r ) => 'command' === r.type ), 'the command survives a flood of posts' );
		assert.ok( merged.filter( ( r ) => 'post' === r.type ).length <= 5 );
	} );

	it( 'respects the overall limit', () => {
		const live = Array.from( { length: 40 }, ( _, i ) => ( {
			type: 'post',
			id: i,
			blogId: i,
			title: `Item ${ i }`,
			source: 'live',
		} ) );

		assert.equal( mergeResults( { commands: [], live, index: [], sites: [] }, 'item', 3 ).length, 3 );
	} );

	it( 'drops results that do not match at all', () => {
		const merged = mergeResults(
			{
				commands: [ command( 'go.posts', 'Posts' ) ],
				live: [ { type: 'post', id: 1, blogId: 1, title: 'Zebra husbandry', source: 'live' } ],
				index: [],
				sites: [],
			},
			'posts'
		);

		assert.ok( merged.every( ( r ) => 'Zebra husbandry' !== r.title ) );
	} );

	it( 'returns commands in priority order for an empty term', () => {
		const merged = mergeResults(
			{
				commands: [
					command( 'c.late', 'Later thing', { priority: 90 } ),
					command( 'c.early', 'Earlier thing', { priority: 10 } ),
				],
				live: [],
				index: [],
				sites: [],
			},
			''
		);

		assert.equal( merged[ 0 ].id, 'c.early' );
	} );

	it( 'is deterministic', () => {
		const groups = {
			commands: [ command( 'a', 'Alpha' ), command( 'b', 'Alpha' ) ],
			live: [],
			index: [],
			sites: [],
		};

		const first = mergeResults( groups, 'alpha' ).map( ( r ) => r.id );
		const second = mergeResults( groups, 'alpha' ).map( ( r ) => r.id );

		assert.deepEqual( first, second );
	} );
} );
