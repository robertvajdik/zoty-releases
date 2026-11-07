<?php
// Image upload for entry descriptions. Returns JSON { url, markdown } or { error }.
require __DIR__ . '/../lib.php';

header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_admin()) fail('Nejste přihlášeni.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Pouze POST.', 405);
if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) fail('Neplatný požadavek (CSRF).');

$f = $_FILES['image'] ?? null;
if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    fail(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'Soubor je příliš velký.' : 'Soubor se nepodařilo nahrát.');
}

$maxBytes = 5 * 1024 * 1024;
if ($f['size'] > $maxBytes) fail('Obrázek je příliš velký (max. 5 MB).');

// Detect the real type from the file content, not from the name / browser.
$allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
$info = @getimagesize($f['tmp_name']);
if (!$info || !isset($allowed[$info[2]])) fail('Povolené formáty: JPG, PNG, GIF, WebP.');
$ext = $allowed[$info[2]];

// manuals keep their pictures in docs/manual/img (referenced as img/name), everything else in uploads/YYYY/MM
$forDoc = ($_GET['for'] ?? '') === 'doc';
$sub = date('Y/m');
$dir = $forDoc ? __DIR__ . '/../' . DOC_IMG_DIR : __DIR__ . '/../uploads/' . $sub;
if (!is_dir($dir) && !mkdir($dir, 0755, true)) fail('Nelze vytvořit složku pro obrázky.', 500);

$name = ($forDoc ? date('Ymd') : date('d')) . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) fail('Obrázek se nepodařilo uložit.', 500);
@chmod($dir . '/' . $name, 0644);

$alt = trim(preg_replace('/[\[\]()\r\n]+/u', ' ', pathinfo((string)$f['name'], PATHINFO_FILENAME)));
$url = $forDoc ? 'img/' . $name : 'uploads/' . $sub . '/' . $name;
echo json_encode(['url' => $url, 'markdown' => '![' . $alt . '](' . $url . ')'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
