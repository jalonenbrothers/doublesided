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
  // media: validated + allowlisted by the importer (images: site-relative
  // or https; youtube: 11-char id or safe URL). Rendered with strict
  // escaping; YouTube goes through the whitelisted youtube-nocookie
  // host only — never an arbitrary embed URL.
  $youtubeId = null;
  foreach ($issue['media'] ?? [] as $mItem):
      if ($mItem['media_type'] === 'image'): ?>
    <figure class="media">
      <img src="<?= ds_e($mItem['url_or_path']) ?>" alt="<?= ds_e($mItem['alt_text']) ?>" loading="lazy">
      <?php if (($mItem['caption'] ?? '') !== ''): ?>
      <figcaption><?= ds_e($mItem['caption']) ?><?php if (($mItem['credit'] ?? '') !== ''): ?> — <span class="credit"><?= ds_e($mItem['credit']) ?></span><?php endif; ?></figcaption>
      <?php endif; ?>
    </figure>
  <?php elseif ($mItem['media_type'] === 'youtube'):
          $youtubeId = preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})#', (string)$mItem['url_or_path'], $ym)
              ? $ym[1]
              : (preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$mItem['url_or_path']) ? $mItem['url_or_path'] : null);
          if ($youtubeId !== null): ?>
    <figure class="media video-embed-wrap">
      <div class="video-embed">
        <iframe src="https://www.youtube-nocookie.com/embed/<?= ds_e($youtubeId) ?>"
                title="<?= ds_e($mItem['caption'] !== '' ? $mItem['caption'] : 'YouTube video player') ?>"
                loading="lazy" allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
      </div>
      <?php if (($mItem['caption'] ?? '') !== ''): ?>
      <figcaption><?= ds_e($mItem['caption']) ?></figcaption>
      <?php endif; ?>
    </figure>
  <?php endif;
      endif;
  endforeach; ?>

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
