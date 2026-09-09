#!/usr/bin/env bash
#
# Prepare a wp-env Multisite for testing.
#
# `wp-env` activates listed plugins on the main site only. This plugin refuses
# to run unless it is *network*-activated, so that has to happen explicitly.
# A few extra sites are created too — a one-site network exercises almost none
# of the interesting code paths.
#
# Usage: npm run env:setup   (after `npm run env:start`)

set -euo pipefail

# `--quiet` confuses argument parsing in current wp-env, which then reads the
# container name as empty; keep the plain form and mute it here instead.
wp() {
	npx --no-install wp-env run cli wp "$@" 2>/dev/null
}

echo "→ Network-activating the plugin"
wp plugin activate modern-dashboard --network

echo "→ Creating test sites"
for slug in alpha beta gamma; do
	title="$(printf '%s' "${slug:0:1}" | tr '[:lower:]' '[:upper:]')${slug:1}"

	# Idempotent: re-running the script must not fail on sites that exist.
	if wp site list --field=url | grep -q "/${slug}/"; then
		echo "   ${slug}: already exists"
	else
		wp site create --slug="${slug}" --title="${title}"
	fi
done

echo "→ Seeding a little content so the numbers are not all zero"
for slug in alpha beta; do
	url="$(wp site list --field=url | grep "/${slug}/" | head -1)"
	wp post generate --count=5 --url="${url}" >/dev/null 2>&1 || true
done

# Distinctive titles, one per site, so a search result proves which site it came
# from rather than looking plausible.
alpha_url="$(wp site list --field=url | grep '/alpha/' | head -1)"
beta_url="$(wp site list --field=url | grep '/beta/' | head -1)"

wp post create --post_title="Alpha confidential roadmap" --post_status=publish --url="${alpha_url}" >/dev/null 2>&1 || true
wp post create --post_title="Beta quarterly planning" --post_status=publish --url="${beta_url}" >/dev/null 2>&1 || true

# A draft on alpha, authored by the super admin: the palette must not show its
# title to anyone who cannot edit it.
wp post create --post_title="Alpha unpublished salary review" --post_status=draft --url="${alpha_url}" >/dev/null 2>&1 || true

echo "→ Creating a site administrator on beta only"
# The point of this user: they are NOT a super admin and belong to exactly one
# site, which is what the cross-tenant checks depend on.
# WP-CLI has no --role=none, so create the account, grant administrator on beta,
# then drop the main-site role: the point is a user who belongs to exactly one
# site, which is what the cross-tenant checks rest on.
if ! wp user get betaadmin --field=user_login >/dev/null 2>&1; then
	wp user create betaadmin betaadmin@example.com --role=subscriber --user_pass=password >/dev/null
fi
wp user set-role betaadmin administrator --url="${beta_url}" >/dev/null 2>&1 || true
wp user remove-role betaadmin --url="$(wp site list --field=url | head -1)" >/dev/null 2>&1 || true

echo "→ Collecting metrics and building the search index"
wp cron event run modern_dashboard_refresh_batch >/dev/null 2>&1 || true

echo
wp site list --fields=blog_id,url,blogname
echo
echo "Network dashboard: http://localhost:8888/wp-admin/network/admin.php?page=modern-dashboard"
echo
echo "  admin / password        super admin, sees everything"
echo "  betaadmin / password    administrator on beta ONLY — use this one to"
echo "                          check that alpha's content stays invisible"
echo
echo "The command palette is off by default. Turn it on under"
echo "Network Dashboard → Settings → Access, then press Cmd/Ctrl+K."
