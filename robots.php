<?php
// robots.txt – served as /robots.txt (see .htaccess), so paths and the sitemap URL
// are correct whether the app runs at the domain root or in a subfolder.
require __DIR__ . '/lib.php';

$path = parse_url(site_url(), PHP_URL_PATH) ?: '/';
header('Content-Type: text/plain; charset=utf-8');
echo "# Zoty – přehled změn\n";
echo "User-agent: *\n";
foreach (['admin/', 'portal/', 'data/', 'partials/', 'export', 'subscribe', 'unsubscribe'] as $p) echo 'Disallow: ' . $path . $p . "\n";
echo "Allow: " . $path . "\n\n";
echo 'Sitemap: ' . site_url('sitemap.xml') . "\n";
