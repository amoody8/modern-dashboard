/**
 * Binds block metric keys to the network overview payload.
 *
 * Keys here must match `BlockRegistry::metric_options()` on the PHP side — that
 * is the list the block inspector offers, and this is what turns a chosen key
 * into something to render.
 */

import { __ } from '@wordpress/i18n';
import { formatBytes, formatNumber } from './format';

/**
 * @param {string} metric   Metric key from the block's settings.
 * @param {Object} overview Network overview payload.
 * @return {{label: string, value: string, hint: string, tone: string}} Display values.
 */
export function readMetric( metric, overview ) {
	const totals = overview?.totals || {};
	const users = overview?.users || {};
	const updates =
		( totals.plugin_updates || 0 ) + ( totals.theme_updates || 0 );
	const attention = overview?.attention_count || 0;

	const table = {
		sites: {
			label: __( 'Sites', 'modern-dashboard' ),
			value: formatNumber( totals.sites ),
			hint: __( 'In this network', 'modern-dashboard' ),
		},
		public: {
			label: __( 'Public sites', 'modern-dashboard' ),
			value: formatNumber( totals.public ),
			hint: __( 'Visible to the world', 'modern-dashboard' ),
		},
		users_unique: {
			label: __( 'Users', 'modern-dashboard' ),
			value: formatNumber( users.unique ),
			hint: __( 'Distinct people', 'modern-dashboard' ),
		},
		users_memberships: {
			label: __( 'Site memberships', 'modern-dashboard' ),
			value: formatNumber( users.memberships ),
			hint: __( 'Seats across all sites', 'modern-dashboard' ),
		},
		administrators: {
			label: __( 'Administrators', 'modern-dashboard' ),
			value: formatNumber( totals.administrators ),
			hint: __( 'Across all sites', 'modern-dashboard' ),
		},
		content: {
			label: __( 'Content', 'modern-dashboard' ),
			value: formatNumber(
				( totals.posts || 0 ) + ( totals.pages || 0 )
			),
			hint: __( 'Posts and pages', 'modern-dashboard' ),
		},
		posts: {
			label: __( 'Posts', 'modern-dashboard' ),
			value: formatNumber( totals.posts ),
			hint: __( 'Published', 'modern-dashboard' ),
		},
		pages: {
			label: __( 'Pages', 'modern-dashboard' ),
			value: formatNumber( totals.pages ),
			hint: __( 'Published', 'modern-dashboard' ),
		},
		media: {
			label: __( 'Media', 'modern-dashboard' ),
			value: formatNumber( totals.media ),
			hint: __( 'Attachments', 'modern-dashboard' ),
		},
		comments_pending: {
			label: __( 'Awaiting moderation', 'modern-dashboard' ),
			value: formatNumber( totals.comments_pending ),
			hint: __( 'Comments', 'modern-dashboard' ),
			tone: totals.comments_pending > 0 ? 'warn' : 'good',
		},
		updates: {
			label: __( 'Pending updates', 'modern-dashboard' ),
			value: formatNumber( updates ),
			hint: __( 'Plugins and themes', 'modern-dashboard' ),
			tone: updates > 0 ? 'warn' : 'good',
		},
		storage: {
			label: __( 'Uploads', 'modern-dashboard' ),
			value: formatBytes( totals.storage_bytes ),
			hint: totals.storage_partial
				? __( 'Partial — some scans timed out', 'modern-dashboard' )
				: __( 'Across every site', 'modern-dashboard' ),
			tone: totals.storage_partial ? 'warn' : 'neutral',
		},
		attention: {
			label: __( 'Needs attention', 'modern-dashboard' ),
			value: formatNumber( attention ),
			hint:
				attention > 0
					? __(
							'Sites with something outstanding',
							'modern-dashboard'
					  )
					: __( 'Nothing outstanding', 'modern-dashboard' ),
			tone: attention > 0 ? 'warn' : 'good',
		},
	};

	const found = table[ metric ] || {
		label: metric,
		value: '—',
		hint: __( 'Unknown metric', 'modern-dashboard' ),
	};

	return { tone: 'neutral', ...found };
}

/**
 * Turn a site row into a comparable number for the chart and ranked list.
 *
 * @param {Object} site   Site row from the sites endpoint.
 * @param {string} metric Metric key.
 * @return {number} The raw value.
 */
export function readSiteMetric( site, metric ) {
	switch ( metric ) {
		case 'content':
			return site.totals?.content || 0;
		case 'storage':
			return site.totals?.storage || 0;
		case 'updates':
			return site.totals?.updates || 0;
		case 'collected_at':
			return site.collected_at || 0;
		default:
			return site.totals?.users || 0;
	}
}

/**
 * @param {string} metric Metric key.
 * @return {Function} Formatter for values of that metric.
 */
export function siteMetricFormatter( metric ) {
	return metric === 'storage' ? formatBytes : formatNumber;
}

/**
 * @param {string} metric Metric key.
 * @return {string} Human label for the metric.
 */
export function siteMetricLabel( metric ) {
	const labels = {
		users: __( 'Users', 'modern-dashboard' ),
		content: __( 'Content', 'modern-dashboard' ),
		storage: __( 'Uploads', 'modern-dashboard' ),
		updates: __( 'Pending updates', 'modern-dashboard' ),
		collected_at: __( 'Data age', 'modern-dashboard' ),
	};

	return labels[ metric ] || metric;
}
