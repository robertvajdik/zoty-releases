<?php
require __DIR__ . '/../lib.php';
// Signs out the customer only – an admin session in the same browser stays.
unset($_SESSION['zoty_customer']);
session_regenerate_id(true);
header('Location: login');
