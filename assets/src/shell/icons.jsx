/**
 * Outline icons for the navigation.
 *
 * WordPress ships Dashicons, which are filled and visually heavy — next to
 * 13px text they dominate the row. These are thin strokes at the same size, so
 * the label leads and the icon supports it.
 *
 * Only the core menu items are drawn. Anything else — a plugin's own entry —
 * falls back to whatever Dashicon that plugin registered, which is always
 * *something* and is never wrong, just heavier. Drawing an icon for every
 * plugin in existence is not a thing this can do.
 */

/**
 * @param {Object} props          Component props.
 * @param {string} props.d        Path data.
 * @param {Object} props.children Extra shapes.
 *
 * @return {JSX.Element} A 16px stroked icon.
 */
function Glyph( { d, children } ) {
	return (
		<svg
			className="mds-icon"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			{ d ? <path d={ d } /> : null }
			{ children }
		</svg>
	);
}

/* eslint-disable react/jsx-key -- These are values in a lookup, not a list. */
const ICONS = {
	dashboard: (
		<Glyph>
			<rect x="3" y="3" width="7" height="7" rx="1.5" />
			<rect x="14" y="3" width="7" height="7" rx="1.5" />
			<rect x="3" y="14" width="7" height="7" rx="1.5" />
			<rect x="14" y="14" width="7" height="7" rx="1.5" />
		</Glyph>
	),
	posts: (
		<Glyph>
			<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
			<path d="M14 3v5h5" />
			<path d="M9 13h6M9 17h4" />
		</Glyph>
	),
	media: (
		<Glyph>
			<rect x="3" y="4" width="18" height="16" rx="2" />
			<circle cx="8.5" cy="9.5" r="1.5" />
			<path d="m21 16-5-5L5 20" />
		</Glyph>
	),
	pages: (
		<Glyph>
			<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
			<path d="M14 3v5h5" />
		</Glyph>
	),
	comments: (
		<Glyph d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z" />
	),
	appearance: (
		<Glyph>
			<circle cx="13.5" cy="6.5" r="1.2" />
			<circle cx="17.5" cy="10.5" r="1.2" />
			<circle cx="8.5" cy="7.5" r="1.2" />
			<circle cx="6.5" cy="12.5" r="1.2" />
			<path d="M12 2a10 10 0 0 0 0 20 2.5 2.5 0 0 0 2-4 2.5 2.5 0 0 1 2-4h2a4 4 0 0 0 4-4 10 10 0 0 0-10-8z" />
		</Glyph>
	),
	plugins: (
		<Glyph>
			<circle cx="12" cy="12" r="3" />
			<path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1" />
		</Glyph>
	),
	users: (
		<Glyph>
			<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
			<circle cx="9" cy="7" r="4" />
			<path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
		</Glyph>
	),
	tools: (
		<Glyph d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
	),
	settings: (
		<Glyph>
			<circle cx="12" cy="12" r="3" />
			<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
		</Glyph>
	),
	sites: (
		<Glyph>
			<circle cx="12" cy="12" r="9" />
			<path d="M3.6 9h16.8M3.6 15h16.8" />
			<path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z" />
		</Glyph>
	),
	analytics: (
		<Glyph>
			<path d="M3 3v18h18" />
			<path d="M7 15v3M12 10v8M17 6v12" />
		</Glyph>
	),
	updates: (
		<Glyph>
			<path d="M21 2v6h-6" />
			<path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
			<path d="M3 22v-6h6" />
			<path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
		</Glyph>
	),
	generic: (
		<Glyph>
			<circle cx="12" cy="12" r="9" />
		</Glyph>
	),
};
/* eslint-enable react/jsx-key */

/** Dashicon slug → our icon. Anything absent keeps the plugin's own Dashicon. */
const FROM_DASHICON = {
	dashboard: 'dashboard',
	'admin-post': 'posts',
	'admin-media': 'media',
	'admin-page': 'pages',
	'admin-comments': 'comments',
	'admin-appearance': 'appearance',
	'admin-plugins': 'plugins',
	'admin-users': 'users',
	'admin-tools': 'tools',
	'admin-settings': 'settings',
	'admin-multisite': 'sites',
	'chart-bar': 'analytics',
	update: 'updates',
};

/**
 * @param {string} dashicon Dashicon slug the menu entry declared.
 *
 * @return {JSX.Element} An outline icon, or the Dashicon as a fallback.
 */
export function navIcon( dashicon ) {
	const name = FROM_DASHICON[ dashicon ];

	if ( name && ICONS[ name ] ) {
		return ICONS[ name ];
	}

	// Unmapped: keep whatever the plugin registered. Heavier than ours, but
	// present and meaningful, which beats a placeholder.
	return (
		<span
			className={ `mds-icon mds-icon--dashicon dashicons dashicons-${ dashicon }` }
			aria-hidden="true"
		/>
	);
}
