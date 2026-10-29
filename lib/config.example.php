<?php
declare(strict_types=1);

return [
    // --- database (required) ---
    'db' => [
        'host'    => 'localhost',   // Hostinger preset; rarely changes
        'port'    => 3306,
        'name'    => 'u123456789_doublesided',
        'user'    => 'u123456789_doublesided',
        'pass'    => 'CHANGE_ME',
    ],

    // --- app (optional, one line) ---
    'app_url' => 'https://twosides.earth-turtle.com', // canonical URLs; Host header NEVER trusted

    // --- debug (always present) ---
    'debug' => false, // true = display errors, no .htaccess-internal blocking in logs
];
