ZOTY RELEASE NOTES
==================

A changelog website in PHP. The layout follows docs.aris.com release notes,
the look comes from zoty.cz. Versions, entries, modules, subscribers and admin
accounts are stored in a database (MySQL/MariaDB through PDO).

Author: Robert Vajdik


REQUIREMENTS
------------

- PHP 8.0 or newer with the pdo_mysql extension
  (or pdo_sqlite for local development)
- MySQL or MariaDB
- Apache with mod_rewrite (for clean URLs and the .htaccess protections)


INSTALLATION
------------

1. Create a MySQL/MariaDB database and a user for it.
2. Copy config.sample.php to config.php and fill in the database credentials
   (and the e-mail settings, see below).
3. With 'auto_migrate' => true, the tables are created on the first visit.
   You can also create them yourself by running schema.sql.
4. Open /admin/. On the first visit you create the admin account.
5. Once the tables exist, you can set 'auto_migrate' => false.


CONFIGURATION (config.php)
--------------------------

db            driver ('mysql' or 'sqlite'), host, port, name, user, pass,
              prefix (optional table prefix, e.g. 'zoty_'),
              path (SQLite file, sqlite driver only)
auto_migrate  true = create missing tables automatically
mail          e-mail for subscriptions: transport ('smtp' or 'mail'),
              host, port, secure ('ssl' / 'tls' / 'none'), user, pass,
              from, from_name, reply_to,
              fallback_mail (use PHP mail() when SMTP fails)
keywords      meta keywords / JSON-LD; module names are added automatically

The environment variable ZOTY_CONFIG can point to a different config file.


DATABASE TABLES
---------------

changelog_versions     id, version (unique), release_date, title, summary,
                       published, created_at, updated_at
changelog_entries      id, version_id (-> versions), type, module, title,
                       body, published, created_at, updated_at
                       type: new | improved | fixed | security
changelog_modules      id, name (unique), created_at
                       seeded with DataLink, Audit, Reporty
changelog_users        id, username, password_hash, created_at
changelog_subscribers  id, email, token, confirmed, created_at,
                       confirmed_at, ip
changelog_mailings     id, version_id, recipients, created_at


ADMINISTRATION (/admin/)
------------------------

Záznamy      List of entries with filters. Create, edit, delete, publish
             or move to drafts. Each entry has a version, type, module,
             title and description (simple markdown, images can be
             uploaded, pasted or dragged in; JPG/PNG/GIF/WebP, max 5 MB).
Verze        Create a version (number, release date, optional title and
             summary), edit, hide/publish, delete when empty, and send
             a published version to the subscribers by e-mail.
             A hidden version hides all of its entries on the public site.
Moduly       Add, rename and delete modules (e.g. DataLink, Audit,
             Reporty). Renaming also updates the entries that use it.
             A module can be deleted only when no entry uses it.
Odběratelé   List of e-mail subscribers.
Export       TXT export of published entries, or including drafts.
             The current filter is applied.

Change types: Vylepšení (new, EN: Feature), Úprava (improved), Oprava (fixed),
Zranitelnosti (security, EN: Vulnerabilities).


PUBLIC SITE
-----------

/                 changelog with search and filters
                  (version, type, module, date range)
/export           TXT export, same filters as the page,
                  e.g. export?version=v1.42.07&type=fixed
/feed.xml         Atom feed
/sitemap.xml      sitemap
/robots.txt       robots
/subscribe        e-mail subscription (double opt-in)
/unsubscribe      cancel subscription

Languages: Czech and English. Texts are in lang/cs.properties and
lang/en.properties.


LOCAL DEVELOPMENT WITHOUT MYSQL
-------------------------------

In config.php set:

    'driver' => 'sqlite', 'path' => __DIR__ . '/data/changelog.sqlite'

and run:

    php -d extension=pdo_sqlite -S 127.0.0.1:8765

Public site: http://127.0.0.1:8765/
Admin:       http://127.0.0.1:8765/admin/


SECURITY
--------

On Apache, .htaccess blocks web access to config.php, lib.php, *.sql,
*.properties, *.md, *.log, readme.txt, data/, lang/ and partials/.
On nginx, add the same rules yourself.
