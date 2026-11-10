<?php
// Serves a download-center PDF to a signed-in customer, or to an admin.
require __DIR__ . '/../lib.php';

$d = get_download((int)($_GET['id'] ?? 0));
$allowed = $d && (is_admin() || portal_user());
$path = $allowed ? download_path($d) : '';

if (!$allowed || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

$name = (string)$d['name'];
$ascii = preg_replace('/[^\x20-\x7e]|["\\\\]/', '_', $name);
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);
