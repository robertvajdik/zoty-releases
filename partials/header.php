<?php
/** @var string $pageTitle */
$base = $base_path ?? '';   // '' for root pages, '../' for admin pages
$isAdminArea = !empty($admin_area);
$isPortal = !empty($portal_area);
$portalUser = $isPortal ? portal_user() : null;
$self = basename($_SERVER['SCRIPT_NAME'] ?? '');
$L = $isAdminArea ? 'cs' : lang();
// Admin has two workspaces: release notes (changelog) and support (customer requests).
$adminSection = $admin_section ?? (in_array($self, ['tickets.php', 'ticket.php', 'customers.php', 'admins.php'], true) ? 'support'
    : (in_array($self, ['manual.php', 'docs.php'], true) ? 'help' : 'releases'));
$adminNav = [];
if ($isAdminArea && is_admin()) {
    $tStats = ticket_stats();
    $waitingOnUs = $tStats['open'];
    $adminNav = $adminSection === 'help' ? [
        ['manual', 'Manuály', ['manual.php', 'docs.php'], 0],
    ] : ($adminSection === 'support' ? [
        ['tickets',   'Požadavky', ['tickets.php', 'ticket.php'], $tStats['open'] + $tStats['in_progress'] + $tStats['waiting']],
        ['customers', 'Zákazníci a přístupy', ['customers.php'], 0],
        ['admins',    'Administrátoři', ['admins.php'], 0],
    ] : [
        ['./',          'Záznamy',    ['index.php', 'edit.php'], 0],
        ['versions',    'Verze',      ['versions.php'], 0],
        ['modules',     'Moduly',     ['modules.php'], 0],
        ['subscribers', 'Odběratelé', ['subscribers.php'], 0],
    ]);
}
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
  <link rel="stylesheet" href="<?= $base ?>assets/style.css?v=15">
  <?php if ($isAdminArea || $isPortal): ?><link rel="stylesheet" href="<?= $base ?>assets/tickets.css?v=20"><?php endif; ?>
  <?php if ($isAdminArea): ?><link rel="stylesheet" href="<?= $base ?>assets/admin.css?v=2"><?php endif; ?>
  <link rel="alternate" type="application/atom+xml" title="<?= h(tr('site.name')) ?>" href="<?= $base ?>feed.xml">
  <?= $headExtra ?? '' ?>
</head>
<body class="<?= $isAdminArea ? 'is-admin is-admin--' . $adminSection : ($isPortal ? 'is-portal' : '') ?>">
<header class="header">
  <nav class="navbar container">
    <a class="navbar__logo" href="<?= $isAdminArea && is_admin() ? $base . ($adminSection === 'support' ? 'admin/tickets' : 'admin/') : ($isPortal ? $base . 'portal/' : $base . './') ?>">
      <img src="<?= $base ?>assets/img/zoty-logo.png" width="130" alt="<?= h(tr('nav.logo_alt')) ?>">
      <span class="navbar__product"><?= $isAdminArea ? 'Administrace' : ($isPortal ? h(tr('nav.support')) : h(tr('site.product'))) ?></span>
    </a>
    <button class="navbar__toggle" type="button" aria-controls="nav-links" aria-expanded="false" aria-label="<?= h(tr('nav.menu')) ?>">
      <span></span><span></span><span></span>
    </button>
    <ul class="navbar__links" id="nav-links">
      <?php if ($isAdminArea && is_admin()): ?>
        <?php if ($adminSection === 'support'): ?>
          <li><a href="<?= $base ?>portal/" target="_blank" rel="noopener">Zákaznický portál ↗</a></li>
        <?php else: ?>
          <li><a href="<?= $base ?>./" target="_blank" rel="noopener">Veřejný web ↗</a></li>
        <?php endif; ?>
        <li><a href="<?= $base ?>admin/manual" <?= in_array($self, ['manual.php', 'docs.php'], true) ? 'aria-current="page"' : '' ?>>Manuál</a></li>
        <li class="nav-user"><a href="<?= $base ?>admin/admins" title="Přihlášený administrátor – správa administrátorů" class="nav-user__name"><?= h((string)($_SESSION['zoty_admin'] ?? '')) ?></a> <a href="<?= $base ?>admin/logout">Odhlásit</a></li>
      <?php elseif ($isPortal && $portalUser): ?>
        <li><a href="<?= $base ?>portal/" <?= in_array($self, ['index.php', 'ticket.php'], true) ? 'aria-current="page"' : '' ?>><?= h(tr('portal.my_requests')) ?></a></li>
        <li><a href="<?= $base ?>portal/help" <?= $self === 'help.php' ? 'aria-current="page"' : '' ?>><?= h(tr('nav.help')) ?></a></li>
        <li><a href="<?= $base ?>portal/downloads" <?= $self === 'downloads.php' ? 'aria-current="page"' : '' ?>><?= h(tr('downloads.title')) ?></a></li>
        <li class="lang-switch" role="group" aria-label="<?= h(tr('lang.switch')) ?>">
          <?php foreach (LANGS as $code): ?>
            <a href="<?= h(lang_url($code)) ?>" hreflang="<?= $code ?>" lang="<?= $code ?>" title="<?= h(tr('lang.name', [], $code)) ?>"
               <?= $code === $L ? 'aria-current="true"' : '' ?>><?= strtoupper($code) ?></a>
          <?php endforeach; ?>
        </li>
        <li class="nav-user"><span title="<?= h($portalUser['email']) ?>"><?= h($portalUser['customer_name']) ?></span> <a href="<?= $base ?>portal/logout"><?= h(tr('portal.logout')) ?></a></li>
        <li><a class="button button--green" href="<?= $base ?>portal/new"><?= h(tr('portal.new_request')) ?></a></li>
      <?php else: ?>
        <li><a href="<?= $base ?>portal/"><?= h(tr('nav.support')) ?></a></li>
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
  <?php if ($adminNav): ?>
  <div class="subnav">
    <div class="container subnav__inner">
      <nav class="ws-switch" aria-label="Sekce administrace">
        <a href="<?= $base ?>admin/" <?= $adminSection === 'releases' ? 'aria-current="true"' : '' ?>>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>Release notes</a>
        <a href="<?= $base ?>admin/tickets" <?= $adminSection === 'support' ? 'aria-current="true"' : '' ?>>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 13a8 8 0 0 1 16 0v4a2 2 0 0 1-2 2h-1v-6h3M4 13v4a2 2 0 0 0 2 2h1v-6H4"/></svg>Podpora<?php if ($waitingOnUs): ?><span class="nav-count" title="Nové nebo s odpovědí zákazníka"><?= $waitingOnUs ?></span><?php endif; ?></a>
      </nav>
      <ul class="subnav__links">
        <?php foreach ($adminNav as [$href, $label, $pages, $count]): ?>
          <li><a href="<?= $base ?>admin/<?= $href === './' ? '' : $href ?>" <?= in_array($self, $pages, true) ? 'aria-current="page"' : '' ?>><?= h($label) ?><?php if ($count): ?> <b><?= $count ?></b><?php endif; ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($adminSection === 'help'): ?>
      <?php elseif ($adminSection === 'support'): ?>
        <a class="button button--primary button--sm subnav__cta" href="<?= $base ?>admin/customers#new">+ Nový zákazník</a>
      <?php else: ?>
        <a class="button button--green button--sm subnav__cta" href="<?= $base ?>admin/edit">+ Nový záznam</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</header>
<script>
  document.querySelector('.navbar__toggle').addEventListener('click', function () {
    var open = this.getAttribute('aria-expanded') !== 'true';
    this.setAttribute('aria-expanded', open);
    document.getElementById('nav-links').classList.toggle('is-open', open);
  });
</script>
