<?php
require __DIR__ . '/../lib.php';
require_admin();

// Admin TXT export – can include drafts (?drafts=1).
$filters = current_filters($_GET);
$drafts  = !empty($_GET['drafts']);
send_txt(fetch_entries($filters, $drafts), $filters, $drafts ? '-vcetne-konceptu' : '');
