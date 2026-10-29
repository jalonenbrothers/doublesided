<?php
declare(strict_types=1);
$title = 'Not found — Two Sides';
http_response_code(404);
require __DIR__ . '/partials/head.php';
?>
<h1>404 — page not found</h1>
<p>That issue doesn't exist, or it isn't published yet.
Drafts and in-review content are never visible here.</p>
<p><a href="/">Back to the home page</a></p>
<?php require __DIR__ . '/partials/foot.php'; ?>
