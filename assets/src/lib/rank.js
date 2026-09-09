/**
 * Scoring and merging for palette results.
 *
 * Pure by design — no imports, no DOM, no network — so the ranking rules can be
 * tested directly and tuned without running WordPress.
 */

/** Relative weight per result type. A command palette is a command palette first. */
const TYPE_WEIGHT = {
	command: 1,
	site: 0.95,
	post: 0.9,
	user: 0.85,
};

/**
 * Added to results from the current site.
 *
 * Additive rather than multiplicative on purpose: a weak local match must not
 * outrank a strong network one. It only decides near-ties, which is exactly
 * what "prefer where I already am" should mean.
 */
const LOCAL_BONUS = 0.15;

const RECENT_7_DAYS = 7 * 24 * 60 * 60;
const RECENT_30_DAYS = 30 * 24 * 60 * 60;

/** Results kept per group, so one noisy group cannot crowd out the rest. */
const PER_GROUP_CAP = 5;

/**
 * How well `needle` matches `haystack`, from 0 (no match) to 1 (exact).
 *
 * @param {string} needle   Search term.
 * @param {string} haystack Text to match against.
 *
 * @return {number} Score between 0 and 1.
 */
export function fuzzyScore( needle, haystack ) {
	const term = String( needle || '' )
		.trim()
		.toLowerCase();
	// An empty query matches everything, so the palette can open on a useful
	// "what can I do here?" list rather than a blank panel.
	if ( '' === term ) {
		return 1;
	}

	const text = String( haystack || '' ).toLowerCase();

	if ( '' === text ) {
		return 0;
	}

	if ( term === text ) {
		return 1;
	}

	if ( text.startsWith( term ) ) {
		return 0.9;
	}

	// A match at a word boundary reads as intentional in a way a match in the
	// middle of a word does not.
	const boundary = text.split( /[\s\-_/]+/ );

	if ( boundary.some( ( word ) => word.startsWith( term ) ) ) {
		return 0.8;
	}

	if ( text.includes( term ) ) {
		return 0.65;
	}

	return subsequenceScore( term, text );
}

/**
 * Ordered-subsequence match, rewarding letters that stayed together.
 *
 * @param {string} term Lowercased search term.
 * @param {string} text Lowercased text.
 *
 * @return {number} Score between 0 and 0.6, or 0 when the term is not a subsequence.
 */
function subsequenceScore( term, text ) {
	let index = 0;
	let run = 0;
	let longest = 0;

	for ( const character of text ) {
		if ( character === term[ index ] ) {
			index += 1;
			run += 1;
			longest = Math.max( longest, run );

			if ( index === term.length ) {
				break;
			}
		} else {
			run = 0;
		}
	}

	if ( index < term.length ) {
		return 0;
	}

	return 0.3 + 0.3 * ( longest / term.length );
}

/**
 * Score one result against the term.
 *
 * @param {Object} result Result object.
 * @param {string} term   Search term.
 *
 * @return {number} Sort score, higher first.
 */
export function scoreResult( result, term ) {
	const weight = TYPE_WEIGHT[ result.type ] ?? 0.8;
	const label = fuzzyScore( term, result.title || result.label );

	let score = label * weight;

	if ( Array.isArray( result.keywords ) && result.keywords.length ) {
		// Keywords are a secondary signal: they help a synonym find its command
		// without letting a keyword pile outrank a real title match.
		score += fuzzyScore( term, result.keywords.join( ' ' ) ) * 0.4;
	}

	if ( 'live' === result.source ) {
		score += LOCAL_BONUS;
	}

	score += recencyBonus( result.at );

	// Priority is the registry's own ordering, used only to break ties.
	score -= ( result.priority ?? 50 ) * 0.001;

	return score;
}

/**
 * @param {number} timestamp Unix seconds, or 0 when unknown.
 *
 * @return {number} Small bonus for recently touched things.
 */
function recencyBonus( timestamp ) {
	if ( ! timestamp ) {
		return 0;
	}

	const age = Math.floor( Date.now() / 1000 ) - timestamp;

	if ( age < 0 ) {
		return 0;
	}

	if ( age < RECENT_7_DAYS ) {
		return 0.05;
	}

	if ( age < RECENT_30_DAYS ) {
		return 0.02;
	}

	return 0;
}

/**
 * Identity of a result, for collapsing the same thing seen twice.
 *
 * The current site appears in both the live query and (before the exclusion in
 * SearchController) potentially the index, so blog id has to be part of the key
 * — two sites can hold different posts under the same id.
 *
 * @param {Object} result Result object.
 *
 * @return {string} Dedup key.
 */
function identity( result ) {
	if ( 'command' === result.type ) {
		return `command:${ result.id }`;
	}

	return `${ result.type }:${ result.blogId ?? 0 }:${ result.id }`;
}

/**
 * Merge every source into one ordered, deduped list.
 *
 * @param {Object}   groups          Result groups.
 * @param {Object[]} groups.commands Commands, already capability-filtered.
 * @param {Object[]} groups.live     Current-site results.
 * @param {Object[]} groups.index    Cross-network results.
 * @param {Object[]} groups.sites    Site results.
 * @param {string}   term            Search term.
 * @param {number}   limit           Maximum results returned.
 *
 * @return {Object[]} Ordered results.
 */
export function mergeResults( groups, term, limit = 20 ) {
	const all = [
		...( groups.commands || [] ).map( ( c ) => ( {
			...c,
			type: 'command',
			title: c.title || c.label,
		} ) ),
		...( groups.live || [] ),
		...( groups.index || [] ),
		...( groups.sites || [] ),
	];

	const seen = new Map();

	for ( const result of all ) {
		const score = scoreResult( result, term );

		if (
			0 === score ||
			( term &&
				0 === fuzzyScore( term, result.title || result.label ) &&
				! matchesKeywords( result, term ) )
		) {
			continue;
		}

		const key = identity( result );
		const existing = seen.get( key );

		if ( ! existing ) {
			seen.set( key, { ...result, score } );
			continue;
		}

		// Same thing from two sources: keep the live copy, which has the fresher
		// title and a permission-checked destination, but the better score.
		const winner = 'live' === result.source ? result : existing;

		seen.set( key, {
			...winner,
			score: Math.max( existing.score, score ),
		} );
	}

	const ordered = [ ...seen.values() ].sort( ( a, b ) => {
		if ( b.score !== a.score ) {
			return b.score - a.score;
		}

		const priority = ( a.priority ?? 50 ) - ( b.priority ?? 50 );

		if ( priority ) {
			return priority;
		}

		// Title last, so the order is total and the tests are not flaky.
		return String( a.title || '' ).localeCompare( String( b.title || '' ) );
	} );

	return capPerGroup( ordered, limit );
}

/**
 * @param {Object} result Result object.
 * @param {string} term   Search term.
 *
 * @return {boolean} Whether any keyword matches.
 */
function matchesKeywords( result, term ) {
	return (
		Array.isArray( result.keywords ) &&
		result.keywords.length > 0 &&
		fuzzyScore( term, result.keywords.join( ' ' ) ) > 0
	);
}

/**
 * @param {Object[]} results Ordered results.
 * @param {number}   limit   Overall cap.
 *
 * @return {Object[]} Capped results.
 */
function capPerGroup( results, limit ) {
	const counts = new Map();
	const kept = [];

	for ( const result of results ) {
		if ( kept.length >= limit ) {
			break;
		}

		const group = result.group || result.type;
		const count = counts.get( group ) || 0;

		if ( count >= PER_GROUP_CAP ) {
			continue;
		}

		counts.set( group, count + 1 );
		kept.push( result );
	}

	return kept;
}
