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

wp() {
	npx --no-install wp-env run --quiet cli wp "$@"
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

echo
wp site list --fields=blog_id,url,blogname
echo
echo "Network dashboard: http://localhost:8888/wp-admin/network/admin.php?page=modern-dashboard"
echo "Log in as admin / password"
