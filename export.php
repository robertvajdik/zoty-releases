<?php
require __DIR__ . '/lib.php';

// Public Markdown export – published entries only, honours the same filters as the page.
$filters = current_filters($_GET);
send_md(fetch_entries($filters), $filters);
