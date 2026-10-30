<?php /** sources list — expects $issue; used inside every view mode */ ?>
<section class="sources" id="sources">
  <h2>Sources</h2>
  <ol class="source-list">
    <?php foreach ($issue['sources'] as $s): ?>
      <li id="source-<?= ds_e($s['ref_key']) ?>" class="source src-<?= ds_e($s['source_type']) ?>">
        <a href="<?= ds_e($s['url']) ?>" rel="noopener noreferrer" target="_blank">
          <?= ds_e($s['title']) ?></a>
        — <?= ds_e($s['publisher']) ?>
        (<?= ds_e($s['source_type']) ?><?= $s['published_at'] ? ', ' . ds_e((string)$s['published_at']) : '' ?>)
      </li>
    <?php endforeach; ?>
  </ol>
</section>
