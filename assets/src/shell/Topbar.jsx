/**
 * The header that replaces WordPress's toolbar.
 *
 * Deliberately quieter than core's: the toolbar tries to be navigation, and
 * with a real sidebar beside it that is redundant. What is left is where you
 * are, the two actions people actually reach for, and the account.
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object} props          Component props.
 * @param {string} props.title    Current screen name.
 * @param {Object} props.user     Display name and avatar.
 * @param {Object} props.links    Useful destinations.
 * @param {string} props.siteName Site name.
 *
 * @return {JSX.Element} The header.
 */
export default function Topbar( { title, user, links, siteName } ) {
	const [ menuOpen, setMenuOpen ] = useState( false );
	const menuRef = useRef( null );

	useEffect( () => {
		if ( ! menuOpen ) {
			return undefined;
		}

		const onDown = ( event ) => {
			if (
				menuRef.current &&
				! menuRef.current.contains( event.target )
			) {
				setMenuOpen( false );
			}
		};

		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				setMenuOpen( false );
			}
		};

		document.addEventListener( 'mousedown', onDown );
		document.addEventListener( 'keydown', onKey );

		return () => {
			document.removeEventListener( 'mousedown', onDown );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ menuOpen ] );

	return (
		<header className="mds-top">
			<div className="mds-top__where">
				<span className="mds-top__title">{ title || siteName }</span>
			</div>

			<div className="mds-top__actions">
				<a className="mds-top__ghost" href={ links.siteHome }>
					{ __( 'Visit site', 'modern-dashboard' ) }
				</a>

				<a className="mds-top__primary" href={ links.newPost }>
					{ __( 'New post', 'modern-dashboard' ) }
				</a>

				<div className="mds-top__account" ref={ menuRef }>
					<button
						type="button"
						className="mds-top__avatar-button"
						aria-expanded={ menuOpen }
						aria-haspopup="true"
						onClick={ () => setMenuOpen( ( v ) => ! v ) }
					>
						<img
							className="mds-top__avatar"
							src={ user.avatar }
							alt=""
							width="26"
							height="26"
						/>
						<span className="screen-reader-text">
							{ __( 'Account menu', 'modern-dashboard' ) }
						</span>
					</button>

					{ menuOpen && (
						<div className="mds-top__menu" role="menu">
							<span className="mds-top__menu-name">
								{ user.name }
							</span>
							<a role="menuitem" href={ links.profile }>
								{ __( 'Profile', 'modern-dashboard' ) }
							</a>
							{ links.network && (
								<a role="menuitem" href={ links.network }>
									{ __(
										'Network admin',
										'modern-dashboard'
									) }
								</a>
							) }
							<a role="menuitem" href={ links.logout }>
								{ __( 'Log out', 'modern-dashboard' ) }
							</a>
						</div>
					) }
				</div>
			</div>
		</header>
	);
}
