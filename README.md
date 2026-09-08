# Modern Dashboard

A network-first admin dashboard for WordPress Multisite.

UiPress-style dashboards are built for a single site. On a network you end up
with one dashboard per site and no way to see the whole install at once. This
plugin starts from the opposite end: the network is the primary unit, and
individual sites are rows in it.

This is an original implementation. It shares goals with UiPress but no code,
markup, or assets.

## Status

**v0.1.0 — the network dashboard.** Metrics collection, the network overview,
the site list with drill-down, and network-controlled settings all work. The
drag-and-drop dashboard builder, menu editor and theming layers are not built
yet; see [Roadmap](#roadmap).

## Requirements

| | |
|---|---|
| WordPress | 6.6+ (the `react-jsx-runtime` script handle this build depends on landed in 6.6) |
| PHP | 8.0+ |
| Install type | Multisite, **network-activated** |

The plugin refuses to run outside a network-activated Multisite install and says
why in an admin notice rather than failing silently.

## What it does

**Network overview.** Sites, unique users, content, pending updates, uploads
size and an attention list, aggregated across every site in the network.

**Site list.** Every site with its users, content, pending updates, uploads size,
last published post and data age. Sortable on any column, filterable by site
status (public / archived / spam / deleted) and by state (needs attention, has
updates, inactive, stale data), with search across name and address.

**Site drill-down.** Per-site panel with user counts by role, content and comment
breakdown, active theme and version, the specific plugins that are out of date on
that site, uploads size, and when the data was last collected. Refresh any single
site on demand.

**Network-controlled settings.** Collection interval and batch size, storage
scanning on/off with a per-site time budget, staleness and inactivity
thresholds, which overview cards are visible, excluded sites, and whether site
administrators may see their own site's numbers.

## Architecture

### Collection never happens on a page load

The thing that makes a naive version of this plugin fall over is walking the
whole network on every dashboard view. Instead:

- A cron event (`modern_dashboard_refresh_batch`) refreshes the **N stalest
  sites** per run. Batch size and interval are settings.
- Reads only ever touch the cache. A 10-site network and a 10,000-site network
  cost the same per request; the large one just takes more passes to come around.
- A time-boxed lock stops batches from overlapping, and cannot wedge collection
  permanently if a batch dies mid-run.
- New sites are collected immediately on `wp_initialize_site` rather than waiting
  for a full cycle.

### Storage

Per-site metrics go in **site meta** (`wp_blogmeta`), with a **network option**
fallback for networks that have not run the network upgrade that creates that
table. A separate lightweight index maps blog ID to collection timestamp, so
"which sites are stalest?" is one option read rather than a meta query across the
network. The site list primes the whole meta cache in a single query.

### Multisite semantics, made explicit

- Plugin and theme *files* are network-wide in Multisite, so the update
  transients are too. What varies per site is which of them are **active**. The
  updates collector reports the intersection — updates that actually affect that
  site — rather than a network-wide number repeated on every row.
- Summing per-site user counts double-counts anyone who belongs to several
  sites. The overview reports both: `users.unique` (distinct headcount) and
  `users.memberships` (seats to administer).
- Collection never triggers an update check. That would mean one outbound
  request to wordpress.org per site. It reads what WordPress has already cached.

### Capabilities

Granted dynamically through `user_has_cap`, not written into roles — roles live
per-site in Multisite, so writing them would mean touching every site's options
table on activation and again on every settings change.

- `manage_network_dashboard` — super admins. Read and configure everything.
- `view_modern_dashboard` — site administrators, **only** when the network admin
  has enabled it. Grants their own site's record and nothing else.

### Layout

```
modern-dashboard.php          Bootstrap: header, autoloader, activation hooks
uninstall.php                 Removes settings, metrics, transients, cron
src/
  Plugin.php                  Container and boot sequence
  Autoloader.php              PSR-4 autoloader (no vendor/ needed at runtime)
  Support/
    Requirements.php          PHP / WP / multisite / network-activation gate
    Capabilities.php          Dynamic capability grants
  Settings/Settings.php       Network-wide settings, sanitizer, REST schema
  Data/
    Store.php                 Site meta or network options, plus the staleness index
    SiteCollector.php         switch_to_blog orchestration
    MetricsRepository.php     Read, refresh, filter, sort, paginate
    NetworkAggregator.php     Network rollup
    Collectors/               Content, Users, Updates, Storage
  Cron/Scheduler.php          Batched background refresh
  Rest/Routes.php             modern-dashboard/v1
  Admin/                      Menu pages and asset loading
assets/src/                   React app (source)
build/                        React app (built, committed so the repo installs as-is)
```

### Front end

React via `wp-element` as a webpack external, so the bundle is ~30KB and carries
no React of its own. Deliberately **not** built on `@wordpress/components`: much
of it is `__experimental*` and churns between releases. Only stable React APIs
are used (`createRoot`, hooks, no `defaultProps`), so the app is unaffected by
core's React version moving underneath it. All CSS is scoped under
`.modern-dashboard` to survive WordPress's unscoped admin stylesheet.

## REST API

Namespace `modern-dashboard/v1`. All routes require `manage_network_dashboard`
except `GET /sites/{id}`, which a site administrator may call for their own site
when the network allows it.

| Route | Method | Purpose |
|---|---|---|
| `/overview` | GET | Network rollup. `?fresh=1` bypasses the 2-minute cache |
| `/sites` | GET | Site list: `search`, `status`, `flag`, `orderby`, `order`, `page`, `per_page` |
| `/sites/{id}` | GET | One site's full record |
| `/sites/{id}/refresh` | POST | Collect one site now |
| `/refresh` | POST | Run one batch now |
| `/settings` | GET / POST | Read and write network settings |

## Extending

```php
// Add your own collector.
add_filter( 'modern_dashboard_collectors', function ( array $collectors ): array {
	$collectors[] = new My_WooCommerce_Collector();
	return $collectors;
} );

// Or adjust a site's payload before it is stored.
add_filter( 'modern_dashboard_site_metrics', function ( array $metrics, WP_Site $site ): array {
	$metrics['custom'] = [ 'orders' => my_order_count() ];
	return $metrics;
}, 10, 2 );
```

A collector implements `ModernDashboard\Data\Collectors\Collector` and runs
inside a `switch_to_blog()` context, so it can use ordinary WordPress APIs. A
collector that throws is caught: its key is nulled and the error is recorded on
the site's record, so one bad collector never costs you the rest of the metrics.

`modern_dashboard_batch_complete` fires after each batch with the refreshed IDs.

## Development

```bash
npm install
npm run build          # production build into build/
npm run start          # watch mode
npm run lint:js        # ESLint + Prettier (@wordpress/scripts)
npm run format         # autoformat

composer install
composer lint          # phpcs (WordPress-Extra + Docs + PHPCompatibility)
composer lint:fix      # phpcbf
```

`build/` is committed so the repository can be zipped and installed directly.

## Known limits

- **The site list derives every row on each request.** Filtering and sorting
  happen in PHP over the cached rows. That is fine into the low thousands of
  sites; past that the index should move to a custom table so the database does
  the sorting. The `Store` abstraction is where that change goes.
- **Uploads scanning is the expensive part.** It walks every file in each site's
  uploads directory. It is bounded per site, runs only from cron or an explicit
  refresh, reports partial results honestly when the budget runs out, and can be
  turned off entirely.
- **Cron-dependent.** On a network with `DISABLE_WP_CRON` and no system cron,
  data will not refresh on its own. "Collect a batch now" still works.

## Roadmap

Ordered by what a network actually needs next, not by UiPress feature order.

1. **Dashboard builder** — block canvas, layout schema, templates assigned per
   role, network templates that sites inherit. `dnd-kit` for drag and drop.
2. **Menu editor** — network-defined admin menus with per-role visibility.
3. **Theming / white-label** — admin chrome, colours, login screen, per-site
   branding controlled from the network.
4. **Historical trends** — the collector already timestamps everything; keeping
   snapshots turns the current point-in-time numbers into graphs.
5. **Bulk actions** — act on filtered site sets (update plugins, archive
   inactive sites) from the site list.

## Licence

GPL-2.0-or-later.
