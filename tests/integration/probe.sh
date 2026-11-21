#!/usr/bin/env bash
# Anonymous probe of a site, as a stranger sees it (curl, no cookies, no redirects followed).
# Usage: tests/integration/probe.sh <base-url> <output-dir> [extra paths...]
# Writes: codes.tsv (path, status, location, bytes, same-as-404), headers.txt, traces.tsv, assets.tsv.
set -uo pipefail
BASE="${1%/}"
OUT="$2"
shift 2
mkdir -p "$OUT"
UA="Mozilla/5.0 (probe)"

PATHS=(
	/ /o-nas/ /pierwszy-wpis/ /nie-ma-takiej-strony/ /robots.txt /feed/ /comments/feed/ '/?feed=rss2' /pierwszy-wpis/feed/
	/pierwszy-wpis/embed/ /wp-sitemap.xml /sitemap.xml /wp-sitemap-posts-post-1.xml /sitemap-posts-post-1.xml
	/wp-login.php '/wp-login.php?action=lostpassword' /wp-admin/ /wp-admin /wp-admin/admin-ajax.php /wp-admin/admin-post.php
	/wp-admin/install.php /wp-admin/css/login.min.css /wp-admin/images/wordpress-logo.svg
	/xmlrpc.php /readme.html /license.txt /wp-config.php /wp-config-sample.php /wp-load.php /wp-settings.php /wp-blog-header.php
	/wp-cron.php /wp-signup.php /wp-activate.php /wp-trackback.php /wp-links-opml.php /wp-mail.php /wp-comments-post.php
	/wp-json/ /wp-json/wp/v2/users /wp-json/wp/v2/posts '/?rest_route=/' '/?rest_route=/wp/v2/users'
	/api/ /api/wp/v2/users /api/contact-form-7/v1/contact-forms
	'/?author=1' /author/admin/ /author/admin/feed/
	/wp-content/ /wp-content/plugins/ /wp-content/themes/ /wp-content/uploads/ /wp-content/plugins/contact-form-7/readme.txt
	/wp-content/themes/twentytwentyfive/style.css /wp-content/plugins/mg-stealth/readme.txt
	/wp-includes/ /wp-includes/js/jquery/jquery.min.js /wp-includes/css/dashicons.min.css /wp-includes/wlwmanifest.xml
	/login /admin /dashboard /wp-sitemap.xsl '/?p=1' '/?s=test'
	"$@"
)

ref_body="$(curl -s -A "$UA" "$BASE/mgs-reference-$RANDOM$RANDOM/")"
ref_len=${#ref_body}

printf 'path\tstatus\tlocation\tbytes\tsame_as_404\n' >"$OUT/codes.tsv"
for p in "${PATHS[@]}"; do
	tmp="$(mktemp)"
	line="$(curl -s -A "$UA" -o "$tmp" -w '%{http_code}\t%{redirect_url}\t%{size_download}' "$BASE$p")"
	code="${line%%$'\t'*}"
	len="$(wc -c <"$tmp" | tr -d ' ')"
	same=no
	if [ "$code" = 404 ]; then
		diff=$((len - ref_len)); diff=${diff#-}
		[ "$diff" -le 120 ] && same=yes
	fi
	printf '%s\t%s\t%s\n' "$p" "$line" "$same" >>"$OUT/codes.tsv"
	rm -f "$tmp"
done
# POST to XML-RPC.
line="$(curl -s -A "$UA" -o /dev/null -w '%{http_code}\t%{redirect_url}\t%{size_download}' -H 'Content-Type: text/xml' --data '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' "$BASE/xmlrpc.php")"
printf 'POST /xmlrpc.php\t%s\t-\n' "$line" >>"$OUT/codes.tsv"

: >"$OUT/headers.txt"
for p in / /pierwszy-wpis/ /nie-ma-takiej-strony/ /wp-login.php; do
	{ echo "== $p"; curl -s -A "$UA" -D - -o /dev/null "$BASE$p"; } >>"$OUT/headers.txt"
done

NEEDLES=(wordpress wp-content wp-includes wp-json generator 'ver=' xmlrpc wp-emoji api.w.org /author/ wp-admin)
printf 'page\tstatus' >"$OUT/traces.tsv"
for n in "${NEEDLES[@]}"; do printf '\t%s' "$n" >>"$OUT/traces.tsv"; done
printf '\n' >>"$OUT/traces.tsv"
mkdir -p "$OUT/pages"
for p in / /o-nas/ /pierwszy-wpis/ /nie-ma-takiej-strony/ /robots.txt /feed/ /wp-sitemap.xml /sitemap.xml /sitemap-posts-post-1.xml; do
	name="$(echo "$p" | tr '/?=' '___')"
	code="$(curl -s -A "$UA" -D "$OUT/pages/$name.headers" -o "$OUT/pages/$name.body" -w '%{http_code}' "$BASE$p")"
	printf '%s\t%s' "$p" "$code" >>"$OUT/traces.tsv"
	for n in "${NEEDLES[@]}"; do
		c="$(cat "$OUT/pages/$name.headers" "$OUT/pages/$name.body" | grep -o -i -F -- "$n" | wc -l | tr -d ' ')"
		printf '\t%s' "$c" >>"$OUT/traces.tsv"
	done
	printf '\n' >>"$OUT/traces.tsv"
done

# Every same-host asset referenced by the home page and a post must load for a guest.
printf 'asset\tstatus\n' >"$OUT/assets.tsv"
host="$(echo "$BASE" | sed -E 's#^https?://##')"
cat "$OUT/pages/_.body" "$OUT/pages/_pierwszy-wpis_.body" 2>/dev/null |
	grep -oE "(src|href)=[\"'][^\"']+[\"']" | sed -E "s/^(src|href)=[\"']//; s/[\"']$//" |
	sed 's/&#038;/\&/g; s/&amp;/\&/g' |
	grep -E "^(https?:)?//$host/|^/[^/]" | grep -E '\.(css|js|mjs|png|jpe?g|gif|svg|webp|woff2?|ico)(\?|$)' | sort -u |
	while read -r u; do
		case "$u" in //*) u="http:$u" ;; /*) u="$BASE$u" ;; esac
		printf '%s\t%s\n' "${u#"$BASE"}" "$(curl -s -o /dev/null -w '%{http_code}' -A "$UA" "$u")" >>"$OUT/assets.tsv"
	done
echo "probe written to $OUT"
