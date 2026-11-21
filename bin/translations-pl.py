#!/usr/bin/env python3
"""Builds languages/mg-stealth-pl_PL.po from languages/mg-stealth.pot and the Polish texts below.

Usage: bin/translations-pl.py   (then compile: msgfmt -o languages/mg-stealth-pl_PL.mo languages/mg-stealth-pl_PL.po)
Strings missing here stay untranslated and are listed on stderr.
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent

PL = {
    "MG Stealth": "MG Stealth",
    "https://mgraphics.com.pl/mg-stealth/": "https://mgraphics.com.pl/mg-stealth/",
    "Makes it hard to tell that a site runs WordPress: clean page source and headers, neutral public paths, a private login address, a lockout after failed logins, closed REST API for guests, the normal 404 page for typical WordPress addresses and one-hop 301 redirects to https and the main host name.":
        "Utrudnia rozpoznanie, że strona działa na WordPressie: czyste źródło strony i nagłówki, neutralne ścieżki publiczne, prywatny adres logowania, blokada po nieudanych próbach logowania, REST API zamknięte dla gości, zwykła strona 404 pod typowymi adresami WordPressa oraz przekierowania 301 w jednym kroku na https i główną nazwę domeny.",
    "MGraphics": "MGraphics",
    "https://mgraphics.com.pl": "https://mgraphics.com.pl",
    "Settings": "Ustawienia",
    "You are not allowed to do this.": "Nie masz uprawnień do tej operacji.",
    "<strong>MG Stealth is switched off</strong> by the MG_STEALTH_DISABLE constant in wp-config.php: no rules are active and the .htaccess block has been removed. Delete the constant to switch it on again.":
        "<strong>MG Stealth jest wyłączona</strong> stałą MG_STEALTH_DISABLE w wp-config.php: żadna reguła nie działa, a blok w .htaccess został usunięty. Usuń stałą, aby ją ponownie włączyć.",
    "MG Stealth: WPS Hide Login is active as well. Deactivate it; MG Stealth already serves the login form at the private address.":
        "MG Stealth: włączona jest też wtyczka WPS Hide Login. Wyłącz ją – MG Stealth sama pokazuje formularz logowania pod prywatnym adresem.",
    "Page source": "Źródło strony",
    "Paths": "Ścieżki",
    "Login": "Logowanie",
    "Access": "Dostęp",
    "Redirects": "Przekierowania",
    "Check": "Sprawdź",
    "Help": "Pomoc",
    "Makes it hard to tell from the page source and the usual addresses that this site runs WordPress. Every change can be undone: deactivating the plugin restores WordPress' defaults.":
        "Utrudnia rozpoznanie po źródle strony i typowych adresach, że ta strona działa na WordPressie. Każdą zmianę można cofnąć: wyłączenie wtyczki przywraca domyślne ustawienia WordPressa.",
    "not needed": "niepotrzebne",
    "written to .htaccess": "zapisane w .htaccess",
    "to be copied by hand": "do skopiowania ręcznie",
    "rejected by the server": "odrzucone przez serwer",
    "Server rules": "Reguły serwera",
    "Neutral paths": "Neutralne ścieżki",
    "active": "działają",
    "waiting for the server rules": "czekają na reguły serwera",
    "off": "wyłączone",
    "Private login": "Prywatne logowanie",
    "on": "włączone",
    "REST API for guests": "REST API dla gości",
    "closed (allow-list)": "zamknięte (lista wyjątków)",
    "open": "otwarte",
    "On": "Włączone",
    "What WordPress and plugins print into every page and header. Visitors get the cleaned version; signed-in users see the normal one.":
        "To, co WordPress i wtyczki wpisują do każdej strony i nagłówka. Goście dostają wersję oczyszczoną, zalogowani widzą zwykłą.",
    "Head tags": "Znaczniki w <head>",
    "Removes the generator tags (of WordPress and of plugins), RSD, wlwmanifest, shortlink, pingback and the REST API discovery link.":
        "Usuwa znaczniki generatora (WordPressa i wtyczek), RSD, wlwmanifest, shortlink, pingback i link do REST API.",
    "Versions in asset addresses": "Wersje w adresach plików",
    "Replaces <code>?ver=7.1.5</code> with a short per-site code (<code>?v=1a2b3c4d</code>): browsers still get fresh files after updates, but the address no longer tells the version.":
        "Zamienia <code>?ver=7.1.5</code> na krótki kod tej strony (<code>?v=1a2b3c4d</code>): po aktualizacji przeglądarki nadal pobiorą nowe pliki, ale adres nie zdradza już wersji.",
    "Emoji script": "Skrypt emoji",
    "Removes the emoji detection script and styles (browsers show emoji on their own).":
        "Usuwa skrypt i style wykrywania emoji (przeglądarki same wyświetlają emoji).",
    "oEmbed": "oEmbed",
    "Removes the oEmbed discovery links and the embed script; /embed/ addresses become the 404 page.":
        "Usuwa linki oEmbed i skrypt osadzania; adresy /embed/ dają stronę 404.",
    "HTTP headers": "Nagłówki HTTP",
    "Removes <code>X-Powered-By</code>, <code>X-Pingback</code>, <code>X-Redirect-By</code>, the REST API and shortlink <code>Link</code> headers and the well-known 1984 <code>Expires</code> date.":
        "Usuwa <code>X-Powered-By</code>, <code>X-Pingback</code>, <code>X-Redirect-By</code>, nagłówki <code>Link</code> REST API i shortlinku oraz charakterystyczną datę <code>Expires</code> z 1984 roku.",
    "Asset tag ids": "Identyfikatory znaczników plików",
    "Removes the WordPress-style ids such as <code>id=\\\"wp-block-library-css\\\"</code> from link, style and script tags.":
        "Usuwa identyfikatory w stylu WordPressa, np. <code>id=\\\"wp-block-library-css\\\"</code>, ze znaczników link, style i script.",
    "Body and comment classes": "Klasy <body> i komentarzy",
    "Removes <code>wp-theme-…</code>, <code>wp-child-theme-…</code>, <code>wp-singular</code> and the author login classes. Block classes (<code>wp-block-…</code>) stay, styles depend on them.":
        "Usuwa <code>wp-theme-…</code>, <code>wp-child-theme-…</code>, <code>wp-singular</code> i klasy z loginem autora. Klasy bloków (<code>wp-block-…</code>) zostają, bo zależą od nich style.",
    "HTML comments": "Komentarze HTML",
    "Removes HTML comments (plugin signatures, cache notes); scripts, styles and conditional comments are left untouched.":
        "Usuwa komentarze HTML (podpisy wtyczek, notki pamięci podręcznej); skrypty, style i komentarze warunkowe zostają nietknięte.",
    "Speculation rules": "Reguły wczytywania z wyprzedzeniem",
    "Removes WordPress' prefetch rules, which list its folders.": "Usuwa reguły prefetch WordPressa, które wymieniają jego katalogi.",
    "Login page branding": "Wygląd strony logowania",
    "The site icon and name instead of the WordPress logo, titles without \\\"WordPress\\\".":
        "Ikona i nazwa strony zamiast logo WordPressa, tytuły bez „WordPress”.",
    "E-mails": "E-maile",
    "Messages are sent as the site name instead of \\\"WordPress\\\" and without the <code>X-Mailer: PHPMailer</code> header.":
        "Wiadomości wychodzą jako nazwa strony zamiast „WordPress” i bez nagłówka <code>X-Mailer: PHPMailer</code>.",
    "Visitors get neutral addresses instead of /wp-content/, /wp-includes/ and /wp-json/. The files stay where they are: the server maps the new names back (a block in .htaccess, Apache and LiteSpeed). The new addresses are used only after the plugin has checked that they answer.":
        "Goście dostają neutralne adresy zamiast /wp-content/, /wp-includes/ i /wp-json/. Pliki zostają na miejscu: serwer tłumaczy nowe nazwy na prawdziwe (blok w .htaccess, Apache i LiteSpeed). Nowe adresy są używane dopiero wtedy, gdy wtyczka sprawdzi, że działają.",
    "This server does not read .htaccess files. Use the wp-config.php snippet below for a real move of the folders instead.":
        "Ten serwer nie czyta plików .htaccess. Zamiast aliasów użyj fragmentu wp-config.php poniżej i naprawdę przenieś katalogi.",
    "Switches the aliases below on. The names were drawn for this site, so two sites with this plugin do not look alike; change them freely.":
        "Włącza poniższe aliasy. Nazwy zostały wylosowane dla tej strony, więc dwie strony z tą wtyczką nie wyglądają tak samo; można je dowolnie zmienić.",
    "Content folder": "Katalog treści",
    "Themes": "Motywy",
    "Plugins": "Wtyczki",
    "Uploads (media)": "Pliki (media)",
    "Core scripts and styles": "Skrypty i style rdzenia",
    "AJAX address": "Adres AJAX",
    "Comment form address": "Adres formularza komentarzy",
    "REST API prefix": "Prefiks REST API",
    "Instead of <code>/%s</code>.": "Zamiast <code>/%s</code>.",
    "Hide folder names": "Ukryj nazwy katalogów",
    "Plugin and theme folders get short per-site codes (e.g. <code>/assets/ext/9f3a1c2b/</code>), so their names do not give the plugins away.":
        "Katalogi wtyczek i motywu dostają krótkie kody tej strony (np. <code>/assets/ext/9f3a1c2b/</code>), więc ich nazwy nie zdradzają, jakie to wtyczki.",
    "Hide the original paths": "Ukryj oryginalne ścieżki",
    "For visitors without a session /wp-includes/, /wp-content/ (with the plugin and theme folders) and the admin AJAX file answer with the normal 404 page. Files in uploads stay reachable under their old address too (links in old content, CSS files of page builders).":
        "Dla gości bez sesji /wp-includes/, /wp-content/ (z katalogami wtyczek i motywów) oraz plik AJAX panelu odpowiadają zwykłą stroną 404. Pliki w uploads działają też pod starym adresem (linki w starszych treściach, pliki CSS kreatorów stron).",
    "The plugin keeps this block at the very top of .htaccess (between \\\"# BEGIN MG Stealth\\\" and \\\"# END MG Stealth\\\"). If the file is not writable, paste it there by hand and press \\\"Check again\\\".":
        "Wtyczka trzyma ten blok na samej górze .htaccess (między „# BEGIN MG Stealth” a „# END MG Stealth”). Jeśli plik nie jest zapisywalny, wklej blok ręcznie i kliknij „Zapisz i sprawdź reguły serwera ponownie”.",
    "Real move (optional)": "Prawdziwe przeniesienie (opcjonalnie)",
    "Instead of aliases the folders can be really renamed. This needs access to the files and the database; the plugin does not do it for you. After the move the matching aliases are simply not needed.":
        "Zamiast aliasów można naprawdę zmienić nazwy katalogów. Potrzebny jest dostęp do plików i bazy danych; wtyczka nie robi tego sama. Po przeniesieniu odpowiednie aliasy po prostu przestają być potrzebne.",
    "(above the line \\\"That's all, stop editing!\\\")": "(nad linią „That's all, stop editing!”)",
    "Commands (WP-CLI)": "Polecenia (WP-CLI)",
    "Write and check the server rules again": "Zapisz i sprawdź reguły serwera ponownie",
    "The login form moves to a private address. For everyone else wp-login.php, and the admin area for visitors without a session, answer with the normal 404 page. The address is never printed on public pages; login links for visitors point to the home page.":
        "Formularz logowania przenosi się pod prywatny adres. Dla wszystkich innych wp-login.php, a dla gości bez sesji także panel, odpowiadają zwykłą stroną 404. Adres nigdy nie pojawia się na publicznych stronach; linki logowania dla gości prowadzą na stronę główną.",
    "The private login is not available on multisite networks.": "Prywatne logowanie nie działa w sieci multisite.",
    "Your login address (save it in your bookmarks and password manager):": "Twój adres logowania (zapisz go w zakładkach i w menedżerze haseł):",
    "Private login address": "Prywatny adres logowania",
    "After saving, the old login address stops working at once. If you lose the new one: WP-CLI <code>wp mg-stealth login-url</code>, or define <code>MG_STEALTH_DISABLE</code> in wp-config.php (Help tab).":
        "Po zapisaniu stary adres logowania od razu przestaje działać. Jeśli zgubisz nowy: WP-CLI <code>wp mg-stealth login-url</code> albo stała <code>MG_STEALTH_DISABLE</code> w wp-config.php (zakładka Pomoc).",
    "Address": "Adres",
    "At least 6 characters; letters, digits and hyphens. Not \\\"login\\\", \\\"admin\\\" or anything starting with \\\"wp-\\\".":
        "Co najmniej 6 znaków: litery, cyfry i myślniki. Nie „login”, „admin” ani nic, co zaczyna się od „wp-”.",
    "Typical WordPress addresses answer like any address that does not exist: the same 404 page, so a probe cannot tell \\\"blocked\\\" from \\\"missing\\\".":
        "Typowe adresy WordPressa odpowiadają jak każdy nieistniejący adres: tą samą stroną 404, więc sonda nie odróżni „zablokowane” od „nie ma”.",
    "REST API only for signed-in users": "REST API tylko dla zalogowanych",
    "Visitors get the 404 page for the REST API, except for the routes allowed below. The editor, the admin area and application passwords keep working.":
        "Goście dostają stronę 404 zamiast REST API, z wyjątkiem tras dozwolonych poniżej. Edytor, panel i hasła aplikacji działają dalej.",
    "Allowed for visitors": "Dozwolone dla gości",
    "One route prefix per line, e.g. contact-form-7 or wc/store. Contact forms and shops that send forms through the REST API need theirs here.":
        "Jeden początek trasy w wierszu, np. contact-form-7 albo wc/store. Formularze kontaktowe i sklepy, które wysyłają dane przez REST API, muszą tu mieć swój wpis.",
    "Namespaces on this site:": "Przestrzenie nazw na tej stronie:",
    "<code>xmlrpc.php</code> (GET and POST) is the 404 page; pingbacks are off.": "<code>xmlrpc.php</code> (GET i POST) daje stronę 404; pingbacki są wyłączone.",
    "Core files": "Pliki rdzenia",
    "<code>readme.html</code>, <code>license.txt</code>, <code>wp-config-sample.php</code>, <code>wp-load.php</code>, <code>wp-signup.php</code>, <code>wp-activate.php</code>, <code>wp-trackback.php</code>, <code>wp-links-opml.php</code>, <code>wp-mail.php</code> and trackback addresses.":
        "<code>readme.html</code>, <code>license.txt</code>, <code>wp-config-sample.php</code>, <code>wp-load.php</code>, <code>wp-signup.php</code>, <code>wp-activate.php</code>, <code>wp-trackback.php</code>, <code>wp-links-opml.php</code>, <code>wp-mail.php</code> i adresy trackbacków.",
    "Authors": "Autorzy",
    "<code>?author=1</code>, author archives, the users sitemap and REST endpoint (they reveal login names).":
        "<code>?author=1</code>, archiwa autorów, mapa strony i punkt REST użytkowników (zdradzają loginy).",
    "RSS feeds": "Kanały RSS",
    "All feeds become the 404 page and their links disappear. Leave off if people or services read the feed.":
        "Wszystkie kanały dają stronę 404, a linki do nich znikają. Zostaw wyłączone, jeśli ktoś lub jakaś usługa czyta kanał.",
    "wp-cron.php": "wp-cron.php",
    "Called directly it is the 404 page; WordPress' own calls carry a secret key and work.":
        "Wywołany bezpośrednio daje stronę 404; własne wywołania WordPressa mają tajny klucz i działają.",
    "For a server cron job use this address:": "Dla zadania cron na serwerze użyj tego adresu:",
    "/login, /admin, /dashboard": "/login, /admin, /dashboard",
    "No redirect to the login form from these short addresses (the one chosen as the private login address shows the form).": "Bez przekierowania z tych krótkich adresów na formularz logowania.",
    "Sitemap without \\\"wp-\\\"": "Mapa strony bez „wp-”",
    "WordPress' own sitemap at <code>/sitemap.xml</code> instead of <code>/wp-sitemap.xml</code>, without its stylesheet. Has no effect when an SEO plugin provides the sitemap. Submit the new address in Search Console.":
        "Wbudowana mapa strony WordPressa pod <code>/sitemap.xml</code> zamiast <code>/wp-sitemap.xml</code>, bez arkusza stylów. Nie działa, gdy mapę dostarcza wtyczka SEO. Nowy adres zgłoś w Search Console.",
    "All addresses of the site in one 301 step to the main address <code>%s</code>: path and query string kept, /.well-known/ left alone (certificates), https behind a proxy or Cloudflare recognised. Only this host name and its www variant are affected, so a local copy keeps working.":
        "Wszystkie adresy strony jednym krokiem 301 na adres główny <code>%s</code>: ścieżka i parametry zostają, /.well-known/ bez zmian (certyfikaty), https za proxy i Cloudflare jest rozpoznawane. Dotyczy tylko tej nazwy domeny i jej wariantu z www, więc kopia lokalna działa dalej.",
    "The site address (Settings → General) starts with http://. Change it to https:// first (with a working certificate); until then the https redirect stays off.":
        "Adres witryny (Ustawienia → Ogólne) zaczyna się od http://. Najpierw zmień go na https:// (przy działającym certyfikacie); do tego czasu przekierowanie na https pozostaje wyłączone.",
    "http → https": "http → https",
    "Every http:// address goes to https://.": "Każdy adres http:// przechodzi na https://.",
    "One host name": "Jedna nazwa domeny",
    "<code>%1$s</code> goes to <code>%2$s</code> (the host of the site address decides whether it is with or without www).":
        "<code>%1$s</code> przechodzi na <code>%2$s</code> (o tym, czy z www, czy bez, decyduje domena w adresie witryny).",
    "Test from a terminal": "Test z terminala",
    "Expected: 301 with location https://… for the first two (in one step), 200 for the last.":
        "Oczekiwane: 301 z location https://… dla dwóch pierwszych (w jednym kroku), 200 dla ostatniego.",
    "The plugin asks the site for its pages and the typical WordPress addresses the way a stranger would: without cookies, from the server itself. It then lists what still gives WordPress away.":
        "Wtyczka pyta stronę o jej podstrony i typowe adresy WordPressa tak, jak zrobiłby to obcy: bez ciasteczek, z samego serwera. Potem wypisuje, co jeszcze zdradza WordPressa.",
    "Run the check": "Uruchom sprawdzanie",
    "hidden (normal 404 page)": "ukryte (zwykła strona 404)",
    "works": "działa",
    "allowed by the settings": "dozwolone w ustawieniach",
    "blocked, but not like the 404 page": "zablokowane, ale inaczej niż strona 404",
    "answers like WordPress": "odpowiada jak WordPress",
    "does not work": "nie działa",
    "Reference: the 404 page of a random address answers %1$d with %2$d bytes.":
        "Wzorzec: strona 404 losowego adresu odpowiada kodem %1$d i ma %2$d bajtów.",
    "Typical addresses": "Typowe adresy",
    "Status": "Kod",
    "Redirect to": "Przekierowanie na",
    "Result": "Wynik",
    "Traces in pages and headers": "Ślady w stronach i nagłówkach",
    "Headers of the home page:": "Nagłówki strony głównej:",
    "Page": "Strona",
    "Found": "Znalezione",
    "nothing found": "nic nie znaleziono",
    "Words in your own content (a post about WordPress) and theme credits are found too; the theme's footer text is edited in the theme or the Site Editor. Block class names (wp-block-…) and the theme stylesheet header stay, as styles depend on them.":
        "Znajdowane są też słowa z Twoich treści (np. wpis o WordPressie) i podpisy motywu; tekst stopki motywu zmienia się w motywie albo w edytorze witryny. Nazwy klas bloków (wp-block-…) i nagłówek arkusza stylów motywu zostają, bo zależą od nich style.",
    "Emergency: switch everything off": "Awaryjnie: wyłącz wszystko",
    "If the site or the admin area stops working, use any one of these:": "Jeśli strona albo panel przestaną działać, wystarczy jeden z tych sposobów:",
    "Add <code>define( 'MG_STEALTH_DISABLE', true );</code> to wp-config.php and open any page of the site: every rule stops, the .htaccess block is removed, wp-login.php works again. Delete the line to switch the plugin on again.":
        "Dopisz <code>define( 'MG_STEALTH_DISABLE', true );</code> do wp-config.php i otwórz dowolną stronę witryny: wszystkie reguły przestają działać, blok w .htaccess znika, wp-login.php znowu działa. Usuń tę linię, aby włączyć wtyczkę ponownie.",
    "Rename the folder <code>wp-content/plugins/mg-stealth</code> (FTP, file manager). The .htaccess rules check that the plugin file exists, so they stop at once.":
        "Zmień nazwę katalogu <code>wp-content/plugins/mg-stealth</code> (FTP, menedżer plików). Reguły w .htaccess sprawdzają, czy plik wtyczki istnieje, więc od razu przestają działać.",
    "WP-CLI: <code>wp mg-stealth remove-rules</code>, <code>wp mg-stealth login-url</code>, <code>wp plugin deactivate mg-stealth</code>.":
        "WP-CLI: <code>wp mg-stealth remove-rules</code>, <code>wp mg-stealth login-url</code>, <code>wp plugin deactivate mg-stealth</code>.",
    "Delete the lines from <code># BEGIN MG Stealth</code> to <code># END MG Stealth</code> in <code>%s</code>.":
        "Usuń linie od <code># BEGIN MG Stealth</code> do <code># END MG Stealth</code> w pliku <code>%s</code>.",
    "Deactivation and removal": "Wyłączenie i usunięcie",
    "Deactivating removes the .htaccess block and WordPress' rewrite rules are rebuilt: the site is back to its defaults (wp-login.php, /wp-json/, /wp-content/ …). The settings stay for a later activation; deleting the plugin removes them too.":
        "Wyłączenie usuwa blok z .htaccess, a WordPress odbudowuje swoje reguły adresów: strona wraca do ustawień domyślnych (wp-login.php, /wp-json/, /wp-content/ …). Ustawienia zostają na później; usunięcie wtyczki kasuje także je.",
    "What cannot be hidden": "Czego nie da się ukryć",
    "Block class names (wp-block-…, wp-container-…) and the CSS custom properties of block themes (--wp--preset--…): styles depend on them.":
        "Nazwy klas bloków (wp-block-…, wp-container-…) i zmienne CSS motywów blokowych (--wp--preset--…): zależą od nich style.",
    "The header comment of the theme's style.css when the theme loads that file, and @wordpress/… names in the import map of blocks that use the Interactivity API.":
        "Komentarz nagłówkowy w style.css motywu, jeśli motyw wczytuje ten plik, oraz nazwy @wordpress/… w mapie importów bloków korzystających z Interactivity API.",
    "Cookies after signing in (wordpress_logged_in_…), and the login page itself at its private address.":
        "Ciasteczka po zalogowaniu (wordpress_logged_in_…) i sama strona logowania pod prywatnym adresem.",
    "Behaviour that a careful tool can still recognise (for example /page/2/ archives). The plugin makes the usual checks find nothing; it cannot promise that no tool ever can.":
        "Zachowanie, które dokładne narzędzie może rozpoznać (np. archiwa /page/2/). Wtyczka sprawia, że typowe sprawdzenia nic nie znajdują; nie może obiecać, że żadne narzędzie nigdy niczego nie wykryje.",
    "Servers": "Serwery",
    "Apache and LiteSpeed read the .htaccess block. On nginx the PHP part still works (head and headers, REST API, login, authors, XML-RPC, cron, redirects); the neutral paths and the blocking of static files such as readme.html need server rules or the real move from the Paths tab.":
        "Apache i LiteSpeed czytają blok w .htaccess. Na nginx działa część w PHP (head i nagłówki, REST API, logowanie, autorzy, XML-RPC, cron, przekierowania); neutralne ścieżki i blokada plików statycznych, np. readme.html, wymagają reguł serwera albo prawdziwego przeniesienia z zakładki Ścieżki.",
    "Privacy": "Prywatność",
    "The plugin sends no data anywhere and prints no credit link. The self-check only asks this site itself. The GitHub build looks for new versions in the GitHub repository of the plugin; the WordPress.org build does not.":
        "Wtyczka nigdzie nie wysyła danych i nie wstawia linku do autora. Sprawdzanie pyta wyłącznie tę stronę. Wersja z GitHuba sprawdza nowe wydania w repozytorium wtyczki na GitHubie; wersja z WordPress.org tego nie robi.",
    "Back up the files and the database first. Run in the WordPress folder:": "Najpierw zrób kopię plików i bazy danych. Uruchom w katalogu WordPressa:",
    "The page you are looking for does not exist.": "Strona, której szukasz, nie istnieje.",
    "Home page": "Strona główna",
    "Newest post": "Najnowszy wpis",
    "Newest page": "Najnowsza strona",
    "404 page": "Strona 404",
    "RSS feed": "Kanał RSS",
    "Sitemap": "Mapa strony",
    "This server does not read .htaccess files (for example nginx). Rules that need the server are not active.":
        "Ten serwer nie czyta plików .htaccess (np. nginx). Reguły wymagające serwera nie działają.",
    "The neutral paths do not answer on this server, so they were left out of the rules and the original paths stay in use. See the help tab.":
        "Neutralne ścieżki nie odpowiadają na tym serwerze, więc zostały pominięte w regułach i nadal używane są oryginalne ścieżki. Zobacz zakładkę Pomoc.",
    "The .htaccess file is not writable. Copy the rules below into it by hand, at the very top.":
        "Plik .htaccess nie jest zapisywalny. Skopiuj do niego ręcznie reguły poniżej, na samą górę.",
    "After writing the rules the site stopped answering (server error), so the previous .htaccess was restored. The server probably does not allow some directives; see the help tab.":
        "Po zapisaniu reguł strona przestała odpowiadać (błąd serwera), więc przywrócono poprzedni .htaccess. Serwer zapewne nie pozwala na niektóre dyrektywy; zobacz zakładkę Pomoc.",
    "Every neutral path needs a name.": "Każda neutralna ścieżka musi mieć nazwę.",
    "\\\"%s\\\" still sounds like WordPress; choose a neutral name.": "„%s” nadal kojarzy się z WordPressem; wybierz neutralną nazwę.",
    "The name \\\"%s\\\" is used twice.": "Nazwa „%s” jest użyta dwa razy.",
    "A real folder \\\"%s\\\" already exists in the site root; choose another name.": "W katalogu głównym strony istnieje już katalog „%s”; wybierz inną nazwę.",
    "The addresses for AJAX, comments and the REST API must be a single word.": "Adresy AJAX, komentarzy i REST API muszą być jednym słowem.",
    "https://github.com/MGraphics/mg-stealth": "https://github.com/MGraphics/mg-stealth",
    "MG Stealth: Limit Login Attempts Reloaded is active as well. Both count failed logins; deactivate Limit Login Attempts Reloaded, MG Stealth already locks out repeated failed attempts.":
        "MG Stealth: włączona jest też wtyczka Limit Login Attempts Reloaded. Obie liczą nieudane logowania – wyłącz Limit Login Attempts Reloaded, MG Stealth sama blokuje powtarzane nieudane próby.",
    "Login attempts": "Próby logowania",
    "Login lockout": "Blokada logowania",
    "Letters, digits and hyphens, at least 3 characters; not starting with \\\"wp-\\\" and not an address already used on the site. A simple word such as <code>login</code> works too, but bots try it first: a random address hides the login better.":
        "Litery, cyfry i myślniki, co najmniej 3 znaki; nie może zaczynać się od „wp-” ani być adresem już używanym na stronie. Można też wpisać proste słowo, np. <code>login</code>, ale boty sprawdzają je w pierwszej kolejności: losowy adres lepiej ukrywa logowanie.",
    "After a few failed sign-ins from one address, further attempts are refused for a while, even with the right password. Covers the login form (also at the private address), XML-RPC and application passwords of the REST API. Every failure shows the same message, so nobody learns whether a user name exists.":
        "Po kilku nieudanych logowaniach z jednego adresu kolejne próby są przez pewien czas odrzucane, nawet z poprawnym hasłem. Dotyczy formularza logowania (także pod prywatnym adresem), XML-RPC i haseł aplikacji w REST API. Każda nieudana próba daje ten sam komunikat, więc nikt nie dowie się, czy dana nazwa użytkownika istnieje.",
    "Limit Login Attempts Reloaded is active. Do not run both: import its settings below, switch the lockout on here and then deactivate Limit Login Attempts Reloaded.":
        "Włączona jest wtyczka Limit Login Attempts Reloaded. Nie używaj obu naraz: zaimportuj poniżej jej ustawienia, włącz tutaj blokadę, a potem wyłącz Limit Login Attempts Reloaded.",
    "The settings of Limit Login Attempts Reloaded were imported and the lockout is on.":
        "Ustawienia Limit Login Attempts Reloaded zostały zaimportowane, a blokada jest włączona.",
    "No settings of Limit Login Attempts Reloaded were found.": "Nie znaleziono ustawień Limit Login Attempts Reloaded.",
    "Done.": "Gotowe.",
    "Lockout after failed logins": "Blokada po nieudanych logowaniach",
    "Replaces plugins such as Limit Login Attempts Reloaded. Addresses on the allow-list below are never locked out; add your own fixed address there.":
        "Zastępuje wtyczki takie jak Limit Login Attempts Reloaded. Adresy z listy dozwolonych poniżej nigdy nie są blokowane – dopisz tam swój stały adres.",
    "Allowed attempts": "Dozwolone próby",
    "failed attempts, then a lockout": "nieudanych prób, potem blokada",
    "Lockout": "Blokada",
    "minutes": "minut",
    "Longer lockout after": "Dłuższa blokada po",
    "lockouts in a row": "blokadach z rzędu",
    "Longer lockout": "Dłuższa blokada",
    "hours": "godzin",
    "Counters reset after": "Liczniki zerowane po",
    "hours without a failed attempt": "godzinach bez nieudanej próby",
    "Also per user name": "Także według nazwy użytkownika",
    "Counts failures for each user name too, so an attack from many addresses on one account is stopped. A stranger can then lock that account out for a while; allow-listed addresses still sign in.":
        "Liczy nieudane próby także dla każdej nazwy użytkownika, co zatrzymuje atak na jedno konto z wielu adresów. Obcy może wtedy na jakiś czas zablokować to konto; adresy z listy dozwolonych nadal się logują.",
    "Site behind Cloudflare": "Strona za Cloudflare",
    "Takes the visitor's address from <code>CF-Connecting-IP</code>, only for requests that really come from Cloudflare's addresses.":
        "Bierze adres odwiedzającego z nagłówka <code>CF-Connecting-IP</code>, tylko dla żądań, które naprawdę przychodzą z adresów Cloudflare.",
    "Trusted proxies": "Zaufane serwery proxy",
    "Addresses or ranges (CIDR) of your own reverse proxy or load balancer: only requests from them may pass the visitor's address in <code>X-Forwarded-For</code>. Leave empty when the site is reached directly. This request comes from <code>%s</code>.":
        "Adresy lub zakresy (CIDR) twojego reverse proxy albo load balancera: tylko żądania od nich mogą przekazać adres odwiedzającego w <code>X-Forwarded-For</code>. Zostaw puste, gdy strona jest osiągalna bezpośrednio. To żądanie przyszło z <code>%s</code>.",
    "Allow-list": "Lista dozwolonych",
    "Addresses or ranges that are never counted or locked out, one per line, e.g. <code>203.0.113.7</code> or <code>198.51.100.0/24</code>.":
        "Adresy lub zakresy, które nigdy nie są liczone ani blokowane, po jednym w wierszu, np. <code>203.0.113.7</code> albo <code>198.51.100.0/24</code>.",
    "Deny-list": "Lista zablokowanych",
    "Addresses or ranges that never sign in: the login page is the normal 404 page for them.":
        "Adresy lub zakresy, które nigdy się nie zalogują: strona logowania jest dla nich zwykłą stroną 404.",
    "E-mail on lockout": "E-mail przy blokadzie",
    "Sends a short message to the site's administrator address for every lockout.":
        "Wysyła krótką wiadomość na adres administratora strony przy każdej blokadzie.",
    "Shorten addresses in the log": "Skracaj adresy w dzienniku",
    "The log keeps only the start of an address (e.g. <code>203.0.113.0</code>). Lockouts keep working: the plugin stores addresses only as keyed hashes. Passwords are never stored.":
        "Dziennik zachowuje tylko początek adresu (np. <code>203.0.113.0</code>). Blokady działają dalej: wtyczka przechowuje adresy wyłącznie jako skróty z kluczem. Hasła nigdy nie są zapisywane.",
    "Limit Login Attempts Reloaded": "Limit Login Attempts Reloaded",
    "Settings of Limit Login Attempts Reloaded were found on this site (limits, allow- and deny-list). Importing them overwrites the values above and switches the lockout on; Limit Login Attempts Reloaded itself is not changed or deactivated.":
        "Na tej stronie są ustawienia Limit Login Attempts Reloaded (limity, lista dozwolonych i zablokowanych). Import nadpisze powyższe wartości i włączy blokadę; sama wtyczka Limit Login Attempts Reloaded nie zostanie zmieniona ani wyłączona.",
    "Import the settings": "Importuj ustawienia",
    "Active lockouts": "Aktywne blokady",
    "None.": "Brak.",
    "Address or user name": "Adres lub nazwa użytkownika",
    "Until": "Do",
    "user": "użytkownik",
    "Unlock": "Odblokuj",
    "Unlock all": "Odblokuj wszystkie",
    "Log of lockouts": "Dziennik blokad",
    "No lockouts yet.": "Na razie nie było blokad.",
    "The last %d lockouts.": "Ostatnie blokady (do %d).",
    "Time": "Czas",
    "User name tried": "Podana nazwa użytkownika",
    "Where": "Gdzie",
    "longer lockout": "dłuższa blokada",
    "Empty the log": "Wyczyść dziennik",
    "The user name or password is incorrect.": "Nazwa użytkownika lub hasło są nieprawidłowe.",
    "[%s] Login lockout": "[%s] Blokada logowania",
    "Address: %s": "Adres: %s",
    "User name tried: %s": "Podana nazwa użytkownika: %s",
    "Where: %s": "Gdzie: %s",
    "Locked out until: %s": "Zablokowany do: %s",
    "Unlock: Settings → MG Stealth → Login attempts, or WP-CLI \\\"wp mg-stealth unlock <address>\\\".":
        "Odblokowanie: Ustawienia → MG Stealth → Próby logowania albo WP-CLI „wp mg-stealth unlock <adres>”.",
    "The login address \\\"%s\\\" cannot be used: use at least 3 letters, digits or hyphens, nothing starting with \\\"wp-\\\" and no WordPress address (feed, page, search, sitemap…).":
        "Adresu logowania „%s” nie można użyć: wpisz co najmniej 3 litery, cyfry lub myślniki, nic zaczynającego się od „wp-” i żadnego adresu WordPressa (feed, page, search, sitemap…).",
    "The login address \\\"%s\\\" is already one of the neutral paths (Paths tab); choose another one.":
        "Adres logowania „%s” jest już jedną z neutralnych ścieżek (zakładka Ścieżki); wybierz inny.",
    "A real file or folder \\\"%s\\\" exists in the site root; choose another login address.":
        "W katalogu głównym strony istnieje plik lub katalog „%s”; wybierz inny adres logowania.",
    "A page or post with the address \\\"%s\\\" already exists; choose another login address or change its address.":
        "Istnieje już strona lub wpis o adresie „%s”; wybierz inny adres logowania albo zmień jej adres.",
    "A category, tag or other term \\\"%s\\\" already exists; choose another login address.":
        "Istnieje już kategoria, tag lub inny termin „%s”; wybierz inny adres logowania.",
    "The address \\\"%s\\\" is already used by WordPress or another plugin (a registered route); choose another login address.":
        "Adres „%s” jest już używany przez WordPressa albo inną wtyczkę (zarejestrowana ścieżka); wybierz inny adres logowania.",
    "The login address \\\"/%s/\\\" is easy to guess: bots try it first, so it hides the login less than a random address. It works and wp-login.php stays the 404 page; use a strong password and brute-force protection: the lockout after failed logins (Login attempts tab), ideally two-factor authentication too.":
        "Adres logowania „/%s/” łatwo odgadnąć: boty sprawdzają go w pierwszej kolejności, więc ukrywa logowanie słabiej niż losowy adres. Działa, a wp-login.php nadal jest stroną 404; używaj silnych haseł i ochrony przed zgadywaniem haseł: blokady po nieudanych logowaniach (zakładka Próby logowania), najlepiej też logowania dwuskładnikowego.",
}

PL_PLURAL = {
    "Too many failed login attempts. Try again in %d minute.": (
        "Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za %d minutę.",
        "Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za %d minuty.",
        "Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za %d minut.",
    ),
}


def po_string(text: str) -> str:
    return '"' + text + '"'


VERSION = re.search(r"^ \* Version: *(\S+)", (ROOT / "mg-stealth.php").read_text(encoding="utf-8"), re.M).group(1)


def main() -> int:
    pot = (ROOT / "languages" / "mg-stealth.pot").read_text(encoding="utf-8")
    header, _, body = pot.partition('\n\n')
    out = [
        '# Polish translation of MG Stealth.',
        '# This file is distributed under the GPL-2.0-or-later.',
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: MG Stealth ' + VERSION + '\\n"',
        '"Report-Msgid-Bugs-To: https://github.com/MGraphics/mg-stealth/issues\\n"',
        '"Last-Translator: MGraphics\\n"',
        '"Language-Team: MGraphics\\n"',
        '"PO-Revision-Date: 2026-11-21 21:00+0100\\n"',
        '"Language: pl_PL\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Plural-Forms: nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);\\n"',
        '"X-Domain: mg-stealth\\n"',
        '',
    ]
    missing = []
    for block in re.split(r'\n\n+', pot.strip()):
        m = re.search(r'^msgid ((?:"(?:[^"\\]|\\.)*"\n?)+)msgstr ""', block, re.M)
        if not m:
            continue
        msgid = ''.join(re.findall(r'"((?:[^"\\]|\\.)*)"', m.group(1)))
        if msgid == '':
            continue
        comments = [line for line in block.split('\n') if line.startswith('#')]
        plural = re.search(r'^msgid_plural ((?:"(?:[^"\\]|\\.)*"\n?)+)', block, re.M)
        if plural:
            forms = PL_PLURAL.get(msgid)
            if forms is None:
                missing.append(msgid)
                forms = ('', '', '')
            out.extend(comments)
            out.append('msgid ' + po_string(msgid))
            out.append('msgid_plural ' + po_string(''.join(re.findall(r'"((?:[^"\\]|\\.)*)"', plural.group(1)))))
            for n, form in enumerate(forms):
                out.append('msgstr[%d] ' % n + po_string(form))
            out.append('')
            continue
        translated = PL.get(msgid)
        if translated is None:
            missing.append(msgid)
            translated = ''
        out.extend(comments)
        out.append('msgid ' + po_string(msgid))
        out.append('msgstr ' + po_string(translated))
        out.append('')
    (ROOT / "languages" / "mg-stealth-pl_PL.po").write_text('\n'.join(out), encoding="utf-8")
    for msgid in missing:
        print('missing:', msgid, file=sys.stderr)
    return 1 if missing else 0


if __name__ == '__main__':
    sys.exit(main())
