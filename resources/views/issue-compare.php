<?php
declare(strict_types=1);
/**
 * issue-compare.php — the compare (split) view: two perspectives side
 * by side on desktop, stacked A→B on mobile, then shared ground,
 * evidence, uncertainty and synthesis below. Server-rendered; JS not
 * required (spec §20 items 6-8).
 *
 * $issue = ContentModel::publishedIssue() row.
 */
$title = $issue['title'] . ' — Two Sides';
$description = $issue['summary'];
[$sideA, $sideB] = array_values($issue['perspectives']);
require __DIR__ . '/partials/head.php';
?>
<article class="issue" data-issue="<?= ds_e($issue['slug']) ?>">
  <header class="issue-head">
    <h1><?= ds_e($issue['title']) ?></h1>
    <p class="dek"><?= ds_e($issue['dek'] ?? $issue['summary']) ?></p>
    <p class="meta">Published <?= ds_e((string)($issue['published_at'] ?? '')) ?>
      · reviewed <?= ds_e((string)($issue['reviewed_at'] ?? '—')) ?></p>
    <ul class="topics">
      <?php foreach ($issue['topics'] as $t): ?>
        <li class="topic">#<?= ds_e($t['slug']) ?></li>
      <?php endforeach; ?>
    </ul>
  </header>

  <section class="compare" aria-label="Both perspectives">
    <div class="side side-a">
      <h2 class="side-label a"><?= ds_e($sideA['label']) ?></h2>
      <p class="side-summary"><?= ds_e($sideA['short_summary']) ?></p>
      <?= Markdown::render((string)$sideA['body_markdown']) ?>
    </div>
    <div class="side side-b">
      <h2 class="side-label b"><?= ds_e($sideB['label']) ?></h2>
      <p class="side-summary"><?= ds_e($sideB['short_summary']) ?></p>
      <?= Markdown::render((string)$sideB['body_markdown']) ?>
    </div>
  </section>

  <?php
  // shared ground / unknowns live in the fixture's body sections; they are
  // also parsed from body_markdown of the perspectives? No — they were
  // stored as body sections. Re-derive them from the DB shared_ground/
  // unknowns columns when present; the importer stored them in the issue
  // sections (Phase 1 view modes will get dedicated routes).
  ?>
  <?php if (!empty($issue['shared_ground'])): ?>
  <section class="shared-ground">
    <h2>Shared ground</h2>
    <?= Markdown::render((string)$issue['shared_ground']) ?>
  </section>
  <?php endif; ?>
  <?php if (!empty($issue['unknowns'])): ?>
  <section class="unknowns">
    <h2>What remains uncertain</h2>
    <?= Markdown::render((string)$issue['unknowns']) ?>
  </section>
  <?php endif; ?>

  <section class="sources" id="sources">
    <h2>Sources</h2>
    <ol class="source-list">
      <?php foreach ($issue['sources'] as $s): ?>
        <li id="source-<?= ds_e($s['ref_key']) ?>" class="source src-<?= ds_e($s['source_type']) ?>">
          <a href="<?= ds_e($s['url']) ?>" rel="noopener noreferrer" target="_blank">
            <?= ds_e($s['title']) ?></a>
          — <?= ds_e($s['publisher']) ?>
          (<?= ds_e($s['source_type']) ?><?= $s['published_at'] ? ', ' . ds_e($s['published_at']) : '' ?>)
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</article>
<?php require __DIR__ . '/partials/foot.php'; ?>
