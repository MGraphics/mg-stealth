#!/usr/bin/env bash
# Builds the release packages into build/:
#   mg-stealth-<version>.zip         GitHub release (with the update checker, Update URI header)
#   mg-stealth-<version>-wporg.zip   WordPress.org (no update checker, no Update URI)
# Both unpack to a folder named mg-stealth/.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(sed -n 's/^ \* Version: *//p' "$ROOT/mg-stealth.php" | head -1 | tr -d '[:space:]')"
STABLE="$(sed -n 's/^Stable tag: *//p' "$ROOT/readme.txt" | head -1 | tr -d '[:space:]')"
CONST="$(sed -n "s/^define( 'MG_STEALTH_VERSION', '\([^']*\)' );/\1/p" "$ROOT/mg-stealth.php")"
if [ "$VERSION" != "$STABLE" ] || [ "$VERSION" != "$CONST" ]; then
	echo "Version mismatch: header $VERSION, readme Stable tag $STABLE, MG_STEALTH_VERSION $CONST" >&2
	exit 1
fi
OUT="$ROOT/build"
rm -rf "$OUT"
mkdir -p "$OUT/github" "$OUT/wporg"

rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$OUT/github/mg-stealth/"
rsync -a "$OUT/github/mg-stealth/" "$OUT/wporg/mg-stealth/"

# WordPress.org: updates come from WordPress.org itself.
WP="$OUT/wporg/mg-stealth"
rm -rf "$WP/lib" "$WP/includes/class-updater.php"
sed -i.bak -e '/^ \* Update URI:/d' -e '/github-build:start/,/github-build:end/d' "$WP/mg-stealth.php"
rm -f "$WP/mg-stealth.php.bak"
if grep -R -n -i -E 'update uri|plugin-update-checker|PucFactory|github-build' "$WP" --include='*.php'; then
	echo "The WordPress.org build still contains update-checker code." >&2
	exit 1
fi

( cd "$OUT/github" && zip -qr "../mg-stealth-$VERSION.zip" mg-stealth )
( cd "$OUT/wporg" && zip -qr "../mg-stealth-$VERSION-wporg.zip" mg-stealth )
echo "$OUT/mg-stealth-$VERSION.zip"
echo "$OUT/mg-stealth-$VERSION-wporg.zip"
