<?php
// Old address: /admin/help -> /admin/manual (keeps ?doc=&lang=)
$qs = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: manual' . ($qs !== '' ? '?' . $qs : ''), true, 301);
