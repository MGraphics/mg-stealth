=== MG Stealth ===
Contributors: mgraphics
Tags: security, hide wordpress, login, rest api, htaccess
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
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

MG Stealth utrudnia rozpoznanie, że strona działa na WordPressie: czyści źródło strony i nagłówki, nadaje neutralne nazwy katalogom /wp-content/, /wp-includes/ i /wp-json/, przenosi logowanie pod prywatny adres, zamyka REST API dla gości (z listą wyjątków dla formularzy), a typowe adresy WordPressa (xmlrpc.php, readme.html, ?author=1, wp-cron.php …) odpowiadają zwykłą stroną 404. Opcjonalnie przekierowuje jednym krokiem 301 z http na https i z www na domenę bez www (lub odwrotnie). Zakładka „Sprawdź” pokazuje, co jeszcze zdradza WordPressa. Każdą funkcję można wyłączyć, a wyłączenie wtyczki przywraca ustawienia domyślne. Wtyczka jest w pełni przetłumaczona na polski.

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

= Does it work on nginx? =

The PHP part does (page source and headers, REST API, private login, authors, XML-RPC, cron, redirects). The neutral paths and the blocking of static files such as `readme.html` need server rules: use the `wp-config.php` snippet for a real move of the folders, or add equivalent nginx rules.

= My contact form stopped sending. =

Forms that post through the REST API need their route on the allow-list (Access tab). Contact Form 7, WPForms, Fluent Forms, Ninja Forms and WooCommerce's store API are on it by default; the tab lists the namespaces present on your site.

= Will search engines notice? =

Pages keep their addresses. Only `/wp-sitemap.xml` moves to `/sitemap.xml` (submit it in Search Console); with an SEO plugin its own sitemap is untouched.

= Can WordPress be hidden completely? =

No. Block class names (`wp-block-…`), the CSS variables of block themes, the header of the theme's `style.css` and cookies after signing in stay, because styles and WordPress itself depend on them. The plugin makes the usual checks (page source, typical addresses, headers) find nothing; it does not promise that no tool can ever recognise the site.

= Does the plugin send data anywhere? =

No. The check only asks your own site. The build distributed on GitLab looks for new versions in the plugin's GitLab project; the WordPress.org build has no update code of its own.

== Screenshots ==

1. Page source: what is removed for visitors.
2. Neutral paths with names drawn for the site.
3. Access rules and the REST API allow-list.
4. The check: typical addresses and remaining traces.

== Changelog ==

= 1.0.0 =
* First release: clean page source and headers, neutral paths with a managed .htaccess block, private login, REST API allow-list, 404 for typical WordPress addresses, neutral sitemap, one-hop https/www redirects, self-check, emergency switch, Polish translation.

== Upgrade Notice ==

= 1.0.0 =
First release.
