<?php
require __DIR__ . '/../lib.php';
require_admin();

$slug = isset(DOCS[$_GET['doc'] ?? '']) ? (string)$_GET['doc'] : 'admin';
$lang = in_array($_GET['lang'] ?? '', LANGS, true) ? (string)$_GET['lang'] : doc_langs($slug)[0];
$doc = doc_get($slug, $lang);

$tocLabel = 'Obsah';
$updatedLabel = 'Naposledy upraveno';
$docTools = '';   // actions are in the bar above (partials/doc-bar.php)

$base_path = '../';
$admin_area = true;
$pageTitle = 'Manuál';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <?php $barMode = 'view'; require __DIR__ . '/../partials/doc-bar.php'; ?>
  <?php if ($doc): ?>
    <?php require __DIR__ . '/../partials/doc-view.php'; ?>
  <?php else: ?>
    <div class="card message-card">
      <h1><?= h(doc_name($slug, $lang)) ?> (<?= strtoupper($lang) ?>)</h1>
      <p>Tato jazyková verze zatím neexistuje. Vytvořte ji tlačítkem <b>Vytvořit</b> – editor se předvyplní textem jiné jazykové verze k přeložení.</p>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
