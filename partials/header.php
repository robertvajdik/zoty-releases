<?php
/** @var string $pageTitle */
$base = $base_path ?? '';   // '' for root pages, '../' for admin pages
$isAdminArea = !empty($admin_area);
$self = basename($_SERVER['SCRIPT_NAME'] ?? '');
$L = $isAdminArea ? 'cs' : lang();
?><!doctype html>
<html lang="<?= $L ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#6144a3">
  <meta name="author" content="Robert Vajdik">
  <title><?= h(($pageTitle ?? '') . ' – Zoty') ?></title>
  <meta property="og:locale" content="<?= $L === 'en' ? 'en_US' : 'cs_CZ' ?>">
  <link rel="icon" type="image/png" href="<?= $base ?>assets/img/favicon-32.png">
  <link rel="apple-touch-icon" href="<?= $base ?>assets/img/favicon-32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>assets/style.css?v=9">
  <link rel="alternate" type="application/atom+xml" title="<?= h(tr('site.name')) ?>" href="<?= $base ?>feed.xml">
  <?= $headExtra ?? '' ?>
</head>
<body class="<?= $isAdminArea ? 'is-admin' : '' ?>">
<header class="header">
  <nav class="navbar container">
    <a class="navbar__logo" href="<?= $isAdminArea && is_admin() ? $base . 'admin/' : $base . './' ?>">
      <img src="<?= $base ?>assets/img/zoty-logo.png" width="130" alt="<?= h(tr('nav.logo_alt')) ?>">
      <span class="navbar__product"><?= $isAdminArea ? 'Administrace' : h(tr('site.product')) ?></span>
    </a>
    <button class="navbar__toggle" type="button" aria-controls="nav-links" aria-expanded="false" aria-label="<?= h(tr('nav.menu')) ?>">
      <span></span><span></span><span></span>
    </button>
    <ul class="navbar__links" id="nav-links">
      <?php if ($isAdminArea && is_admin()): ?>
        <li><a href="<?= $base ?>admin/" <?= $self === 'index.php' ? 'aria-current="page"' : '' ?>>Záznamy</a></li>
        <li><a href="<?= $base ?>admin/versions" <?= $self === 'versions.php' ? 'aria-current="page"' : '' ?>>Verze</a></li>
        <li><a href="<?= $base ?>admin/modules" <?= $self === 'modules.php' ? 'aria-current="page"' : '' ?>>Moduly</a></li>
        <li><a href="<?= $base ?>admin/subscribers" <?= $self === 'subscribers.php' ? 'aria-current="page"' : '' ?>>Odběratelé</a></li>
        <li><a href="<?= $base ?>./" target="_blank">Veřejný web</a></li>
        <li><a href="<?= $base ?>admin/logout">Odhlásit</a></li>
        <li><a class="button button--green" href="<?= $base ?>admin/edit">+ Nový záznam</a></li>
      <?php else: ?>
        <li><a href="https://zoty.cz/nase-reseni/"><?= h(tr('nav.solutions')) ?></a></li>
        <li><a href="https://zoty.cz/kontakt/"><?= h(tr('nav.contact')) ?></a></li>
        <?php if (!$isAdminArea): ?>
        <li class="lang-switch" role="group" aria-label="<?= h(tr('lang.switch')) ?>">
          <?php foreach (LANGS as $code): ?>
            <a href="<?= h(lang_url($code)) ?>" hreflang="<?= $code ?>" lang="<?= $code ?>" title="<?= h(tr('lang.name', [], $code)) ?>"
               <?= $code === $L ? 'aria-current="true"' : '' ?>><?= strtoupper($code) ?></a>
          <?php endforeach; ?>
        </li>
        <?php endif; ?>
        <li><a class="button button--green" href="https://zoty.cloud" target="_blank" rel="noopener"><?= h(tr('nav.login')) ?></a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>
<script>
  document.querySelector('.navbar__toggle').addEventListener('click', function () {
    var open = this.getAttribute('aria-expanded') !== 'true';
    this.setAttribute('aria-expanded', open);
    document.getElementById('nav-links').classList.toggle('is-open', open);
  });
</script>
