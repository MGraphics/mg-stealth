# MG Stealth

WordPress plugin by [MGraphics](https://mgraphics.com.pl) that makes it hard to tell that a site runs WordPress: clean page source and headers, neutral public paths, a private login address, a REST API closed for guests, the normal 404 page for typical WordPress addresses, one-hop 301 redirects to https and the main host name, and a self-check that lists what still gives WordPress away.

Polski opis, FAQ i lista zmian: [readme.txt](readme.txt). The plugin is fully translated into Polish.

- License: GPL-2.0-or-later ([LICENSE](LICENSE))
- Requires WordPress 6.4+, PHP 7.4+; tested with WordPress 7.1 on PHP 8.4
- Author: MGraphics, https://mgraphics.com.pl

## Install

Download `mg-stealth-<version>.zip` from the [latest release](https://github.com/MGraphics/mg-stealth/releases/latest) (the release asset, not GitHub's *Source code* archive) and upload it in **Plugins → Add New → Upload Plugin**. Later versions show up as normal WordPress updates (see *Updates* below).

## Features

| Tab | What it does | Default |
|---|---|---|
| Page source | generator tags, RSD, wlwmanifest, shortlink, pingback, REST/oEmbed links, emoji, speculation rules, `?ver=` → per-site hash, `X-Powered-By`, `X-Pingback`, `X-Redirect-By`, REST `Link` headers, 1984 `Expires`, asset tag ids, theme body classes, HTML comments, `sourceURL` comments, login page and e-mail branding | on |
| Paths | `/wp-content/`, `/wp-includes/`, plugins, uploads, `admin-ajax.php`, `wp-comments-post.php`, `/wp-json/` under per-site neutral names (managed `.htaccess` block, verified before use), hashed plugin/theme folder names, original paths 404 for guests, `wp-config.php` snippet for a real move | off |
| Login | private login address; `wp-login.php` and `/wp-admin/` for guests → the site's 404 page; login links on public pages point home | off |
| Access | REST API for guests only on an allow-list; 404 for XML-RPC, core files, authors, direct `wp-cron.php`, `/login` `/admin` `/dashboard`, embeds, (optional) feeds; sitemap at `/sitemap.xml` | on (feeds off) |
| Redirects | one 301 to `https://` and the host of the site address (with or without `www`), path and query kept, `/.well-known/` excluded, `X-Forwarded-Proto` and Cloudflare `CF-Visitor` aware | off |
| Check | anonymous loopback probe of typical addresses and pages, with the traces it finds | – |

Everything is undone on deactivation (the `.htaccess` block is removed and WordPress' rewrite rules are rebuilt). Deleting the plugin also removes its settings.

### Emergency

- `define( 'MG_STEALTH_DISABLE', true );` in `wp-config.php` → every rule off, `.htaccess` block removed on the next request;
- rename `wp-content/plugins/mg-stealth` → the `.htaccess` rules stop immediately (each one checks that the plugin file exists);
- WP-CLI: `wp mg-stealth status | sync | rules | remove-rules | login-url | check`.

## Updates (GitHub Releases)

The GitHub build bundles [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT) and asks the Releases of the public repository [MGraphics/mg-stealth](https://github.com/MGraphics/mg-stealth) for new versions, without a token. It installs only the release asset named `mg-stealth-<version>.zip`; a release without that asset is ignored. The repository address is the constant `MG_STEALTH_UPDATE_REPOSITORY` in `mg-stealth.php` (`https://github.com/MGraphics/mg-stealth/`; it can also be defined in `wp-config.php`). `MG_STEALTH_DISABLE_UPDATES` switches the checks off. The WordPress.org build has neither the library nor the `Update URI` header.

### Releasing a version

1. Bump the version in three places: the `Version:` header and `MG_STEALTH_VERSION` in `mg-stealth.php`, and `Stable tag:` in `readme.txt`; add a changelog entry to `readme.txt`.
2. `bin/i18n.sh` when strings changed; `vendor/bin/phpunit`.
3. Commit, tag `v<version>` and push the tag: `git tag v1.0.1 && git push origin main v1.0.1`.
4. The tag workflow (`.github/workflows/release.yml`) runs the tests, builds both packages with `bin/build.sh` and creates the GitHub Release `v<version>` with `mg-stealth-<version>.zip` and `mg-stealth-<version>-wporg.zip` as release assets. By hand instead: `bin/build.sh && gh release create v1.0.1 build/mg-stealth-1.0.1.zip build/mg-stealth-1.0.1-wporg.zip`.
5. Sites with the plugin see the update within 12 hours (or at once via **Dashboard → Updates → Check again**).

The repository must stay **public**, otherwise sites cannot read the releases without a token.

## Development

```sh
bin/dev-setup.sh                         # WordPress 7.1 + MariaDB in Docker, http://127.0.0.1:8476
composer install && vendor/bin/phpunit   # unit tests
tests/integration/probe.sh http://127.0.0.1:8476 /tmp/probe   # anonymous curl matrix
bin/build.sh                             # build/mg-stealth-<v>.zip and build/mg-stealth-<v>-wporg.zip
bin/i18n.sh                              # .pot, Polish .po/.mo
docker compose run --rm cli wp plugin check /var/www/html/wp-content/plugins/mg-stealth-wporg
```

Admin credentials of the dev site are written to `.local/admin.txt` (not in git).
