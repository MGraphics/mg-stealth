=== MG Stealth ===
Contributors: mgraphics
Tags: security, hide wordpress, login, rest api, htaccess
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Makes it hard to tell that a site runs WordPress: clean source, neutral paths, private login, closed REST API, 404 for typical WordPress addresses.

== Description ==

MG Stealth removes the usual signs of WordPress from what a visitor (or a scanner) can see: the page source, the HTTP headers and the well-known addresses. Every feature has its own switch, the risky ones start switched off, and deactivating the plugin brings the site back to WordPress' defaults.

**Clean page source and headers**

* No generator tags (of WordPress and of plugins), RSD, wlwmanifest, shortlink, pingback, REST API and oEmbed discovery links.
* No emoji script, no speculation rules listing WordPress folders.
* `?ver=7.1.5` in asset addresses replaced with a short per-site code, so browsers still get fresh files but the version is not shown.
* No `X-Powered-By`, `X-Pingback`, `X-Redirect-By`, REST API `Link` headers and no 1984 `Expires` date.
* WordPress-style tag ids (`wp-block-library-css`), theme body classes and plugin HTML comments removed for visitors.
* Login page and e-mails without WordPress branding.

**Neutral public paths**

* `/wp-content/`, `/wp-includes/`, plugins, uploads, `admin-ajax.php`, `wp-comments-post.php` and `/wp-json/` get neutral names, drawn for each site (so two sites with this plugin do not look alike) and freely editable.
* Plugin and theme folder names can be replaced with short per-site codes.
* The files stay where they are: a managed block in `.htaccess` (Apache, LiteSpeed) maps the names back. The plugin switches the new addresses on only after it has checked that they answer.
* Optional `wp-config.php` snippet and commands for a real move of the folders.

**Private login**

* The login form moves to a private address that is never printed on public pages.
* `wp-login.php` and the admin area (for visitors without a session) answer with the site's normal 404 page.
* Signed-in users who open the login address go straight to the dashboard (or to the profile page, if they cannot edit posts); everyone else gets the login form.
* Any free address works, a random one (the default, e.g. `/k3x9-panel-q8m2/`) or a simple word such as `/login/`; a simple word is saved with a warning, as bots try it first. Addresses already used by a page, post, term, route, real folder or one of the neutral paths are refused.

**Lockout after failed logins**

* After 4 failed sign-ins from one address, further attempts are refused for 20 minutes, even with the right password; after 4 lockouts in a row for 24 hours; counters reset after 12 hours (all adjustable, like Limit Login Attempts Reloaded).
* Covers the login form at the private address, XML-RPC and application passwords of the REST API; optionally counts per user name too.
* The same message for an unknown user and a wrong password; the visitor's address is read from proxy headers only when the request comes from a trusted proxy or Cloudflare.
* Allow-list and deny-list (addresses and CIDR ranges), a log of the last 100 lockouts with manual unlock, optional e-mail to the administrator, optional shortened addresses in the log. Passwords are never stored.
* Can import the settings of Limit Login Attempts Reloaded.

**Typical addresses answer like missing pages**

* REST API closed for guests, with an allow-list (contact forms, shop checkout).
* `xmlrpc.php`, `readme.html`, `license.txt`, `wp-config-sample.php`, `wp-signup.php`, `wp-activate.php`, `wp-trackback.php`, `wp-links-opml.php`, `wp-mail.php`, direct `wp-cron.php` calls, `?author=N`, author archives, embeds and (optionally) feeds: the same 404 page as any address that does not exist, so a probe cannot tell "blocked" from "missing".
* WordPress' own sitemap under `/sitemap.xml` instead of `/wp-sitemap.xml`.

**Redirects**

* Optional one-hop 301 from http to https and between `www.` and the bare domain, path and query string kept, `/.well-known/` excluded, https behind a proxy or Cloudflare recognised.

**Check**

* A "Check" tab asks the site for its pages and the typical WordPress addresses the way a stranger would (no cookies, from the server itself) and lists what still gives WordPress away.

No data is sent anywhere, no credit link is printed, nothing is tracked.

= Po polsku =

MG Stealth utrudnia rozpoznanie, że strona działa na WordPressie: czyści źródło strony i nagłówki, nadaje neutralne nazwy katalogom /wp-content/, /wp-includes/ i /wp-json/, przenosi logowanie pod prywatny adres (losowy albo prosty, np. /login/; zalogowanych kieruje od razu do kokpitu), blokuje logowanie po kilku nieudanych próbach (zamiast Limit Login Attempts Reloaded), zamyka REST API dla gości (z listą wyjątków dla formularzy), a typowe adresy WordPressa (xmlrpc.php, readme.html, ?author=1, wp-cron.php …) odpowiadają zwykłą stroną 404. Opcjonalnie przekierowuje jednym krokiem 301 z http na https i z www na domenę bez www (lub odwrotnie). Zakładka „Sprawdź” pokazuje, co jeszcze zdradza WordPressa. Każdą funkcję można wyłączyć, a wyłączenie wtyczki przywraca ustawienia domyślne. Wtyczka jest w pełni przetłumaczona na polski.

== Installation ==

1. Upload the plugin and activate it (Plugins → Add New → Upload Plugin).
2. Open Settings → MG Stealth. The page source and access rules are on from the start.
3. Optionally switch on the neutral paths (Paths tab), the private login (Login tab) and the redirects (Redirects tab), one at a time.
4. Run the check (Check tab).

When you switch the private login on, save the new address in your bookmarks and password manager.

== Frequently Asked Questions ==

= I lost the login address. =

Use WP-CLI: `wp mg-stealth login-url`. Without shell access add `define( 'MG_STEALTH_DISABLE', true );` to `wp-config.php`: the plugin switches every rule off and `wp-login.php` works again.

= The site or the admin area stopped working. =

Any one of these switches everything off:

* add `define( 'MG_STEALTH_DISABLE', true );` to `wp-config.php` and open any page (the `.htaccess` block is removed too);
* rename the folder `wp-content/plugins/mg-stealth` – the `.htaccess` rules check that the plugin exists and stop at once;
* delete the lines from `# BEGIN MG Stealth` to `# END MG Stealth` in `.htaccess`;
* `wp mg-stealth remove-rules` or `wp plugin deactivate mg-stealth`.

= Can the login address be simply /login/? =

Yes. Type `login` (or `panel`, `admin` …) in the Login tab. `/login/` then shows the login form, while `wp-login.php`, `/wp-admin/` for visitors and the other shortcuts (`/admin/`, `/dashboard/`) stay the normal 404 page. A simple address is easy to guess, so the plugin shows a warning: switch on the lockout after failed logins (Login attempts tab) and use strong passwords.

= I locked myself out after failed logins. =

Wait until the lockout ends, or use WP-CLI: `wp mg-stealth unlock <your IP address>` (or `--all`). `define( 'MG_STEALTH_DISABLE', true );` in `wp-config.php` switches the lockout off together with everything else. Add your fixed address to the allow-list to never be locked out.

= Can it replace Limit Login Attempts Reloaded? =

Yes. The Login attempts tab imports its limits, allow-list and deny-list; then switch the lockout on and deactivate Limit Login Attempts Reloaded (do not run both).

= Does it work on nginx? =

The PHP part does (page source and headers, REST API, private login, authors, XML-RPC, cron, redirects). The neutral paths and the blocking of static files such as `readme.html` need server rules: use the `wp-config.php` snippet for a real move of the folders, or add equivalent nginx rules.

= My contact form stopped sending. =

Forms that post through the REST API need their route on the allow-list (Access tab). Contact Form 7, WPForms, Fluent Forms, Ninja Forms and WooCommerce's store API are on it by default; the tab lists the namespaces present on your site.

= Will search engines notice? =

Pages keep their addresses. Only `/wp-sitemap.xml` moves to `/sitemap.xml` (submit it in Search Console); with an SEO plugin its own sitemap is untouched.

= Can WordPress be hidden completely? =

No. Block class names (`wp-block-…`), the CSS variables of block themes, the header of the theme's `style.css` and cookies after signing in stay, because styles and WordPress itself depend on them. The plugin makes the usual checks (page source, typical addresses, headers) find nothing; it does not promise that no tool can ever recognise the site.

= Does the plugin send data anywhere? =

No. The check only asks your own site. The build distributed on GitHub looks for new versions in the plugin's GitHub repository; the WordPress.org build has no update code of its own.

== Screenshots ==

1. Page source: what is removed for visitors.
2. Neutral paths with names drawn for the site.
3. Access rules and the REST API allow-list.
4. The check: typical addresses and remaining traces.

== Changelog ==

= 1.0.2 =
* Opening the private login address while signed in now goes straight to the dashboard instead of showing the login form again (a 302 that is not cached; to an allowed `redirect_to` address when one is given, to the profile page for users who cannot edit posts, as WordPress does after signing in). Logout, lost password, password reset, registration, protected posts, confirmations, re-authentication (`reauth=1`) and the login window in the admin area work as before. Addresses on the deny-list still get the normal 404 page.

= 1.0.1 =
* The private login address may now be a simple word such as `login`, `panel` or `admin` (at least 3 characters). It is saved with a warning that it is easy to guess. Addresses starting with "wp-", WordPress' own addresses (feed, page, search, sitemap …) and addresses already used by a page, post, term, registered route, real folder or neutral path are refused with a message. New installs still get a random address.
* With the address `login`, `/login/` shows the login form (with the MG Login design when that plugin is active); `wp-login.php`, `/wp-admin/` for visitors, `/admin/` and `/dashboard/` stay the normal 404 page. The check expects 200 for the chosen address and no longer counts it as a leaked shortcut.
* New: lockout after failed logins (Login attempts tab), off until switched on: per IP address and optionally per user name, longer lockouts after repeated ones, counters reset after a set time, login form, XML-RPC and application passwords, one message for every failure, proxy and Cloudflare aware, allow-list and deny-list, log with manual unlock, optional e-mail, import from Limit Login Attempts Reloaded. WP-CLI: `wp mg-stealth unlock`, `wp mg-stealth lockouts`.
* Fixed: the GitHub package missed the update checker's own `vendor` folder, so checking a release with notes ended in a fatal error ("Class Parsedown not found"). The build now leaves out only the development `vendor` folder, and every build is tested with an update check before release.

= 1.0.0 =
* Updates are delivered through the GitHub Releases of https://github.com/MGraphics/mg-stealth (release asset mg-stealth-<version>.zip).
* First release: clean page source and headers, neutral paths with a managed .htaccess block, private login, REST API allow-list, 404 for typical WordPress addresses, neutral sitemap, one-hop https/www redirects, self-check, emergency switch, Polish translation.

== Upgrade Notice ==

= 1.0.2 =
The login address takes signed-in users straight to the dashboard.

= 1.0.1 =
Fixes a fatal error when checking for updates; simple login addresses such as /login/; lockout after failed logins.

= 1.0.0 =
First release.
