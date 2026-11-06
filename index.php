<?php
require __DIR__ . '/lib.php';


$filters  = current_filters($_GET);
$entries  = fetch_entries($filters);
$groups   = group_by_version($entries);
$versions = distinct_values('version');
$modules  = distinct_values('module');
$qs       = http_build_query($filters);
$pageTitle = tr('site.product');
$home = site_url();
$canonicalCs = isset($filters['version']) && count($filters) === 1
    ? site_url('?version=' . rawurlencode($filters['version'])) : $home;
$canonical = with_lang($canonicalCs);
$keywords = site_keywords($modules);
$headExtra = '<link rel="canonical" href="' . h($canonical) . '">' . "\n"
    . '  <link rel="alternate" hreflang="cs" href="' . h($canonicalCs) . '">' . "\n"
    . '  <link rel="alternate" hreflang="en" href="' . h(with_lang($canonicalCs, 'en')) . '">' . "\n"
    . '  <link rel="alternate" hreflang="x-default" href="' . h($canonicalCs) . '">' . "\n"
    . '  <meta name="description" content="' . h(tr('site.description')) . '">' . "\n"
    . ($keywords ? '  <meta name="keywords" content="' . h(implode(', ', $keywords)) . '">' . "\n" : '')
    . ($filters && $canonicalCs === $home ? '  <meta name="robots" content="noindex, follow">' . "\n" : '')
    . changelog_jsonld($groups, $canonical, $keywords);
require __DIR__ . '/partials/header.php';
?>
<section class="banner">
  <img src="assets/img/dot-green.svg" class="dot dot--1" alt="">
  <img src="assets/img/dot-yellow.svg" class="dot dot--2" alt="">
  <img src="assets/img/dot-purple.svg" class="dot dot--3" alt="">
  <div class="banner__content container">
    <div>
      <p class="banner__eyebrow"><?= h(tr('banner.eyebrow')) ?></p>
      <h1><?= h(tr('banner.title')) ?></h1>
      <p class="banner__lead"><?= h(tr('site.description')) ?></p>
    </div>
    <div class="banner__illustration" aria-hidden="true">
      <img src="assets/img/hero.svg" alt="">
    </div>
  </div>
</section>

<main class="container changelog" vocab="https://schema.org/" typeof="CollectionPage" resource="<?= h($canonical) ?>#webpage">
  <meta property="name" content="<?= h(tr('site.name')) ?>">
  <link property="about" href="<?= h($home) ?>#app">
  <?php if ($keywords): ?><meta property="keywords" content="<?= h(implode(', ', $keywords)) ?>"><?php endif; ?>
  <form class="toolbar" method="get" action="">
    <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="<?= h(tr('filter.search_placeholder')) ?>" aria-label="<?= h(tr('filter.search')) ?>">
    <select name="version" aria-label="<?= h(tr('filter.version')) ?>">
      <option value=""><?= h(tr('filter.all_versions')) ?></option>
      <?php foreach ($versions as $v): ?>
        <option value="<?= h($v) ?>" <?= ($filters['version'] ?? '') === $v ? 'selected' : '' ?>><?= h(tr('release.version', ['version' => $v])) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="module" aria-label="<?= h(tr('filter.module')) ?>">
      <option value=""><?= h(tr('filter.all_modules')) ?></option>
      <?php foreach ($modules as $m): ?>
        <option value="<?= h($m) ?>" <?= ($filters['module'] ?? '') === $m ? 'selected' : '' ?>><?= h($m) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!empty($filters['type'])): ?><input type="hidden" name="type" value="<?= h($filters['type']) ?>"><?php endif; ?>
    <button class="button button--primary" type="submit"><?= h(tr('filter.submit')) ?></button>
    <a class="button button--ghost" href="export<?= $qs ? '?' . h($qs) : '' ?>" title="<?= h(tr('filter.download_title')) ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0l-5-5m5 5l5-5M4 21h16"/></svg>
      <?= h(tr('filter.download')) ?>
    </a>
  </form>

  <?php if ($m = flash()): ?><div class="flash flash--public" role="status"><?= h($m) ?></div><?php endif; ?>

  <nav class="chips" aria-label="<?= h(tr('chips.label')) ?>">
    <?php $chipBase = $filters; unset($chipBase["type"]); ?>
    <a class="chip <?= empty($filters['type']) ? 'is-active' : '' ?>" href="?<?= h(http_build_query($chipBase)) ?>"><?= h(tr('chips.all')) ?></a>
    <?php foreach (TYPES as $key => $t): ?>
      <a class="chip chip--<?= $key ?> <?= ($filters['type'] ?? '') === $key ? 'is-active' : '' ?>"
         href="?<?= h(http_build_query($chipBase + ['type' => $key])) ?>"><?= h(type_label($key)) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$groups): ?>
    <div class="empty">
      <p><?= h(tr('empty.text')) ?></p>
      <a href="./" class="button button--primary"><?= h(tr('empty.show_all')) ?></a>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $g): ?>
    <section class="release" id="<?= h(version_anchor($g['version'])) ?>" property="hasPart" typeof="TechArticle" resource="<?= h($home . '#' . version_anchor($g['version'])) ?>">
      <meta property="headline" content="<?= h('Zoty ' . $g['version'] . ($g['title'] !== '' ? ' – ' . $g['title'] : '')) ?>">
      <meta property="inLanguage" content="cs">
      <link property="about" href="<?= h($home) ?>#app">
      <link property="author" href="<?= h($home) ?>#author">
      <link property="publisher" href="https://zoty.cz/#organization">
      <aside class="release__meta">
        <time property="datePublished" datetime="<?= h($g['date']) ?>"><?= fmt_date($g['date']) ?></time>
        <a class="release__version" property="url" href="?version=<?= urlencode($g['version']) ?>"><?= h(tr('release.version', ['version' => $g['version']])) ?></a>
        <a class="release__export" href="export?version=<?= urlencode($g['version']) ?>"><?= h(tr('release.download')) ?></a>
      </aside>
      <div class="release__entries">
        <?php if ($g['title'] !== '' || trim($g['summary']) !== ''): ?>
          <header class="release__intro">
            <?php if ($g['title'] !== ''): ?><h2><?= h($g['title']) ?></h2><?php endif; ?>
            <?php if (trim($g['summary']) !== ''): ?><div class="entry__body" property="description"><?= render_body($g['summary']) ?></div><?php endif; ?>
          </header>
        <?php endif; ?>
        <?php foreach ($g['entries'] as $e): ?>
          <article class="entry" property="hasPart" typeof="CreativeWork">
            <div class="entry__tags">
              <span class="tag tag--<?= h($e['type']) ?>" property="genre"><?= h(type_label($e['type'])) ?></span>
              <?php foreach (split_modules($e['module']) as $mod): ?><span class="tag tag--module" property="keywords"><?= h($mod) ?></span><?php endforeach; ?>
            </div>
            <h2 class="entry__title" property="name"><?= h($e['title']) ?></h2>
            <?php if (trim($e['body']) !== ''): ?>
              <div class="entry__body" property="text"><?= render_body($e['body']) ?></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="subscribe" id="subscribe" aria-labelledby="subscribe-title">
    <div class="subscribe__text">
      <h2 id="subscribe-title"><?= h(tr('subscribe.title')) ?></h2>
      <p><?= h(tr('subscribe.lead')) ?></p>
    </div>
    <form class="subscribe__form" method="post" action="subscribe">
      <?= csrf_field() ?>
      <label class="visually-hidden" for="sub-email"><?= h(tr('subscribe.email_label')) ?></label>
      <input id="sub-email" type="email" name="email" required maxlength="190" placeholder="<?= h(tr('subscribe.placeholder')) ?>" autocomplete="email" inputmode="email">
      <input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
      <button class="button button--green" type="submit"><?= h(tr('subscribe.button')) ?></button>
      <p class="subscribe__note"><?= h(tr('subscribe.note')) ?> · <a href="feed.xml"><?= h(tr('subscribe.feed')) ?></a></p>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
