#!/usr/bin/env bash
# Redirect matrix against the local Apache: production host names sent in the Host header,
# https simulated with proxy headers (X-Forwarded-Proto, CF-Visitor). No redirects are followed.
# Usage: tests/integration/redirects.sh <server-url> <main-host> [local-host]
set -uo pipefail
SERVER="${1%/}"
MAIN="$2"
LOCAL="${3:-127.0.0.1:8476}"
BARE="${MAIN#www.}"
printf 'host\tproxy\tpath\tstatus\tlocation\n'
probe() {
	local host="$1" proxy="$2" path="$3"
	local args=(-s -o /dev/null -w '%{http_code}\t%{redirect_url}' -H "Host: $host")
	case "$proxy" in
		xfp) args+=(-H 'X-Forwarded-Proto: https') ;;
		cf-https) args+=(-H 'CF-Visitor: {"scheme":"https"}') ;;
		cf-http) args+=(-H 'CF-Visitor: {"scheme":"http"}') ;;
	esac
	printf '%s\t%s\t%s\t%s\n' "$host" "$proxy" "$path" "$(curl "${args[@]}" "$SERVER$path")"
}
for path in / '/kontakt/?utm_source=fb&x=a%20b' '/zaz%C3%B3%C5%82%C4%87/?q=%C5%BC' /kit/css/dashicons.min.css; do
	for host in "$BARE" "www.$BARE"; do
		for proxy in none xfp cf-https cf-http; do
			probe "$host" "$proxy" "$path"
		done
	done
done
probe "WWW.$(echo "$BARE" | tr '[:lower:]' '[:upper:]')" none /
probe "$BARE." none /a
probe "www.$BARE" none /.well-known/acme-challenge/real-token
probe "$BARE" none /.well-known/acme-challenge/real-token
probe "$LOCAL" none /
probe "$BARE.example.org" none /
probe "inna-domena.pl" none /wp-includes/css/dashicons.min.css
