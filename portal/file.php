<?php
// Serves a ticket attachment to the customer who owns the ticket, or to an admin.
require __DIR__ . '/../lib.php';

$f = get_ticket_file((int)($_GET['id'] ?? 0));
$ticket = $f ? get_ticket((int)$f['ticket_id']) : null;
$user = portal_user();
$allowed = $ticket && (is_admin() || ($user && (int)$user['customer_id'] === $ticket['customer_id']));
$path = $allowed ? ticket_file_path($f) : '';

if (!$allowed || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

$inline = ticket_file_is_image($f);   // everything else downloads (a sandboxed PDF would not render)
$name = (string)$f['name'];
$ascii = preg_replace('/[^\x20-\x7e]|["\\\\]/', '_', $name);
header('Content-Type: ' . ($inline ? $f['mime'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=3600');
readfile($path);
