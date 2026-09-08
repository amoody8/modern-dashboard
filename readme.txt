=== Modern Dashboard ===
Contributors: amoody8
Tags: multisite, network, dashboard, admin, white-label
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.0
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

= Dashboard builder =

Compose the overview from blocks — statistics, a ranked bar chart, the attention list, a site list, network facts, headings and notes. Drag them into place, set each one's width, and save. Blocks flow across a twelve-column grid, so a layout that looks right on a desktop still reads correctly on a phone.

= Menu editor =

Reorder, rename and hide admin menu items — top level and submenus — per role, defined once at the network level and applied on every site.

Hiding a menu item tidies the screen; it does not revoke a capability, and the page stays reachable by URL. Use roles and capabilities for access control. Network admin screens are never modified, super administrators are exempt by default, and any administrator can add ?mdash-menu=off to an admin URL to see the untouched menu, so a bad rule can always be undone.

= Branding =

Set the admin menu and toolbar colours, the login screen, and white-label text — the admin footer, the "Howdy" greeting, and the WordPress logo in the toolbar. Network-wide, with per-site overrides.

The editor shows a live preview and checks the contrast of every text-on-background pair as you choose colours, so an unreadable scheme is caught before you save it rather than after. If one slips through anyway, adding ?mdash-theme=off to any admin URL shows the unbranded admin.

= Network-controlled =

The network admin sets the collection schedule, builds the dashboards, decides which role sees which one, picks which sites are excluded, and chooses whether site administrators may see their own site's numbers. Individual sites inherit those decisions.

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

= Does hiding a menu item stop people reaching the page? =

No. It tidies the menu; the page is still reachable by its URL for anyone whose role allows it. Menu rules are presentation, not permissions.

= Can I brand one site differently from the rest? =

Yes. Pick the site in the Branding tab and give it its own theme. A site override replaces the network branding entirely rather than merging with it, so an override with branding switched off means that site is unbranded — not that it falls back to the network.

= Can site administrators use it? =

Only if you let them. When enabled, a site administrator sees a read-only view of their own site under **Dashboard → Site Metrics** and nothing about the rest of the network.

== Changelog ==

= 0.4.0 =
* Added branding: admin chrome colours, login screen styling, custom login logo, and white-label footer, greeting and toolbar logo.
* Branding is network-wide with per-site overrides; an override replaces the network theme rather than merging with it.
* The editor computes WCAG contrast for each text-on-background pair and flags combinations below the 4.5:1 body-text threshold.
* Colours are validated as hex and logos restricted to http/https, on save and again at render, since these values become CSS.
* ?mdash-theme=off renders the unbranded admin for any administrator, so an unreadable colour scheme is always recoverable.

= 0.3.0 =
* Added the admin menu editor: drag to reorder, rename and hide menu items per role, submenus included, defined at the network level.
* The menu catalogue is assembled by observing admin page loads, since WordPress cannot report another site's menu; it is stored once per network keyed by slug rather than once per site.
* Lockout safeguards: network admin screens are never modified, super administrators are exempt by default, and ?mdash-menu=off restores the untouched menu for any administrator.
* Menu renames are escaped when written into the menu, which WordPress otherwise prints unescaped.

= 0.2.0 =
* Added the dashboard builder: drag-to-reorder blocks on a twelve-column grid, per-block settings, and templates assigned by role.
* Added block types for statistics, a ranked bar chart, the attention list, a site list, network facts, data freshness, headings, notes and spacers.
* Blocks are registerable from PHP through the `modern_dashboard_blocks` filter; the palette, inspector and server-side validation all build themselves from the definition.
* The overview is now whatever template your role is assigned, which replaces the old fixed "visible cards" setting.

= 0.1.0 =
* Initial release: network overview, site list with drill-down, batched background collection, and network-controlled settings.
