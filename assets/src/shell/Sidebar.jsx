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

/**
 * @param {Object}   props           Component props.
 * @param {Object[]} props.items     Menu items from the server.
 * @param {string}   props.current   Slug of the active screen.
 * @param {string}   props.siteName  Site name for the header.
 * @param {Object}   props.links     Useful destinations.
 * @param {boolean}  props.isNetwork Whether this is a network admin screen.
 *
 * @return {JSX.Element} The sidebar.
 */
export default function Sidebar( {
	items,
	current,
	siteName,
	links,
	isNetwork,
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

	return (
		<nav
			className="mds-nav"
			aria-label={ __( 'Admin navigation', 'modern-dashboard' ) }
		>
			<div className="mds-nav__brand">
				<a className="mds-nav__site" href={ links.siteHome }>
					<span className="mds-nav__mark" aria-hidden="true">
						{ ( siteName || 'W' ).trim().charAt( 0 ).toUpperCase() }
					</span>
					<span className="mds-nav__site-text">
						<span className="mds-nav__site-name">{ siteName }</span>
						<span className="mds-nav__site-role">
							{ isNetwork
								? __( 'Network admin', 'modern-dashboard' )
								: __( 'Site admin', 'modern-dashboard' ) }
						</span>
					</span>
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

			<ul className="mds-nav__list">
				{ visible.map( ( item ) => {
					const isCurrent = isActive( item, current );
					const expanded =
						filtering || open === item.slug || isCurrent;

					return (
						<li key={ item.slug } className="mds-nav__group">
							<a
								href={ item.url }
								className={ `mds-nav__item${
									isCurrent ? ' is-current' : ''
								}` }
								aria-current={ isCurrent ? 'page' : undefined }
								onClick={ ( event ) => {
									// A parent with children reveals them on first
									// click rather than navigating away from a list
									// the user is still choosing from.
									if ( item.children.length && ! expanded ) {
										event.preventDefault();
										setOpen( item.slug );
									}
								} }
							>
								<span
									className={ `mds-nav__icon dashicons dashicons-${ item.icon }` }
									aria-hidden="true"
								/>
								<span className="mds-nav__label">
									{ item.label }
								</span>
							</a>

							{ expanded && item.children.length > 0 && (
								<ul className="mds-nav__sub">
									{ item.children.map( ( child ) => (
										<li key={ child.slug }>
											<a
												href={ child.url }
												className={ `mds-nav__subitem${
													child.slug === current
														? ' is-current'
														: ''
												}` }
												aria-current={
													child.slug === current
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

			{ filtering && 0 === visible.length && (
				<p className="mds-nav__empty">
					{ __( 'Nothing matches.', 'modern-dashboard' ) }
				</p>
			) }
		</nav>
	);
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
