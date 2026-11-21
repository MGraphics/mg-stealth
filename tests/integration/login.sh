#!/usr/bin/env bash
# Private login address and the lockout after failed logins, as a stranger and as the owner see it.
# Usage: [APP_PASS=<application password>] tests/integration/login.sh <base-url> <login-slug> <user> <password> [--lockout <retries>]
# The site must have the private login on with <login-slug> (and, with --lockout, the lockout on
# with <retries> allowed attempts and the test address not on the allow-list).
# Prints one line per check and exits 1 when any check fails.
set -uo pipefail
BASE="${1%/}"
SLUG="$2"
USER_NAME="$3"
PASS="$4"
RETRIES=""
[ "${5:-}" = "--lockout" ] && RETRIES="${6:-4}"
UA="Mozilla/5.0 (probe)"
JAR="$(mktemp)"
trap 'rm -f "$JAR" "$JAR.body"' EXIT
FAILED=0

check() { # name expected actual
	if [ "$2" = "$3" ]; then printf 'ok    %s (%s)\n' "$1" "$3"; else printf 'FAIL  %s: expected %s, got %s\n' "$1" "$2" "$3"; FAILED=1; fi
}
code() { curl -s -A "$UA" -o "$JAR.body" -w '%{http_code}' "$@"; }

ref_len="$(curl -s -A "$UA" "$BASE/mgs-reference-$RANDOM$RANDOM/" | wc -c | tr -d ' ')"
is_404_page() { # path → yes/no (status 404 and the size of the normal 404 page)
	local c len diff
	c="$(code "$BASE$1")"
	len="$(wc -c <"$JAR.body" | tr -d ' ')"
	diff=$((len - ref_len)); diff=${diff#-}
	if [ "$c" = 404 ] && [ "$diff" -le 120 ]; then echo yes; else echo "no:$c"; fi
}

# 1. The private address shows the login form; the usual addresses are the normal 404 page.
c="$(code "$BASE/$SLUG/")"
check "GET /$SLUG/ answers 200" 200 "$c"
check "GET /$SLUG/ shows the login form" yes "$(grep -q 'id="loginform"' "$JAR.body" && echo yes || echo no)"
check "GET /$SLUG/ does not name wp-login.php" 0 "$(grep -c 'wp-login\.php' "$JAR.body")"
c="$(code "$BASE/$SLUG")"
check "GET /$SLUG (no slash) answers 200" 200 "$c"
for p in /wp-login.php '/wp-login.php?action=lostpassword' /wp-admin/ /wp-admin /login/ /login /admin/ /admin /dashboard/ /dashboard; do
	case "$p" in "/$SLUG" | "/$SLUG/") continue ;; esac
	check "GET $p is the normal 404 page" yes "$(is_404_page "$p")"
done

# 2. Signing in at the private address opens the admin area.
login() { # user password → status of the POST (cookies in $JAR)
	curl -s -A "$UA" -c "$JAR" -b "$JAR" -o "$JAR.body" -w '%{http_code} %{redirect_url}' \
		--data-urlencode "log=$1" --data-urlencode "pwd=$2" --data-urlencode "wp-submit=Log In" \
		--data-urlencode "redirect_to=$BASE/wp-admin/" --data-urlencode "testcookie=1" \
		-b "wordpress_test_cookie=WP%20Cookie%20check" "$BASE/$SLUG/"
}
attempt() { : >"$JAR"; login "$1" "$2"; }

if [ -n "$RETRIES" ]; then
	# 3. Failed attempts: one message for every failure, then the lockout (even for the right password).
	r="$(attempt "$USER_NAME" "wrong-$RANDOM")"
	check "wrong password stays on the form" 200 "${r%% *}"
	msg_pw="$(grep -o 'The user name or password is incorrect\|Nazwa użytkownika lub hasło są nieprawidłowe' "$JAR.body" | head -1)"
	r="$(attempt "nobody-$RANDOM" "wrong-$RANDOM")"
	msg_user="$(grep -o 'The user name or password is incorrect\|Nazwa użytkownika lub hasło są nieprawidłowe' "$JAR.body" | head -1)"
	check "unknown user and wrong password get the same message" "yes" "$([ -n "$msg_pw" ] && [ "$msg_pw" = "$msg_user" ] && echo yes || echo no)"
	check "the message does not name the user" 0 "$(grep -c "$USER_NAME</strong>" "$JAR.body")"
	for _ in $(seq 3 "$RETRIES"); do attempt "$USER_NAME" "wrong-$RANDOM" >/dev/null; done
	r="$(attempt "$USER_NAME" "$PASS")"
	check "right password during the lockout is refused" 200 "${r%% *}"
	check "the lockout message is shown" yes "$(grep -q 'Too many failed login attempts\|Zbyt wiele nieudanych prób logowania' "$JAR.body" && echo yes || echo no)"
	check "the lockout page does not name WordPress" 0 "$(grep -c -i 'wordpress\.org\|Powered by WordPress' "$JAR.body")"
	if [ -n "${APP_PASS:-}" ]; then
		c="$(curl -s -A "$UA" -o /dev/null -w '%{http_code}' -u "$USER_NAME:$APP_PASS" "$BASE/?rest_route=/wp/v2/users/me")"
		# 401, or the normal 404 page when the REST API is closed for guests.
		check "REST with a valid application password during the lockout is refused" yes "$([ "$c" = 401 ] || [ "$c" = 404 ] && echo yes || echo "no:$c")"
	fi
	echo "LOCKED"
	exit "$FAILED"
fi

r="$(attempt "$USER_NAME" "$PASS")"
check "POST /$SLUG/ with the right password redirects" 302 "${r%% *}"
check "… to the admin area" "$BASE/wp-admin/" "${r#* }"
c="$(curl -s -A "$UA" -b "$JAR" -o "$JAR.body" -w '%{http_code}' "$BASE/wp-admin/")"
check "GET /wp-admin/ signed in answers 200" 200 "$c"
check "… with the dashboard" yes "$(grep -q 'id="wpadminbar"' "$JAR.body" && echo yes || echo no)"
logout="$(grep -o "href=[\"'][^\"']*action=logout[^\"']*" "$JAR.body" | head -1 | sed -E "s/^href=[\"']//")"
if [ "${logout#"$BASE/$SLUG/?"}" != "$logout" ]; then uses=yes; else uses="no:$logout"; fi
check "the logout link uses the private address" yes "$uses"
exit "$FAILED"
