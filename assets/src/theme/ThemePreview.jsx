/**
 * A small mock of the admin chrome.
 *
 * Colour pickers are a poor way to judge a colour scheme — the numbers say
 * nothing about what the sidebar will look like next to the admin bar. This
 * shows the combination in roughly the arrangement it will appear in.
 */

import { __ } from '@wordpress/i18n';

export default function ThemePreview( { theme } ) {
	const radius = `${ theme.radius }px`;

	return (
		<div className="md-preview" aria-hidden="true">
			<div
				className="md-preview__bar"
				style={ { background: theme.bar_bg, color: theme.bar_text } }
			>
				<span>{ __( 'My Network', 'modern-dashboard' ) }</span>
				<span>
					{ theme.howdy_text || __( 'Howdy,', 'modern-dashboard' ) }{ ' ' }
					{ __( 'Alex', 'modern-dashboard' ) }
				</span>
			</div>
			<div className="md-preview__body">
				<div
					className="md-preview__menu"
					style={ {
						background: theme.menu_bg,
						color: theme.menu_text,
					} }
				>
					<span>{ __( 'Dashboard', 'modern-dashboard' ) }</span>
					<span
						className="md-preview__menu-active"
						style={ {
							background: theme.menu_active_bg,
							color: theme.menu_active_text,
						} }
					>
						{ __( 'Posts', 'modern-dashboard' ) }
					</span>
					<span>{ __( 'Media', 'modern-dashboard' ) }</span>
					<span>{ __( 'Settings', 'modern-dashboard' ) }</span>
				</div>
				<div className="md-preview__content">
					<p>
						{ __( 'Body text with a', 'modern-dashboard' ) }{ ' ' }
						<span style={ { color: theme.link } }>
							{ __( 'link', 'modern-dashboard' ) }
						</span>
						{ '.' }
					</p>
					<span
						className="md-preview__button"
						style={ {
							background: theme.accent,
							borderRadius: radius,
						} }
					>
						{ __( 'Save changes', 'modern-dashboard' ) }
					</span>
				</div>
			</div>
		</div>
	);
}
