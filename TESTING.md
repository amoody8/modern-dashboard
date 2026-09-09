# Testing the command palette

Three levels. The first two take a minute; the third is the one that would
actually catch a shipping bug.

## 1. Static checks (no WordPress needed)

```bash
export PATH="$HOME/.nvm/versions/node/v22.21.1/bin:/opt/homebrew/bin:$PATH"

npm test                 # 39 assertions: ranking, dedup, merge
php tests/smoke.php      # 163 assertions: registry, index, gate, tenant boundary
npm run lint:js          # ESLint + Prettier
npm run build            # must succeed and leave build/ unchanged
git diff --exit-code -- build/
```

All of this currently passes.

**`composer lint` has never run here** — Composer is not installed, so phpcs has
not seen the ~1,400 new lines of PHP. Either install it
(`brew install composer`) or let CI be the first to check. It is the most likely
source of a red build.

## 2. WordPress Playground (browser, no install)

Good for a quick look at the UI. It cannot tell you anything about scale, real
cron, or the tenant boundary with more than a toy dataset — treat it as a demo.

## 3. Local Multisite — the one that matters

Docker Desktop must be running. Note its CLI lives in `/usr/local/bin`, which is
not on the default non-interactive PATH — hence the export below.

```bash
export PATH="$HOME/.nvm/versions/node/v22.21.1/bin:/usr/local/bin:/opt/homebrew/bin:$PATH"
npm run env:start
npm run env:setup
```

`env:setup` creates three sites (alpha, beta, gamma), seeds posts with
distinctive titles, runs a collection batch, and creates two users:

| User | Password | Role |
|---|---|---|
| `admin` | `password` | Super admin — sees everything |
| `betaadmin` | `password` | Administrator on **beta only** |

`betaadmin` exists for one reason: to prove alpha's content stays invisible.

Then turn the palette on: **Network Dashboard → Settings → Access → Enable the
command palette**. It is off by default.

### The security checks — these block release

Log in as **betaadmin** (not admin) at
`http://localhost:8888/beta/wp-admin/`.

1. **Cross-tenant content.** Press `Cmd/Ctrl+K`, type `confidential`.
   → **Expect nothing.** "Alpha confidential roadmap" exists on alpha, and this
   user has no business knowing it does. A result here is a release blocker.

2. **Site enumeration.** Type `alpha`.
   → **Expect no site result for alpha.** Only beta should ever appear.

3. **Own content still works.** Type `quarterly`.
   → **Expect "Beta quarterly planning".** If this is missing, live search is
   broken and the feature is pointless for site admins.

4. **Draft leak.** Type `salary`.
   → **Expect nothing.** The draft is on alpha *and* unpublished — two reasons
   it must not appear.

Now log in as **admin** at `http://localhost:8888/wp-admin/network/`.

5. **Cross-network search works.** Type `confidential`.
   → **Expect "Alpha confidential roadmap"**, labelled with its site.

6. **The URL actually navigates.** Click it, or press Enter.
   → **Expect to land on alpha's post editor.** This is the bug review caught:
   URLs were being stored as `null` and results silently did nothing. This click
   is the only real proof the fix works.

### Behaviour checks

7. **Block editor.** Open any post for editing. Press `Cmd/Ctrl+K` with the
   cursor in the title or body.
   → Expect the editor's link dialog or core's palette — **not** this one.
   Then press `Cmd/Ctrl+Shift+P`.
   → Expect this palette to open.

8. **Escape.** On the network dashboard, open a site's detail panel, then open
   the palette over it and press Escape once.
   → Expect the palette to close and the site panel to **stay open**.

9. **Focus.** Open the palette, press Tab several times.
   → Expect focus to stay inside it. Press Escape.
   → Expect focus back on whatever had it before.

10. **Recovery hatch.** Add `?mdash-palette=off` to any admin URL.
    → Expect no palette, and a notice saying so. Check it works in both site and
    network admin.

11. **Kill switch.** In the browser console:
    `localStorage.setItem('mdash-palette','off')`, then reload.
    → Expect no palette, and for that to survive navigation.

12. **The setting.** Turn the palette off in settings.
    → Expect the script to stop loading anywhere. View source and confirm
    `palette.js` is absent.

### Performance check — the one people skip

13. **Cron writes nothing when nothing changed.**

    ```bash
    npm run env:cli -- cron event run modern_dashboard_refresh_batch
    npm run env:cli -- cron event run modern_dashboard_refresh_batch
    ```

    The second run should skip every site: `last_updated` has not moved, so
    indexing returns before it queries. This is where the "cheap on every pass"
    claim actually lives, and it is invisible unless you look.

14. **Dark mode.** Set the admin colour scheme to Modern or Midnight *and* your
    OS to dark. Open the palette.
    → Expect a dark panel, not a white one. The tokens are gated on both
    conditions, so half a setup shows nothing.

## Tearing down

```bash
npm run env:destroy
```
