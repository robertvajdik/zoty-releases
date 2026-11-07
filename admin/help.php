<?php
require __DIR__ . '/../lib.php';
require_admin();

$slug = isset(DOCS[$_GET['doc'] ?? '']) ? (string)$_GET['doc'] : 'admin';
$lang = in_array($_GET['lang'] ?? '', LANGS, true) ? (string)$_GET['lang'] : DOCS[$slug]['langs'][0];
$doc = doc_get($slug, $lang);

$tocLabel = 'Obsah';
$updatedLabel = 'Naposledy upraveno';
$docTools = '<a class="button button--primary button--sm" href="docs?doc=' . h($slug) . '&amp;lang=' . h($lang) . '">✎ Upravit</a>'
          . ($slug === 'portal' ? ' <a class="button button--ghost button--sm" href="../portal/help?lang=' . h($lang) . '" target="_blank" rel="noopener">Zobrazit v portálu ↗</a>' : '');

$base_path = '../';
$admin_area = true;
$pageTitle = 'Nápověda';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <nav class="tabs" aria-label="Příručky">
    <?php foreach (DOCS as $s => $d): foreach ($d['langs'] as $l): ?>
      <a href="help?doc=<?= $s ?>&amp;lang=<?= $l ?>" <?= $s === $slug && $l === $lang ? 'aria-current="page"' : '' ?>><?= h($d['label']) ?> <b><?= strtoupper($l) ?></b></a>
    <?php endforeach; endforeach; ?>
  </nav>
  <?php if ($doc): ?>
    <?php require __DIR__ . '/../partials/doc-view.php'; ?>
  <?php else: ?>
    <div class="card message-card">
      <h1><?= h(DOCS[$slug]['label']) ?> (<?= strtoupper($lang) ?>)</h1>
      <p>Tato jazyková verze zatím neexistuje.</p>
      <div class="form-actions"><a class="button button--primary" href="docs?doc=<?= h($slug) ?>&amp;lang=<?= h($lang) ?>">Vytvořit</a></div>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
