/**
 * Human labels for machine keys coming out of the REST API.
 */

import { __ } from '@wordpress/i18n';

export const attentionLabels = () => ( {
	updates: {
		label: __( 'Updates pending', 'modern-dashboard' ),
		tone: 'warn',
	},
	moderation: {
		label: __( 'Comments to moderate', 'modern-dashboard' ),
		tone: 'info',
	},
	inactive: {
		label: __( 'No recent posts', 'modern-dashboard' ),
		tone: 'muted',
	},
	archived: { label: __( 'Archived', 'modern-dashboard' ), tone: 'muted' },
	spam: { label: __( 'Marked as spam', 'modern-dashboard' ), tone: 'bad' },
	deleted: {
		label: __( 'Marked as deleted', 'modern-dashboard' ),
		tone: 'bad',
	},
	collection_error: {
		label: __( 'Collection failed', 'modern-dashboard' ),
		tone: 'bad',
	},
} );

export const intervalLabels = () => ( {
	mdash_quarter_hourly: __( 'Every 15 minutes', 'modern-dashboard' ),
	hourly: __( 'Hourly', 'modern-dashboard' ),
	twicedaily: __( 'Twice daily', 'modern-dashboard' ),
	daily: __( 'Daily', 'modern-dashboard' ),
} );

export const cardLabels = () => ( {
	sites: __( 'Sites', 'modern-dashboard' ),
	users: __( 'Users', 'modern-dashboard' ),
	content: __( 'Content', 'modern-dashboard' ),
	updates: __( 'Updates', 'modern-dashboard' ),
	storage: __( 'Storage', 'modern-dashboard' ),
	attention: __( 'Needs attention', 'modern-dashboard' ),
} );
