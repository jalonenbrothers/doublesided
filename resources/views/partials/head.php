<?php /** layout top — $title, optional $canonical */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ds_e($title ?? 'Two Sides') ?></title>
<?php if (!empty($canonical)): ?>
<link rel="canonical" href="<?= ds_e($canonical) ?>">
<?php endif; ?>
<meta name="description" content="<?= ds_e($description ?? 'Two sides of contested questions — evidence, shared ground and visible uncertainty.') ?>">
<link rel="stylesheet" href="<?= ds_e(($base ?? '') . '/assets/css/site.css') ?>">
</head>
<body>
<header class="site-head">
  <a class="brand" href="<?= ds_e(($base ?? '') . '/') ?>">Two Sides</a>
  <span class="tagline">evidence over heat</span>
</header>
<main>
