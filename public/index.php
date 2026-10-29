<?php
declare(strict_types=1);

/**
 * public/index.php — front controller (spec §7.4).
 * Routes: /            home (latest published)
 *         /issue/{slug} compare view (published only; else 404)
 */
require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/src/FrontMatter.php';
require dirname(__DIR__) . '/src/Markdown.php';
require dirname(__DIR__) . '/src/ContentModel.php';
require dirname(__DIR__) . '/src/Importer.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; frame-src https://www.youtube-nocookie.com; style-src 'self'; script-src 'self'");

try {
    $base = ''; // docroot = public/ (Hostinger: rewrite sends everything here)
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    if ($path === '/' || $path === '/index.php') {
        $issues = ContentModel::latest();
        require dirname(__DIR__) . '/resources/views/home.php';
        exit;
    }
    if (preg_match('#^/issue/([a-z0-9-]{1,150})$#', $path, $m) === 1) {
        $issue = ContentModel::publishedIssue($m[1]);
        if ($issue === null) {
            http_response_code(404);
            $slugRequested = $m[1];
            require dirname(__DIR__) . '/resources/views/404.php';
            exit;
        }
        // canonical URL from trusted config, never the Host header
        $canonical = ds_base_url() . '/issue/' . rawurlencode($issue['slug']);
        header('Link: <' . $canonical . '>; rel="canonical"');
        require dirname(__DIR__) . '/resources/views/issue-compare.php';
        exit;
    }
    http_response_code(404);
    require dirname(__DIR__) . '/resources/views/404.php';
} catch (Throwable $e) {
    ds_fail($e);
}
