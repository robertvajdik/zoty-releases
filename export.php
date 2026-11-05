<?php
require __DIR__ . '/lib.php';

// Public TXT export – published entries only, honours the same filters as the page.
$filters = current_filters($_GET);
send_txt(fetch_entries($filters), $filters);
