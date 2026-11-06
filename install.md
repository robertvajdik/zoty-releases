# Zoty Release Notes

A changelog site in PHP. The layout follows docs.aris.com/release notes, and the look comes from zoty.cz.
The changelog and admin accounts are stored in a database (MySQL/MariaDB through PDO).

## Install

1. Create a MySQL/MariaDB database and a user for it.
2. Fill in the credentials in `config.php` (the template is `config.sample.php`).
3. With `'auto_migrate' => true`, the tables are created on the first visit. You can also create them yourself from `schema.sql`.
4. Open `/admin/`. On the first visit you create the admin account (it's stored in the `changelog_users` table).

Requirements: PHP 8.0+ with the `pdo_mysql` extension (or `pdo_sqlite` for local development).

## Database tables

- `changelog_versions`: id, version (unique), release_date, title, summary, published, created_at, updated_at
- `changelog_entries`: id, version_id (→ versions), type (new/improved/fixed/security), module, title, body, published, created_at, updated_at
- `changelog_users`: id, username, password_hash, created_at

Use `'prefix'` in `config.php` to add a table prefix.

## Local development without MySQL

In `config.php`, set `'driver' => 'sqlite', 'path' => __DIR__ . '/data/changelog.sqlite'`, then run:

    php -d extension=pdo_sqlite -S 127.0.0.1:8765

Public page: http://127.0.0.1:8765/ · Admin: http://127.0.0.1:8765/admin/

Alternatively, the environment variable `ZOTY_CONFIG` can point to a different config file.

## Admin

- **Verze** (`admin/versions`): create a new version (number, release date, optional title and summary), edit it,
  hide or publish it, and delete it if it has no entries. A hidden version hides all of its entries on the public site.
- Entries: create, edit, delete, publish or move to drafts. Each entry belongs to a version, picked from a list
  (or create one right in the entry form with "+ Nová verze…"), and has a type,
  module, title and description (simple markdown).
- TXT export of published entries only, or including drafts. The current filter applies.

## TXT export

`export` accepts the same filters as the page, e.g. `export?version=2.4.0&type=fixed`.

## Security

On Apache, `.htaccess` blocks web access to `config.php`, `lib.php`, `*.sql`, `data/` and `partials/`.
On nginx, add the same rules yourself.


- Email: zoty@idsa.cz 
- Heslo: kHkzmqCAWAbUHxIB