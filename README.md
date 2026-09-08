# Modern Dashboard

A network-first admin dashboard for WordPress Multisite.

UiPress-style dashboards are built for a single site. On a network you end up
with one dashboard per site and no way to see the whole install at once. This
plugin starts from the opposite end: the network is the primary unit, and
individual sites are rows in it.

This is an original implementation. It shares goals with UiPress but no code,
markup, or assets.

## Status

**v0.4.0 — all four pillars.** Metrics collection, the network overview, the
site list with drill-down, network-controlled settings, a drag-and-drop
dashboard builder whose templates are assigned by role, a network-defined admin
menu editor, and branding for the admin chrome and login screen. What remains is
depth rather than new pillars; see [Roadmap](#roadmap).

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

**Dashboard builder.** Compose the overview from blocks — statistics, a ranked
bar chart, the attention list, a site list, network facts, headings and notes —
by dragging them into place and setting each one's width. Templates are stored
at the network level and assigned per role, with a default for everyone else.

**Menu editor.** Reorder, rename and hide admin menu items per role, defined
once at the network level and applied on every site. Submenus too.

**Branding.** Admin chrome colours, login screen, and white-label text (footer,
greeting, WordPress logo), set network-wide with per-site overrides. Includes a
live preview and WCAG contrast warnings on every foreground/background pair.

**Network-controlled settings.** Collection interval and batch size, storage
scanning on/off with a per-site time budget, staleness and inactivity
thresholds, excluded sites, and whether site administrators may see their own
site's numbers.

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

### Templates are a flow, not a free grid

A block has a **width in twelfths** and blocks flow left to right; dragging
reorders them. It would have been showier to give every block an x/y position on
a canvas, but a freely positioned grid buys its looks with permanent complexity:
breakpoint rules, overlap resolution, and a collapse order that has to be
authored separately for every layout. Width spans give real control and stay
correct on a phone, so the responsive behaviour is a property of the model rather
than a pile of special cases. Narrow screens fall back to a two-column grid where
wide blocks stay wide and small ones pair up.

The registry is the single authority on what a block is. The palette, the
inspector's controls and the server-side validation are all generated from the
same definition, so a block type added through the `modern_dashboard_blocks`
filter gets a working editor without a line of JavaScript.

Nothing arriving from the browser is trusted: unknown block types are dropped,
widths are clamped to what each block declares it can handle, unknown settings
keys are discarded, and every value is coerced to the type its schema declares.
The builder's canvas and the live dashboard share one renderer, so what an editor
arranges is literally what a viewer gets — there is no second implementation to
drift.

### Charts

The one chart type is a ranked horizontal bar chart, drawn in plain HTML rather
than pulled from a charting library — one form, done properly, costs less than a
dependency. It is a single series, so it carries no legend (the title names what
is plotted) and no gridlines (every bar is labelled with its value at the tip).
A table view is one click away for anyone who wants the numbers directly.

The bar colour was validated rather than eyeballed, which caught a real problem:
the plugin's WordPress-admin accent `#2271b1` passes contrast on the light
surface but measures **2.88:1** on the dark one, under the 3:1 floor. Dark mode
therefore steps to `#3987e5`. Both live in `--md-chart-bar`.

### The menu catalogue is observed, not enumerated

WordPress only knows a site's admin menu while that site is rendering an admin
page: `$menu` is assembled by whichever plugins are active there, so it cannot be
read for another site through `switch_to_blog()`. There is no API that answers
"what is in site 47's menu?" from outside site 47.

So the catalogue is built by observation. Every admin page load contributes what
that site has, merged into one network-level record keyed by slug — which means
it grows with the number of distinct menu items (tens) rather than the number of
sites (possibly thousands). Writes only happen when the menu's signature actually
changes, so the common case is a read. Merging is additive and converges: a lost
concurrent write is repaired by the next page load. Items unseen for 30 days are
pruned, so uninstalled plugins fade out on their own.

The consequence worth knowing: a freshly installed network shows an empty
catalogue until somebody visits a site's admin. The editor says so rather than
looking broken.

### Menu rules cannot lock you out

A menu editor can hide the screen you would use to undo your mistake. Three
safeguards, in order of how much they matter:

1. **Network admin screens are never modified.** This editor and the network
   dashboard are always reachable, whatever the rules say.
2. **Super administrators are exempt by default.** Switchable, but on unless you
   turn it off.
3. **`?mdash-menu=off` restores the untouched menu** for anyone who can
   `manage_options` — the recovery route for a site administrator given rules
   that hide too much. An admin notice says when it is active.

**Hiding a menu item is cosmetic.** It does not revoke a capability, and the page
stays reachable by URL for anyone whose role allows it. This is a tidying tool,
not an access-control one — use roles and capabilities for that. The editor says
this on screen too, because it is the kind of thing people assume the other way
round.

Renames are escaped on the way into the menu. WordPress treats menu titles as
trusted markup authored by plugin code and prints them without escaping, so a
stored label would otherwise be an injection point.

### Branding validates colours, and checks they can be read

Every colour ends up interpolated into a stylesheet, so `sanitize_hex_color()`
is the gate: anything that is not hex falls back to its default rather than
being passed through. Logos are restricted to `http`/`https` — no `javascript:`,
no `data:`. Colours are re-validated at render time as well as on save, because
that is where a string actually becomes CSS and an option can be changed by
other means (WP-CLI, a migration, another plugin).

The logo URL gets its own escaper. `esc_url()` is an *HTML* escaper — it encodes
ampersands, which corrupts any URL with a query string once it is inside a CSS
`url()`. So the raw escaper restricts the protocol, and the characters that could
terminate the `url()` or the surrounding `<style>` are stripped.

Renames of core strings reuse core's own text domain deliberately: the greeting
replacement matches on `__( 'Howdy,', 'default' )` so it still finds the string
in a non-English locale.

The editor computes **WCAG contrast** for each foreground/background pair as you
pick colours, and says which fail. Colour pickers show you two swatches; they say
nothing about whether the result is readable, and a theming tool that lets you
build an unreadable admin without comment is not finished. Below 4.5:1 is flagged
as under the body-text threshold; below 3:1 is called what it is.

An override **replaces** the network theme rather than merging with it. Merging
needs a per-field "is this overridden?" flag and produces half-inherited themes
that are hard to reason about; "this site has its own branding" is a state people
can hold in their head. A consequence worth knowing: an override with branding
switched *off* means "no branding on this site", not "fall back to the network".

As with menus, `?mdash-theme=off` renders the unbranded admin for anyone who can
`manage_options`, so a colour scheme nobody can read is always recoverable.

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
  Builder/
    BlockRegistry.php         Block catalogue: labels, schemas, width bounds
    LayoutSanitizer.php       Validates untrusted templates against the registry
    TemplateRepository.php    Template storage, role assignment, resolution
  Menu/
    MenuCatalogue.php         Observes admin menus and merges them network-wide
    MenuRules.php             Per-role hide/rename/order rules
    MenuApplier.php           Applies rules, with the lockout safeguards
  Theme/
    Theme.php                 Theme shape, defaults, strict sanitization
    ThemeRepository.php       Network theme and per-site overrides
    ThemeRenderer.php         Admin CSS, login CSS, white-label hooks
  Data/
    Store.php                 Site meta or network options, plus the staleness index
    SiteCollector.php         switch_to_blog orchestration
    MetricsRepository.php     Read, refresh, filter, sort, paginate
    NetworkAggregator.php     Network rollup
    Collectors/               Content, Users, Updates, Storage
  Cron/Scheduler.php          Batched background refresh
  Rest/Routes.php             modern-dashboard/v1
  Rest/BuilderRoutes.php      Builder endpoints in the same namespace
  Rest/MenuRoutes.php         Menu catalogue and rules endpoints
  Rest/ThemeRoutes.php        Branding endpoints
  Admin/                      Menu pages and asset loading
assets/src/                   React app (source)
  blocks/                     One renderer per block type
  builder/                    Canvas, palette, inspector, role assignment
  menu/                       Menu editor
  theme/                      Branding editor, colour fields, preview
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
| `/blocks` | GET | The block registry, for the palette and inspector |
| `/templates` | GET / POST | List templates and assignments; create or replace one |
| `/templates/active` | GET | The template the current user should see |
| `/templates/{id}` | GET / DELETE | Read or remove one template |
| `/templates/assignments` | POST | Set the role map and the default template |
| `/menu` | GET / POST | The observed menu catalogue and the per-role rules |
| `/menu/catalogue` | DELETE | Clear the catalogue so it rebuilds from scratch |
| `/theme` | GET / POST | The network-wide branding |
| `/theme/site/{id}` | GET / POST / DELETE | One site's branding override |

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

Blocks are registered the same way. Supply a `label`, a `category`, `defaults`,
and a `settings` map describing each setting, and the palette and inspector build
themselves:

```php
add_filter( 'modern_dashboard_blocks', function ( array $blocks ): array {
	$blocks['orders'] = array(
		'label'         => 'Orders',
		'category'      => 'data',
		'min_width'     => 3,
		'max_width'     => 12,
		'default_width' => 4,
		'defaults'      => array( 'range' => '7d' ),
		'settings'      => array(
			'range' => array(
				'label'   => 'Range',
				'type'    => 'select',
				'options' => array(
					array( 'value' => '7d', 'label' => 'Last 7 days' ),
					array( 'value' => '30d', 'label' => 'Last 30 days' ),
				),
			),
		),
	);

	return $blocks;
} );
```

The matching React renderer is registered in `assets/src/blocks/index.js`. A type
with no renderer shows a visible placeholder rather than empty space — a missing
renderer should be obvious, not silent.

## Testing it

The plugin needs PHP, a database, and a **network-activated Multisite** install.
Static hosting (GitHub Pages and friends) cannot run it — but Pages can host the
zip that boots it in a browser-based WordPress, which is what the Playground
option below does.

### Local Multisite (recommended)

`wp-env` is WordPress's own Docker-based environment. The config here sets
`multisite: true`, and the setup script network-activates the plugin and creates
a few sites — one-site networks exercise almost none of the interesting paths.

```bash
npm install
npm run build
npm run env:start     # boots WordPress in Docker
npm run env:setup     # network-activates the plugin, creates 3 test sites
```

Then open <http://localhost:8888/wp-admin/network/admin.php?page=modern-dashboard>
and log in as `admin` / `password`. `npm run env:destroy` throws it all away.

`wp-env` activates listed plugins on the main site only, which is why the setup
script exists: this plugin refuses to run unless it is *network*-activated, and
says so in an admin notice.

### In the browser, with no install at all

[WordPress Playground](https://playground.wordpress.net) runs WordPress in the
browser — PHP compiled to WebAssembly, SQLite instead of MySQL — and its
`enableMultisite` step makes a network. `.playground/blueprint.json` installs
this plugin from a published zip, network-activates it and creates three sites.
The Pages workflow publishes the zip, the Blueprint and a launch page, so a
single link boots the whole thing.

Enable it under **Settings → Pages → Source: GitHub Actions**; the workflow does
the rest.

**What Playground proves, and what it does not.** It exercises the UI, the REST
API and the plugin's wiring end to end, which is genuinely useful. It cannot
tell you about:

- **scale** — three sites is not three thousand, and the batching, caching and
  query-priming exist entirely for the second case;
- **real menus** — the menu catalogue only ever sees the plugins installed there;
- **scheduled collection** — there is no real cron, so use *Collect a batch now*;
- **storage** — directory sizes on a virtual filesystem are meaningless.

Treat it as a demo and a UI test, not as evidence the plugin is production-ready
on a real network.

### What to check first, in order

Branding and menu rules change what other people see, so prove the read-only
parts before switching either on.

1. **Collection.** Press *Collect a batch now*. Every site should get numbers,
   and *Data freshness* should stop reporting uncollected sites.
2. **Drill-down.** Open a site from the list. Check the plugin/theme figures
   against that site's own Plugins screen.
3. **Builder.** Rearrange the overview, save, reload. The layout should persist
   and the Overview tab should match the canvas.
4. **Menus** — with a throwaway role first. Hide something for `editor`, log in
   as an editor, confirm it is gone and that `?mdash-menu=off` brings it back.
5. **Branding** — last, and check `?mdash-theme=off` works *before* you rely on
   it.

## Development

```bash
npm install
npm run build          # production build into build/
npm run start          # watch mode
npm run lint:js        # ESLint + Prettier (@wordpress/scripts)
npm run format         # autoformat

npm test               # node --test: contrast maths
npm run env:start      # WordPress Multisite in Docker (see Testing it)
npm run env:cli -- ...  # run WP-CLI against it

composer install
composer lint          # phpcs (WordPress-Extra + Docs + PHPCompatibility)
composer lint:fix      # phpcbf
composer test          # PHP smoke tests, no WordPress install needed
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
- **The builder governs the network dashboard only.** It does not yet take over
  each site's own `index.php` dashboard — that is a bigger, riskier change and is
  deliberately separate.
- **Blocks are reordered, not freely positioned.** See the note above; this is a
  deliberate trade, not a missing feature.
- **The menu catalogue starts empty** and fills in as admin pages are visited.
  There is no way to enumerate another site's menu ahead of time; see above.
- **Menu hiding is cosmetic, not a permission boundary.** Stated here because it
  is the most likely thing to be misread as security.
- **A custom menu order drops separators.** Their positions stop meaning anything
  once the items around them have moved.
- **Branding is colours and text, not layout.** It restyles the existing admin
  chrome; it does not move or restructure it.
- **A per-site branding override replaces the network theme**, it does not merge
  with it. See the note above.

## Roadmap

Ordered by what a network actually needs next, not by UiPress feature order.

1. **Per-site dashboards** — let a network template replace each site's own
   `index.php`, so site admins land on a dashboard the network authored.
2. **Historical trends** — the collector already timestamps everything; keeping
   snapshots turns the current point-in-time numbers into graphs, and gives the
   chart block something to plot over time.
3. **Bulk actions** — act on filtered site sets (update plugins, archive
   inactive sites) from the site list.
4. **Branding presets** — save a colour scheme once and apply it to a set of
   sites, rather than re-entering it per site.

## Licence

GPL-2.0-or-later.
