<?php
// Old address: /admin/help.php -> /admin/manual.php (keeps ?doc=&lang=)
$qs = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: manual.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
