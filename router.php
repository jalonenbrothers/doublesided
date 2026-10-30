<?php declare(strict_types=1);
// dev router: mimic Hostinger rewrite — everything -> public/index.php.
// The whole inner URI (incl. its query string) travels in ?r=..., so the
// inner query params must be re-parsed into $_GET for the app to see them.
$r = $_GET['r'] ?? '/';
$_SERVER['REQUEST_URI'] = $r;
$qPos = strpos($r, '?');
if ($qPos !== false) {
    parse_str(substr($r, $qPos + 1), $inner);
    $_GET = array_merge($_GET, $inner);
    $_SERVER['QUERY_STRING'] = substr($r, $qPos + 1);
}
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
