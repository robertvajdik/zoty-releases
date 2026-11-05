<?php
// Atom feed of published versions – served as /feed.xml (see .htaccess)
require __DIR__ . '/lib.php';
header('Content-Type: application/atom+xml; charset=utf-8');
echo atom_feed();
