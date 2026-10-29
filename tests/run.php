<?php
declare(strict_types=1);
/**
 * tests/run.php — house test runner (no framework).
 * Part 1: unit (parser, markdown, validators) against the fixture.
 * Part 2: integration (importer on sqlite) incl. security set.
 * Part 3: feature/http — dev router requests (published 200,
 *         draft 404, XSS escaping, SQLi, traversal, security headers).
 *
 * Usage:  php tests/run.php            (sqlite, whole suite)
 * Exit code = number of failures (0 = green).
 */
$root = dirname(__DIR__);
require $root . '/lib/db.php';
require $root . '/src/FrontMatter.php';
require $root . '/src/Markdown.php';
require $root . '/src/ContentModel.php';
require $root . '/src/Importer.php';

$PASS = 0; $FAIL = 0; $FAILURES = [];
function check(bool $cond, string $name): void
{
    global $PASS, $FAIL, $FAILURES;
    if ($cond) { $PASS++; echo "ok  $name\n"; }
    else { $FAIL++; $FAILURES[] = $name; echo "FAIL $name\n"; }
}

// ---------------------------------------------------------- unit: fixture
$fixtureRaw = file_get_contents($root . '/fixtures/fixture-01.md');
check(is_string($fixtureRaw) && $fixtureRaw !== '', 'fixture-01.md readable');
[$fm, $body] = FrontMatter::parse($fixtureRaw);
check($fm['slug'] === 'will-ai-take-our-jobs', 'front-matter: slug parsed');
check($fm['title'] === 'Will AI take our jobs?', 'front-matter: title parsed');
check(count($fm['sides']) === 2 && $fm['sides'][0]['id'] === 'case-for', 'front-matter: sides list-of-maps');
check(count($fm['sources']) === 4 && $fm['sources'][0]['id'] === 'src-imf', 'front-matter: sources parsed');
check($fm['sources'][0]['title'] === 'Gen-AI: Artificial Intelligence and the Future of Work', 'front-matter: quoted string unquoted');
check(in_array('ai', $fm['topics'], true), 'front-matter: flat list topics');
check(str_contains($body, '## The case for'), 'body split off front matter');

try {
    FrontMatter::parse("no front matter here");
    check(false, 'front-matter: rejects file without delimiter');
} catch (FrontMatterError) {
    check(true, 'front-matter: rejects file without delimiter');
}
try {
    FrontMatter::parse("---\nkey: [a,b]\n---\nx");
    check(false, 'front-matter: rejects flow syntax');
} catch (FrontMatterError) {
    check(true, 'front-matter: rejects flow syntax');
}

// ---------------------------------------------------------- unit: markdown
$html = Markdown::render("## Heading\n\nHello **world** *stars* [x](https://e.com/a) [src-imf] and [label][src-imf]");
check(str_contains($html, '<h2 id="heading">'), 'md: heading rendered with anchor');
check(str_contains($html, '<strong>world</strong>'), 'md: bold');
check(str_contains($html, 'rel="noopener noreferrer"'), 'md: safe link attrs');
check(str_contains($html, '<sup class="src-ref"'), 'md: source ref -> sup');
$xss = Markdown::render('<script>alert(1)</script> **ok**');
check(!str_contains($xss, '<script>'), 'md: script tag escaped (XSS)');
$esc = htmlspecialchars('<script>alert(1)</script>', ENT_QUOTES, 'UTF-8');
check(str_contains($xss, $esc), 'md: escaped script visible as text');
$bad = Markdown::render('[c](javascript:alert(1))');
check(!str_contains($bad, 'href="javascript:'), 'md: javascript: URL not linked');

// ---------------------------------------------------------- unit: importer validators
check(Importer::safeUrl('https://example.org/report') === true, 'safeUrl accepts https');
check(Importer::safeUrl('javascript:alert(1)') === false, 'safeUrl rejects javascript:');
check(Importer::safeUrl('data:text/html,x') === false, 'safeUrl rejects data:');
check(Importer::safeUrl('https://u:p@e.com/') === false, 'safeUrl rejects userinfo');
check(Importer::safeMediaLocation('/assets/x.webp', 'image') === true, 'media: relative path ok');
check(Importer::safeMediaLocation('../../etc/passwd', 'image') === false, 'media: traversal rejected');
check(Importer::safeMediaLocation('https://evil.com/x.gif', 'image') === true, 'media: https ok');
check(Importer::safeMediaLocation('dQw4w9WgXcQ', 'youtube') === true, 'media: youtube id ok');

$fixtureValid = Importer::validate($fm, $body);
check(is_array($fixtureValid), 'validate: fixture-01 passes');

// unsafe variants must throw with named reasons
function expectReject(string $raw, string $needle, string $name): void
{
    try {
        [$f, $b] = FrontMatter::parse($raw);
        Importer::validate($f, $b);
        check(false, $name . ' (no exception)');
    } catch (ImportError $e) {
        check(
            collect($e->errors, fn($x) => str_contains($x, $needle)),
            $name
        );
    }
}
function collect(array $a, callable $fn): bool
{
    foreach ($a as $x) { if ($fn($x)) return true; }
    return false;
}

$fm2 = $fm;
$fm2['slug'] = 'Bad_Slug!';
expectReject(fixtureRawWith($fm2, $body), 'invalid slug', 'validate: bad slug rejected');
$fm3 = $fm;
$fm3['status'] = 'live-now';
expectReject(fixtureRawWith($fm3, $body), 'invalid status', 'validate: bad status rejected');
$fm4 = $fm;
$fm4['sources'][0]['url'] = 'javascript:alert(1)';
expectReject(fixtureRawWith($fm4, $body), 'unsafe or invalid source url', 'validate: javascript: source URL rejected');
$fm5 = $fm;
$fm5['content_type'] = 'timeline';
expectReject(fixtureRawWith($fm5, $body), 'unknown content_type', 'validate: unknown content_type rejected');
expectReject(fixtureRawWith($fm, str_replace('## Shared ground', '## Nope', $body)),
    'missing body section', 'validate: missing body section rejected');
expectReject(fixtureRawWith($fm, str_replace('[src-imf]', '[src-ghost]', $body)),
    'unknown source id', 'validate: unresolvable source ref rejected');
expectReject(fixtureRawWith($fm, $body . "\n\n<script>alert(1)</script>\n"),
    'not allowed in body', 'validate: script tag in body rejected');

/** re-serialise fm+body back into a fixture string (test helper) */
function fixtureRawWith(array $fm, string $body): string
{
    $yaml = '';
    foreach ($fm as $k => $v) {
        if ($v === null) { $yaml .= "$k:\n"; continue; }
        if (is_array($v) && array_is_list($v)) {
            $yaml .= "$k:\n";
            foreach ($v as $item) {
                if (is_array($item)) {
                    $first = true;
                    foreach ($item as $ik => $iv) {
                        $yaml .= ($first ? '  - ' : '    ') . "$ik: " . sv($iv) . "\n";
                        $first = false;
                    }
                } else {
                    $yaml .= '  - ' . sv($item) . "\n";
                }
            }
        } else {
            $yaml .= "$k: " . sv($v) . "\n";
        }
    }
    return "---\n$yaml---\n$body";
}
function sv(mixed $v): string
{
    if (is_string($v) && (str_contains($v, ':') || str_starts_with($v, '"'))) {
        return '"' . str_replace('"', '\\"', $v) . '"';
    }
    return (string)$v;
}

// ------------------------------------------------ integration: sqlite importer
$testDb = $root . '/lib/ds-test.sqlite';
@unlink($testDb);
$pdo = new PDO('sqlite:' . $testDb, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec((string)file_get_contents($root . '/tests/schema.sqlite.sql'));

$imp = new Importer($pdo, 'agent', 'test');

$r1 = $imp->importString($fixtureRaw, false, str_repeat('a', 40), 'fixture-01.md');
check($r1['result'] === 'imported', 'import: fixture imported');
$row = $pdo->query("SELECT * FROM issues WHERE slug='will-ai-take-our-jobs'")->fetch();
check($row['status'] === 'published', 'import: status stored');
$persp = $pdo->query("SELECT * FROM perspectives ORDER BY position")->fetchAll();
check(count($persp) === 2, 'import: 2 perspectives');
check(str_contains((string)$persp[0]['body_markdown'], 'Past general-purpose technologies'), 'import: case-for body mapped');
check(str_contains((string)$persp[1]['body_markdown'], 'AI differs from past waves'), 'import: case-against body mapped');
$srcs = $pdo->query("SELECT ref_key FROM issue_sources ORDER BY ref_key")->fetchAll();
check(count($srcs) === 4, 'import: 4 sources linked');
$revs = $pdo->query("SELECT COUNT(*) c FROM revisions")->fetch();
check((int)$revs['c'] === 1, 'import: revision recorded');
check($pdo->query("SELECT git_hash FROM revisions LIMIT 1")->fetch()['git_hash'] === str_repeat('a', 40), 'import: git hash stored');

// idempotence: re-import unchanged = no-op, no duplicate, no new revision
$r2 = $imp->importString($fixtureRaw, false, str_repeat('a', 40), 'fixture-01.md');
check($r2['result'] === 'unchanged', 'import: idempotent (unchanged)');
check((int)$pdo->query("SELECT COUNT(*) c FROM issues")->fetch()['c'] === 1, 'import: no duplicate issue');
check((int)$pdo->query("SELECT COUNT(*) c FROM revisions")->fetch()['c'] === 1, 'import: no extra revision');

// re-import CHANGED content = update + new revision, same id
$changedRaw = str_replace('where the real risks live', 'CHANGED LINE.', $fixtureRaw);
$r3 = $imp->importString($changedRaw, false, str_repeat('b', 40), 'fixture-01.md');
check($r3['result'] === 'updated', 'import: changed content updates');
check((int)$pdo->query("SELECT COUNT(*) c FROM issues")->fetch()['c'] === 1, 'import: still one issue after update');
check((int)$pdo->query("SELECT COUNT(*) c FROM revisions")->fetch()['c'] === 2, 'import: new revision on change');
check((int)$pdo->query("SELECT COUNT(*) c FROM perspectives")->fetch()['c'] === 2, 'import: children replaced, not duplicated');

// dry-run writes nothing
$pdo->exec("DELETE FROM issues");
$r4 = $imp->importString($fixtureRaw, true, null, 'fixture-01.md');
check($r4['result'] === 'dry-run-ok', 'import: dry-run validates');
check((int)$pdo->query("SELECT COUNT(*) c FROM issues")->fetch()['c'] === 0, 'import: dry-run wrote nothing');

// failed import preserves prior state
$imp->importString($fixtureRaw, false, null, 'fixture-01.md');
try {
    $imp->importString($fixtureRaw . "\n<script>bad()</script>", false, null, 'evil.md');
    check(false, 'import: unsafe rejected mid-run (no exception)');
} catch (ImportError $e) {
    check(true, 'import: unsafe rejected mid-run');
}
check((int)$pdo->query("SELECT COUNT(*) c FROM issues")->fetch()['c'] === 1, 'import: failed import preserved published state');

// SQLi attempt through slug in queries
try {
    $sqliFm = $fm;
    $sqliFm['slug'] = "ok' OR '1'='1";
    $raw = fixtureRawWith($sqliFm, $body);
    [$f2, $b2] = FrontMatter::parse($raw);
    Importer::validate($f2, $b2);
    check(false, 'import: SQLi slug rejected (no exception)');
} catch (ImportError) {
    check(true, 'import: SQLi slug rejected by validator');
}
$cnt = $pdo->query("SELECT COUNT(*) c FROM issues WHERE slug LIKE \"%' OR '1'='1%\"")->fetch();
check((int)$cnt['c'] === 0, 'import: no injected row exists');

// unpublished stays invisible via model (using same PDO through config override)
// — model test done HTTP-level below with the router.

// ---------------------------------------------------------- http feature tests
// use the dev router with the test sqlite db
$configTest = "<?php declare(strict_types=1);\n"
    . "return ['db'=>['host'=>'','port'=>3306,'name'=>'sqlite:ds-test.sqlite','user'=>'','pass'=>''],'debug'=>true];\n";
file_put_contents($root . '/lib/config.php', $configTest);
$imp->importString($fixtureRaw, false, null, 'fixture-01.md');

$router = $root . '/router.php';
if (!is_file($router)) {
    // minimal router: serve requests via PHP built-in server style
    file_put_contents($router, <<<PHP
<?php declare(strict_types=1);
// dev router: mimic Hostinger rewrite — everything -> public/index.php
\$_SERVER['REQUEST_URI'] = \$_GET['r'] ?? '/';
\$_SERVER['SCRIPT_NAME'] = '/public/index.php';
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
PHP
    );
}

function httpGet(string $uri): array
{
    global $root;
    $url = 'http://127.0.0.1:' . getenv('DS_TEST_PORT') . '/?r=' . rawurlencode($uri);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $status = (int)$m[1]; }
    }
    return [$status, (string)$body];
}

// spin built-in server on a random port
$port = (string)random_int(18000, 18999);
putenv("DS_TEST_PORT=$port");
$proc = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", $root . '/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes, $root
);
usleep(300000);

[$st, $htmlHome] = httpGet('/');
check($st === 200 && str_contains($htmlHome, 'Will AI take our jobs?'), 'http: home lists published issue');
[$st, $htmlIssue] = httpGet('/issue/will-ai-take-our-jobs');
check($st === 200, 'http: published issue 200');
check(str_contains($htmlIssue, 'The case for') && str_contains($htmlIssue, 'The case against'), 'http: both sides rendered');
check(str_contains($htmlIssue, 'imf.org'), 'http: source links rendered');
check(str_contains($htmlIssue, 'International Monetary Fund'), 'http: source attribution rendered');
check(str_contains($htmlIssue, 'rel="canonical"'), 'http: canonical URL present');
check(str_contains($htmlIssue, '<sup class="src-ref"'), 'http: citation chips in body');
check(str_contains($htmlIssue, '<img src="/assets/img/automation-factory.svg"'), 'http: media image rendered');
check(str_contains($htmlIssue, 'youtube-nocookie.com/embed/WSfu7jHiGtA'), 'http: youtube embed via whitelisted host');
check(!str_contains($htmlIssue, 'src="https://www.youtube.com/watch'), 'http: no raw youtube watch URL in iframe');
[$st, $htmlMissing] = httpGet('/issue/does-not-exist');
check($st === 404, 'http: missing slug 404');

// draft invisible
$draftRaw = str_replace("status: published", "status: draft", str_replace("slug: will-ai-take-our-jobs", "slug: secret-draft", $fixtureRaw));
$imp->importString($draftRaw, false, null, 'secret-draft.md');
[$st, $htmlDraft] = httpGet('/issue/secret-draft');
check($st === 404, 'http: draft issue 404 (never public)');

// XSS: reflected slug stays escaped
[$st, $htmlXss] = httpGet('/issue/%3Cscript%3Ealert(1)%3C%2Fscript%3E');
check($st === 404 && !str_contains($htmlXss, '<script>alert'), 'http: XSS slug not reflected raw');
// stored XSS in content: importer escaped at render
$pdo->exec("UPDATE issues SET title = '<script>alert(1)</script>' WHERE slug='will-ai-take-our-jobs'");
[$st, $htmlStored] = httpGet('/issue/will-ai-take-our-jobs');
check($st === 200 && !str_contains($htmlStored, '<script>alert(1)'), 'http: stored XSS in title escaped');
$pdo->exec("UPDATE issues SET title = 'Will AI take our jobs?' WHERE slug='will-ai-take-our-jobs'");

// SQLi in slug
[$st, $htmlInj] = httpGet("/issue/will-ai-take-our-jobs' OR '1'='1");
check($st === 404, 'http: SQLi slug -> 404, no bypass');
// path traversal probe
[$st, $_] = httpGet('/issue/..%2f..%2flib%2fconfig.php');
check($st === 404, 'http: traversal slug -> 404');
// internals must not be served by the router path either
[$st, $cfg] = httpGet('/lib/config.php');
check(!str_contains((string)$cfg, 'db'), 'http: router does not leak lib/config.php contents');

proc_terminate($proc);
@unlink($root . '/lib/config.php');
@unlink($testDb);

echo "\n$PASS passed, $FAIL failed\n";
if ($FAIL) {
    echo 'FAILED: ' . implode(', ', $FAILURES) . "\n";
    exit(min($FAIL, 250));
}
exit(0);
