/**
 * The navigation, rebuilt.
 *
 * The items are WordPress's own — read from `$menu` at render time — but the
 * behaviour is not: type to filter, arrow through results, Enter to go. That
 * filter is the point. A stock WordPress sidebar with thirty plugin entries is
 * a scroll; this makes it two keystrokes.
 */

import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { navIcon } from './icons';

/**
 * @param {Object}   props           Component props.
 * @param {Object[]} props.items     Menu items from the server.
 * @param {string}   props.current   Slug of the active screen.
 * @param {string}   props.siteName  Site name for the header.
 * @param {Object}   props.links     Useful destinations.
 * @param {boolean}  props.isNetwork Whether this is a network admin screen.
 * @param {Object}   props.user      Current user, for the account card.
 *
 * @return {JSX.Element} The sidebar.
 */
export default function Sidebar( {
	items,
	current,
	siteName,
	links,
	isNetwork,
	user,
} ) {
	const [ filter, setFilter ] = useState( '' );
	const [ open, setOpen ] = useState( () => activeParent( items, current ) );
	const searchRef = useRef( null );

	// `/` focuses the filter, the convention in every tool this borrows from.
	useEffect( () => {
		const onKey = ( event ) => {
			if ( '/' !== event.key ) {
				return;
			}

			const target = event.target;
			const typing =
				target &&
				( [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes(
					target.tagName
				) ||
					target.isContentEditable );

			if ( typing ) {
				return;
			}

			event.preventDefault();
			searchRef.current?.focus();
		};

		document.addEventListener( 'keydown', onKey );

		return () => document.removeEventListener( 'keydown', onKey );
	}, [] );

	const visible = useMemo( () => {
		const term = filter.trim().toLowerCase();

		if ( ! term ) {
			return items;
		}

		// A parent survives if it matches, or if any child does — and when a
		// child matched, only the matching children are shown.
		return items
			.map( ( item ) => {
				if ( item.label.toLowerCase().includes( term ) ) {
					return item;
				}

				const children = item.children.filter( ( child ) =>
					child.label.toLowerCase().includes( term )
				);

				return children.length ? { ...item, children } : null;
			} )
			.filter( Boolean );
	}, [ items, filter ] );

	const filtering = '' !== filter.trim();

	// The reference separates clusters with space rather than headings. Groups
	// are derived from slugs so an unknown plugin lands in the trailing group
	// instead of being mixed into core's.
	const grouped = useMemo( () => groupItems( visible ), [ visible ] );

	return (
		<nav
			className="mds-nav"
			aria-label={ __( 'Admin navigation', 'modern-dashboard' ) }
		>
			<div className="mds-nav__brand">
				<a className="mds-nav__site" href={ links.siteHome }>
					<span className="mds-nav__wordmark">{ siteName }</span>
					{ isNetwork && (
						<span className="mds-nav__scope">
							{ __( 'Network', 'modern-dashboard' ) }
						</span>
					) }
				</a>
			</div>

			<div className="mds-nav__search">
				<input
					ref={ searchRef }
					type="search"
					className="mds-nav__search-input"
					placeholder={ __( 'Jump to…', 'modern-dashboard' ) }
					aria-label={ __( 'Filter navigation', 'modern-dashboard' ) }
					value={ filter }
					onChange={ ( event ) => setFilter( event.target.value ) }
					onKeyDown={ ( event ) => {
						if ( 'Escape' === event.key ) {
							setFilter( '' );
							event.currentTarget.blur();
						}
					} }
				/>
				{ ! filter && (
					<kbd className="mds-nav__kbd" aria-hidden="true">
						/
					</kbd>
				) }
			</div>

			<div className="mds-nav__list">
				{ grouped.map( ( group, index ) => (
					<ul key={ index } className="mds-nav__cluster">
						{ group.map( ( item ) => {
							const isCurrent = isActive( item, current );
							const expanded =
								filtering || open === item.slug || isCurrent;

							return (
								<li
									key={ item.slug }
									className="mds-nav__group"
								>
									<a
										href={ item.url }
										className={ `mds-nav__item${
											isCurrent ? ' is-current' : ''
										}` }
										aria-current={
											isCurrent ? 'page' : undefined
										}
										onClick={ ( event ) => {
											// A parent with children reveals them on first
											// click rather than navigating away from a list
											// the user is still choosing from.
											if (
												item.children.length &&
												! expanded
											) {
												event.preventDefault();
												setOpen( item.slug );
											}
										} }
									>
										{ navIcon( item.icon ) }
										<span className="mds-nav__label">
											{ labelOf( item.label ) }
										</span>
										{ countOf( item.label ) && (
											<span className="mds-nav__count">
												{ countOf( item.label ) }
											</span>
										) }
									</a>

									{ expanded && item.children.length > 0 && (
										<ul className="mds-nav__sub">
											{ item.children.map( ( child ) => (
												<li key={ child.slug }>
													<a
														href={ child.url }
														className={ `mds-nav__subitem${
															child.slug ===
															current
																? ' is-current'
																: ''
														}` }
														aria-current={
															child.slug ===
															current
																? 'page'
																: undefined
														}
													>
														{ child.label }
													</a>
												</li>
											) ) }
										</ul>
									) }
								</li>
							);
						} ) }
					</ul>
				) ) }
			</div>

			{ filtering && 0 === visible.length && (
				<p className="mds-nav__empty">
					{ __( 'Nothing matches.', 'modern-dashboard' ) }
				</p>
			) }

			{ user && (
				<a className="mds-nav__user" href={ links.profile }>
					<img
						className="mds-nav__user-avatar"
						src={ user.avatar }
						alt=""
						width="26"
						height="26"
					/>
					<span className="mds-nav__user-text">
						<span className="mds-nav__user-name">
							{ user.name }
						</span>
						{ user.email && (
							<span className="mds-nav__user-email">
								{ user.email }
							</span>
						) }
					</span>
				</a>
			) }
		</nav>
	);
}

/**
 * The server appends update counts to a label as ` · N`. Splitting them here
 * lets the count render as a pill instead of running into the label text.
 *
 * @param {string} label Raw label.
 *
 * @return {string} Label without its count.
 */
function labelOf( label ) {
	return label.split( '\u2009·\u2009' )[ 0 ];
}

/**
 * @param {string} label Raw label.
 *
 * @return {string|null} The count, when the label carries one.
 */
function countOf( label ) {
	const parts = label.split( '\u2009·\u2009' );

	return parts.length > 1 ? parts[ 1 ] : null;
}

/** Slugs that open each cluster, in the order the reference shows them. */
const CLUSTER_STARTS = [ 'edit.php', 'themes.php' ];

/**
 * Split the menu into whitespace-separated clusters.
 *
 * @param {Object[]} items Visible menu items.
 *
 * @return {Object[][]} Clusters, empty ones removed.
 */
function groupItems( items ) {
	const clusters = [ [] ];

	items.forEach( ( item ) => {
		if (
			CLUSTER_STARTS.includes( item.slug ) &&
			clusters[ clusters.length - 1 ].length
		) {
			clusters.push( [] );
		}

		clusters[ clusters.length - 1 ].push( item );
	} );

	return clusters.filter( ( cluster ) => cluster.length > 0 );
}

/**
 * @param {Object} item    Menu item.
 * @param {string} current Active slug.
 *
 * @return {boolean} Whether this item or one of its children is active.
 */
function isActive( item, current ) {
	if ( item.slug === current ) {
		return true;
	}

	return item.children.some( ( child ) => child.slug === current );
}

/**
 * @param {Object[]} items   Menu items.
 * @param {string}   current Active slug.
 *
 * @return {string|null} Slug of the group holding the active screen.
 */
function activeParent( items, current ) {
	const match = items.find( ( item ) => isActive( item, current ) );

	return match ? match.slug : null;
}
