<?php
declare(strict_types=1);
$title = 'Two Sides — contested questions, both perspectives';
require __DIR__ . '/partials/head.php';
?>
<?php if ($issues === []): ?>
  <section class="empty">
    <h1>Nothing published yet</h1>
    <p>The first issue is on its way. Content appears here once reviewed
       and published — see <a href="#methodology">how we work</a>.</p>
  </section>
<?php else: ?>
  <h1 class="home-title">Contested questions, both sides, one evidence base</h1>
  <ul class="issue-list">
    <?php foreach ($issues as $it): ?>
      <li>
        <a class="issue-link" href="/issue/<?= ds_e($it['slug']) ?>">
          <span class="k"><?= ds_e($it['title']) ?></span>
          <span class="sum"><?= ds_e($it['summary']) ?></span>
          <span class="meta"><?= ds_e($it['content_type']) ?> ·
            <?= ds_e((string)($it['published_at'] ?? '')) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php require __DIR__ . '/partials/foot.php'; ?>
