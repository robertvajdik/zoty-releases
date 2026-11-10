<?php
// Copy this file as config.php and fill in the database credentials.
return [
    'db' => [
        // 'mysql' = MySQL / MariaDB (production), 'sqlite' = local file for development
        'driver' => 'mysql',
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'name'   => 'basketubcz05',
        'user'   => 'basketubcz006',
        'pass'   => '32ERLrrs',
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
        'user'      => 'hellozoty@basketub.cz',
        'pass'      => 'J6fp6hnn98f5',
        'from'      => 'hellozoty@basketub.cz',
        'from_name' => 'Zoty',
        'reply_to'  => '',
        // when the SMTP connection or login fails, try the hosting's PHP mail() instead
        'fallback_mail' => true,
    ],
    // Klíčová slova (meta keywords + JSON-LD). Module names are added automatically.
    // Customer support portal (/portal/): who gets an e-mail about new requests and customer comments.
    'support' => [
        'notify' => ['hellozoty@basketub.cz'],
        // 'os' => ['Windows 11', 'Windows Server 2022', 'Windows Server 2019', 'Linux', 'OpenShift', 'Kubernetes', 'Rancher'],
    ],
    'keywords' => ['Zoty', 'přehled změn', 'release notes', 'changelog', 'novinky', 'aktualizace', 'vylepšení', 'opravy', 'verze'],
];
