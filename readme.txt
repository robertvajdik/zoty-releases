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
support       customer portal: notify (staff e-mails for new requests and
              customer comments), os (Operating System choices)
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
changelog_customers    id, name, code (ticket key prefix, unique), created_at
changelog_customer_users  id, customer_id, email (unique), name,
                       password_hash, active, lang, created_at, last_login
changelog_tickets      id, customer_id, number (per customer), user_id, type,
                       product, product_version, os, summary, description,
                       severity, status, created_at, updated_at
changelog_ticket_comments  id, ticket_id, user_id (NULL = support),
                       staff_name, body, event, created_at
changelog_docs         id, slug (admin | portal), lang, body (Markdown),
                       updated_at, updated_by – manuals, seeded from
                       "Zoty Release Notes & Customer Support.md" and docs/portal.*.md on first view
changelog_ticket_files id, ticket_id, comment_id, name, path, mime, size,
                       created_at (files in data/tickets/<ticket_id>/)


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
Požadavky    Support queue: all customer requests with tabs by status and
             filters (customer, type, severity, text or key ACME-12).
             Detail: reply to the customer (with attachments), change
             status and severity, delete. The customer is e-mailed about
             replies and status changes.
Zákazníci    Customers and their portal users. Each customer has a key
             (ACME) used for request numbers and its own space: its users
             see all requests of that customer and nothing else. Add users
             with a password (optionally e-mailed to them), block, delete.
Export       Markdown (.md) export of published entries, or including drafts.
             The current filter is applied.

Change types: Vylepšení (new, EN: Feature), Úprava (improved), Oprava (fixed),
Zranitelnosti (security, EN: Vulnerabilities).


PUBLIC SITE
-----------

/                 changelog with search and filters
                  (version, type, module, date range)
/export           Markdown export, same filters as the page,
                  e.g. export?version=v1.42.07&type=fixed
/portal/          customer support portal (sign-in for customer users):
                  list of the customer's requests, new request
                  (Support Incident / Question / Feature Request),
                  request detail with comments and attachments
/portal/help      customer help (signed-in customers only, CS/EN, edited in admin)
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
