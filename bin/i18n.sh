#!/usr/bin/env bash
# Regenerates languages/mg-stealth.pot (WP-CLI in the dev container), the Polish .po and its .mo.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
docker compose -f "$ROOT/docker-compose.yml" run --rm -T -v "$ROOT/languages:/out" cli \
	wp i18n make-pot /var/www/html/wp-content/plugins/mg-stealth /out/mg-stealth.pot \
	--slug=mg-stealth --domain=mg-stealth --exclude=lib,vendor,tests,docker,bin,build \
	--headers='{"Report-Msgid-Bugs-To":"https://gitlab.com/mgraphics/mg-stealth/-/issues","Last-Translator":"MGraphics","Language-Team":"MGraphics"}'
python3 "$ROOT/bin/translations-pl.py"
msgfmt --check -o "$ROOT/languages/mg-stealth-pl_PL.mo" "$ROOT/languages/mg-stealth-pl_PL.po"
msgfmt --statistics -o /dev/null "$ROOT/languages/mg-stealth-pl_PL.po"
