<?php declare(strict_types=1);
// dev router: mimic Hostinger rewrite — everything -> public/index.php
$_SERVER['REQUEST_URI'] = $_GET['r'] ?? '/';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';