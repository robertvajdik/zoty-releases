<?php
// Copy this file as config.php and fill in the database credentials.
return [
    'db' => [
        // 'mysql' = MySQL / MariaDB (production), 'sqlite' = local file for development
        'driver' => 'mysql',
        'host'   => 'localhost',
        'port'   => 3306,
        'name'   => 'zoty_changelog',
        'user'   => 'zoty',
        'pass'   => 'CHANGE_ME',
        'prefix' => '',            // table prefix, e.g. 'zoty_'
        // 'path' => __DIR__ . '/data/changelog.sqlite',   // driver = sqlite only
    ],
    // Create tables automatically on first connect (CREATE TABLE IF NOT EXISTS).
    // Set to false once the tables exist, or create them yourself from schema.sql.
    'auto_migrate' => true,
    // E-mail for subscriptions (confirmation + new-version announcements).
    // transport: 'smtp' (recommended) or 'mail' (PHP mail()). secure: 'ssl' (port 465) | 'tls' (587) | 'none'
    'mail' => [
        'transport' => 'smtp',
        'host'      => 'smtp.cesky-hosting.cz',
        'port'      => 465,
        'secure'    => 'ssl',
        'user'      => 'info@example.cz',
        'pass'      => 'CHANGE_ME',
        'from'      => 'info@example.cz',
        'from_name' => 'Zoty',
        'reply_to'  => '',
        // when the SMTP connection or login fails, try the hosting's PHP mail() instead
        'fallback_mail' => true,
    ],
    // Klíčová slova (meta keywords + JSON-LD). Module names are added automatically.
    'keywords' => ['Zoty', 'přehled změn', 'release notes', 'changelog', 'novinky', 'aktualizace', 'vylepšení', 'opravy', 'verze'],
];
