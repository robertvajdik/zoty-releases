<?php
// Pictures of the manuals (docs/manual/img) – only for signed-in admins and portal customers.
require __DIR__ . '/lib.php';

$path = doc_img_file((string)($_GET['f'] ?? ''));
if (!$path) {
    http_response_code(404);
    exit('Not found');
}
if (!is_admin() && !portal_user()) {
    http_response_code(403);
    exit('Forbidden');
}

$etag = '"' . md5($path . filemtime($path) . filesize($path)) . '"';
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . DOC_IMG_EXT[strtolower(pathinfo($path, PATHINFO_EXTENSION))]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline');
readfile($path);
