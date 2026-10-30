<?php
declare(strict_types=1);
/**
 * issue-compare.php — compare page with view modes (spec §15 Phase 3):
 * merged / side A / side B / evidence / timeline / compact.
 *
 * Server-rendered: ALL views are in the DOM, non-active ones carry the
 * `hidden` attribute; the active one comes from ?view= (whitelisted in
 * index.php). Without JS the view toggles are ordinary links (?view=x)
 * that reload the page — full progressive enhancement. With JS,
 * /assets/js/views.js switches instantly, syncs scroll and keeps the
 * URL shareable via history.replaceState; the canonical URL stays the
 * bare issue URL, so view modes never create duplicate-content URLs.
 *
 * $issue    = ContentModel::publishedIssue() row (now includes media,
 *             orientation, shared_ground, evidence, unknowns, synthesis).
 * $view     = active view key (whitelisted).
 * $canonical= bare canonical URL (no ?view= — modes are not pages).
 */
$title = $issue['title'] . ' — Two Sides';
$description = $issue['summary'];
[$sideA, $sideB] = array_values($issue['perspectives']);

$views = [
    'merged'   => 'Both sides',
    'side-a'   => 'Side A: ' . $sideA['label'],
    'side-b'   => 'Side B: ' . $sideB['label'],
    'evidence' => 'Evidence',
    'timeline' => 'Timeline',
    'compact'  => 'Compact',
];

/** render one perspective column */
$renderSide = function (array $side, string $key) use ($issue): string {
    ob_start(); ?>
    <div class="side side-<?= $key ?>">
      <h2 class="side-label <?= $key ?>"><?= ds_e($side['label']) ?></h2>
      <p class="side-summary"><?= ds_e($side['short_summary']) ?></p>
      <?= Markdown::render((string)$side['body_markdown']) ?>
    </div>
    <?php return ob_get_clean();
};

/** render importer-validated media (image + whitelisted YouTube). */
$renderMedia = function () use ($issue): string {
    if (empty($issue['media'])) {
        return '';
    }
    ob_start();
    foreach ($issue['media'] as $mItem):
        if ($mItem['media_type'] === 'image'): ?>
    <figure class="media">
      <img src="<?= ds_e($mItem['url_or_path']) ?>" alt="<?= ds_e($mItem['alt_text']) ?>" loading="lazy">
      <?php if (($mItem['caption'] ?? '') !== ''): ?>
      <figcaption><?= ds_e($mItem['caption']) ?><?php if (($mItem['credit'] ?? '') !== ''): ?> — <span class="credit"><?= ds_e($mItem['credit']) ?></span><?php endif; ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php elseif ($mItem['media_type'] === 'youtube'):
            $loc = (string)$mItem['url_or_path'];
            $youtubeId = preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})#', $loc, $ym)
                ? $ym[1]
                : (preg_match('/^[A-Za-z0-9_-]{11}$/', $loc) ? $loc : null);
            if ($youtubeId !== null): ?>
    <figure class="media video-embed-wrap">
      <div class="video-embed">
        <iframe src="https://www.youtube-nocookie.com/embed/<?= ds_e($youtubeId) ?>"
                title="<?= ds_e(($mItem['caption'] ?? '') !== '' ? $mItem['caption'] : 'YouTube video player') ?>"
                loading="lazy" allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
      </div>
      <?php if (($mItem['caption'] ?? '') !== ''): ?>
      <figcaption><?= ds_e($mItem['caption']) ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php endif;
        endif;
    endforeach;
    return ob_get_clean();
};

$mediaHtml = $renderMedia();
require __DIR__ . '/partials/head.php';
?>
<article class="issue" data-issue="<?= ds_e($issue['slug']) ?>" data-view="<?= ds_e($view) ?>">
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

  <nav class="view-toggle" aria-label="View modes">
    <?php foreach ($views as $key => $label): ?>
    <a class="view-btn<?= $key === $view ? ' is-active' : '' ?>"
       href="?view=<?= ds_e($key) ?>#views"
       data-view="<?= ds_e($key) ?>"
       aria-pressed="<?= $key === $view ? 'true' : 'false' ?>"
       <?= $key === $view ? 'aria-current="true"' : '' ?>><?= ds_e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <div id="views">

  <?php // ---------------- merged (default): both sides + everything ?>
  <section class="view v-merged" data-view="merged" <?= $view !== 'merged' ? 'hidden' : '' ?> aria-label="Both perspectives">
    <div class="compare" data-scrollsync>
      <?= $renderSide($sideA, 'a') ?>
      <?= $renderSide($sideB, 'b') ?>
    </div>
    <?= $mediaHtml ?>
    <?php if (!empty($issue['orientation'])): ?>
    <section class="orientation">
      <h2>Orientation</h2>
      <?= Markdown::render((string)$issue['orientation']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['shared_ground'])): ?>
    <section class="shared-ground">
      <h2>Shared ground</h2>
      <?= Markdown::render((string)$issue['shared_ground']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['evidence'])): ?>
    <section class="evidence">
      <h2>What the evidence says</h2>
      <?= Markdown::render((string)$issue['evidence']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['unknowns'])): ?>
    <section class="unknowns">
      <h2>What remains uncertain</h2>
      <?= Markdown::render((string)$issue['unknowns']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['synthesis'])): ?>
    <section class="synthesis">
      <h2>Synthesis</h2>
      <?= Markdown::render((string)$issue['synthesis']) ?>
    </section>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/sources.php'; ?>
  </section>

  <?php // ---------------- side A / side B: one column + shared tail ?>
  <?php foreach (['a' => 'side-a', 'b' => 'side-b'] as $col => $key): ?>
  <section class="view v-<?= ds_e($key) ?>" data-view="<?= ds_e($key) ?>" <?= $view !== $key ? 'hidden' : '' ?> aria-label="<?= ds_e($key === 'side-a' ? $sideA['label'] : $sideB['label']) ?>">
    <?= $renderSide($key === 'side-a' ? $sideA : $sideB, $col) ?>
    <?= $mediaHtml ?>
    <?php if (!empty($issue['shared_ground'])): ?>
    <section class="shared-ground">
      <h2>Shared ground</h2>
      <?= Markdown::render((string)$issue['shared_ground']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['synthesis'])): ?>
    <section class="synthesis">
      <h2>Synthesis</h2>
      <?= Markdown::render((string)$issue['synthesis']) ?>
    </section>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/sources.php'; ?>
  </section>
  <?php endforeach; ?>

  <?php // ---------------- evidence: orientation + evidence + sources ?>
  <section class="view v-evidence" data-view="evidence" <?= $view !== 'evidence' ? 'hidden' : '' ?> aria-label="Evidence">
    <?php if (!empty($issue['orientation'])): ?>
    <section class="orientation">
      <h2>Orientation</h2>
      <?= Markdown::render((string)$issue['orientation']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['evidence'])): ?>
    <section class="evidence">
      <h2>What the evidence says</h2>
      <?= Markdown::render((string)$issue['evidence']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['unknowns'])): ?>
    <section class="unknowns">
      <h2>What remains uncertain</h2>
      <?= Markdown::render((string)$issue['unknowns']) ?>
    </section>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/sources.php'; ?>
  </section>

  <?php // ---------------- timeline: the argument as a reading sequence ?>
  <section class="view v-timeline" data-view="timeline" <?= $view !== 'timeline' ? 'hidden' : '' ?> aria-label="Timeline of the argument">
    <ol class="tl">
      <?php
      $steps = [
          ['Orientation', (string)($issue['orientation'] ?? '')],
          [$sideA['label'], (string)$sideA['body_markdown']],
          [$sideB['label'], (string)$sideB['body_markdown']],
          ['Shared ground', (string)($issue['shared_ground'] ?? '')],
          ['What the evidence says', (string)($issue['evidence'] ?? '')],
          ['What remains uncertain', (string)($issue['unknowns'] ?? '')],
          ['Synthesis', (string)($issue['synthesis'] ?? '')],
      ];
      foreach ($steps as $i => [$stepLabel, $stepMd]):
          if (trim($stepMd) === '') { continue; } ?>
      <li class="tl-step">
        <span class="tl-dot" aria-hidden="true"></span>
        <div class="tl-body">
          <h2 class="tl-title"><span class="tl-n"><?= $i + 1 ?></span>. <?= ds_e($stepLabel) ?></h2>
          <?= Markdown::render($stepMd) ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php require __DIR__ . '/partials/sources.php'; ?>
  </section>

  <?php // ---------------- compact: dense overview ?>
  <section class="view v-compact" data-view="compact" <?= $view !== 'compact' ? 'hidden' : '' ?> aria-label="Compact overview">
    <div class="compact-grid">
      <div class="c-side c-a">
        <h2><?= ds_e($sideA['label']) ?></h2>
        <p><?= ds_e($sideA['short_summary']) ?></p>
      </div>
      <div class="c-side c-b">
        <h2><?= ds_e($sideB['label']) ?></h2>
        <p><?= ds_e($sideB['short_summary']) ?></p>
      </div>
    </div>
    <?php if (!empty($issue['shared_ground'])): ?>
    <section class="shared-ground">
      <h2>Shared ground</h2>
      <?= Markdown::render((string)$issue['shared_ground']) ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($issue['synthesis'])): ?>
    <section class="synthesis">
      <h2>Synthesis</h2>
      <?= Markdown::render((string)$issue['synthesis']) ?>
    </section>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/sources.php'; ?>
  </section>

  </div><!-- /#views -->
</article>
<script src="<?= ds_e(($base ?? '') . '/assets/js/views.js') ?>" defer></script>
<?php require __DIR__ . '/partials/foot.php'; ?>
