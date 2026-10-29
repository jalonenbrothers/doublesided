<?php
declare(strict_types=1);

/**
 * bin/import-content.php — CLI importer (spec §9.2).
 *
 * Usage:
 *   php bin/import-content.php <file.md> [--dry-run]
 *   php bin/import-content.php <dir> [--dry-run]
 *   php bin/import-content.php fixtures/fixture-01.md
 *
 * Exit 0 on success (even with skips), non-zero on any failure
 * (strict mode: any rejected file fails the run — CI gate).
 */
require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/src/FrontMatter.php';
require dirname(__DIR__) . '/src/Markdown.php';
require dirname(__DIR__) . '/src/ContentModel.php';
require dirname(__DIR__) . '/src/Importer.php';

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => $a !== '--dry-run'));
$dryRun = in_array('--dry-run', $argv, true);
if (count($args) !== 1) {
    fwrite(STDERR, "usage: php bin/import-content.php <file.md|dir> [--dry-run]\n");
    exit(2);
}
$target = $args[0];

$gitHash = null;
exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' rev-parse HEAD 2>/dev/null', $out, $rc);
if ($rc === 0 && isset($out[0]) && strlen((string)$out[0]) === 40) {
    $gitHash = (string)$out[0];
}

try {
    $importer = new Importer(ds_db(), 'agent', 'cli-importer');
    if (is_dir($target)) {
        $summary = $importer->importDir($target, $dryRun);
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
        ds_q('INSERT INTO imports (run_at, files_seen, accepted, rejected, notes) VALUES (?,?,?,?,?)',
            [date('Y-m-d H:i:s'), $summary['seen'], $summary['accepted'], $summary['rejected'],
             $dryRun ? 'dry-run' : 'live import']);
        exit($summary['rejected'] > 0 ? 1 : 0);
    }
    $r = $importer->importFile($target, $dryRun, $gitHash);
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    ds_q('INSERT INTO imports (run_at, files_seen, accepted, rejected, notes) VALUES (?,?,?,?,?)',
        [date('Y-m-d H:i:s'), 1, 1, 0, $dryRun ? 'dry-run' : $r['result']]);
    exit(0);
} catch (ImportError $e) {
    fwrite(STDERR, "REJECTED: " . basename($target) . "\n");
    foreach ($e->errors as $er) {
        fwrite(STDERR, "  - $er\n");
    }
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'error: ' . $e->getMessage() . "\n");
    if (ds_debug()) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}
