=== Modern Dashboard ===
Contributors: amoody8
Tags: multisite, network, dashboard, admin, management
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A network-first admin dashboard for WordPress Multisite. See every site in your network in one place.

== Description ==

Most admin dashboard plugins are built for a single site. On a Multisite network that leaves you with one dashboard per site and no view of the whole install. Modern Dashboard starts from the network instead: sites are rows in it, and the network admin controls what everyone sees.

= Network overview =

Sites, unique users, content, pending updates, uploads size and an attention list, aggregated across every site.

= Site list =

Every site with its users, content, pending updates, uploads size, last published post and data age. Sort on any column. Filter by site status or by state — needs attention, has updates, inactive, stale data. Search by name or address.

= Site drill-down =

User counts by role, content and comment breakdown, active theme and version, the specific plugins that are out of date on that site, and uploads size. Refresh any single site on demand.

= Built for large networks =

Metrics are never collected during a page load. A background job refreshes the stalest sites a batch at a time, so a ten-site network and a ten-thousand-site network cost the same per request. Batch size, interval and the per-site storage scan budget are all configurable.

= Network-controlled =

The network admin sets the collection schedule, which overview cards appear, which sites are excluded, and whether site administrators may see their own site's numbers. Individual sites inherit those decisions.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/modern-dashboard`.
2. Network-activate it in **Network Admin → Plugins**.
3. Open **Network Dashboard** in the network admin menu.

The plugin requires a Multisite install and must be network-activated; it will tell you in an admin notice if either is not the case.

== Frequently Asked Questions ==

= Does it work on a single-site install? =

No. It is built around network-level data and refuses to run outside Multisite rather than pretending to work.

= Will it slow down a large network? =

Collection runs on WP-Cron in batches, never during a page load, and overlapping batches are prevented. The most expensive part — measuring uploads directory size — is bounded per site and can be turned off.

= What happens if WP-Cron is disabled? =

Data will not refresh automatically. Use the "Collect a batch now" button, or point a system cron at `wp-cron.php`.

= Can site administrators use it? =

Only if you let them. When enabled, a site administrator sees a read-only view of their own site under **Dashboard → Site Metrics** and nothing about the rest of the network.

== Changelog ==

= 0.1.0 =
* Initial release: network overview, site list with drill-down, batched background collection, and network-controlled settings.
