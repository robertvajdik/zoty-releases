<?php
// XML sitemap – served as /sitemap.xml (see .htaccess)
require __DIR__ . '/lib.php';

$rows = db()->query(
    'SELECT v.version, v.release_date, MAX(e.updated_at) AS updated'
    . ' FROM ' . t('changelog_versions') . ' v'
    . ' JOIN ' . t('changelog_entries') . ' e ON e.version_id = v.id AND e.published = 1'
    . ' WHERE v.published = 1'
    . ' GROUP BY v.id, v.version, v.release_date'
    . ' ORDER BY v.release_date DESC, v.id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$lastmod = function (?string $dt): string {
    $t = $dt ? strtotime($dt) : false;
    return $t ? date('Y-m-d', $t) : date('Y-m-d');
};
$x = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

$latest = null;
foreach ($rows as $r) if ($latest === null || $r['updated'] > $latest) $latest = $r['updated'];

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>' . $x(site_url()) . '</loc><lastmod>' . $lastmod($latest) . '</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>' . "\n";
foreach ($rows as $r) {
    echo '  <url><loc>' . $x(site_url('?version=' . rawurlencode($r['version']))) . '</loc>'
       . '<lastmod>' . $lastmod($r['updated'] ?: $r['release_date']) . '</lastmod><priority>0.6</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
