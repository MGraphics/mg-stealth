#!/usr/bin/env bash
# Unpacks a built GitHub package into a fresh WordPress and runs its update check against a
# release fixture with a Markdown body (tests/build/update-check.php).
# Usage: tests/build/update-check.sh <mg-stealth-x.y.z.zip>
# Needs wp-cli and a MySQL/MariaDB server: DB_HOST (127.0.0.1), DB_USER (root), DB_PASS (root),
# DB_NAME (mgs_build_check, created and dropped). MGS_SKIP_CONTENT_CHECK=1 skips the file list check
# (to see how a broken package fails inside WordPress).
set -euo pipefail
ZIP="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"
HERE="$(cd "$(dirname "$0")" && pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}" DB_USER="${DB_USER:-root}" DB_PASS="${DB_PASS:-root}" DB_NAME="${DB_NAME:-mgs_build_check}"
WP="$(mktemp -d)"
sql() { php -r '$db = new mysqli( getenv( "DB_HOST" ), getenv( "DB_USER" ), getenv( "DB_PASS" ) ); $db->query( $argv[1] ) || exit( 1 );' "$1"; }
export DB_HOST DB_USER DB_PASS
trap 'sql "DROP DATABASE IF EXISTS \`$DB_NAME\`" || true; rm -rf "$WP"' EXIT

LIST="$(unzip -l "$ZIP" | awk '{print $NF}')"
[ -n "${MGS_SKIP_CONTENT_CHECK:-}" ] && LIST="$(printf 'mg-stealth/%s\n' lib/plugin-update-checker/vendor/Parsedown.php lib/plugin-update-checker/vendor/PucReadmeParser.php lib/plugin-update-checker/load-v5p7.php)"
for f in lib/plugin-update-checker/vendor/Parsedown.php lib/plugin-update-checker/vendor/PucReadmeParser.php lib/plugin-update-checker/load-v5p7.php; do
	grep -qx "mg-stealth/$f" <<<"$LIST" || { echo "FAIL $f is missing from $ZIP"; exit 1; }
done
for f in vendor/autoload.php tests/unit/bootstrap.php composer.json; do
	if grep -qx "mg-stealth/$f" <<<"$LIST"; then echo "FAIL development file $f is in $ZIP"; exit 1; fi
done

wp core download --path="$WP" --quiet --skip-content
wp config create --path="$WP" --dbhost="$DB_HOST" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbname="$DB_NAME" --skip-check --quiet
sql "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`"
wp core install --path="$WP" --url=http://example.test --title=Build --admin_user=admin --admin_password=x --admin_email=a@example.test --skip-email --quiet
mkdir -p "$WP/wp-content/plugins"
unzip -q "$ZIP" -d "$WP/wp-content/plugins"
wp plugin activate mg-stealth --path="$WP" --quiet
MGS_RELEASE_FIXTURE="$HERE/fixtures/github-release.json" wp eval-file "$HERE/update-check.php" --path="$WP"
