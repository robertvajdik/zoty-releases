<?php
// Customer help – only for signed-in customers (admins may preview it).
require __DIR__ . '/../lib.php';
if (!is_admin()) require_portal_user();

$docs = portal_docs();
$slug = in_array($_GET['doc'] ?? '', $docs, true) ? (string)$_GET['doc'] : $docs[0];
$doc = doc_for($slug, lang());
/** Tab name: the name set in the admin for this language, else the default one. */
$docLabel = function (string $s): string {
    $d = doc_get($s, lang());
    return $d && trim((string)$d['title']) !== '' ? trim((string)$d['title']) : tr('help.doc.' . $s);
};
$tocLabel = tr('help.toc');
$updatedLabel = tr('help.updated');
$docTools = '';

$base_path = '../';
$portal_area = true;
$pageTitle = $doc ? (trim((string)$doc['title']) !== '' ? trim((string)$doc['title']) : doc_title($doc['body'], tr('help.title'))) : tr('help.title');
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <?php if (count($docs) > 1): ?>
    <nav class="tabs" aria-label="<?= h(tr('nav.help')) ?>">
      <?php foreach ($docs as $s): ?>
        <a href="help?doc=<?= h($s) ?>" <?= $s === $slug ? 'aria-current="page"' : '' ?>><?= h($docLabel($s)) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if ($doc): ?>
    <?php require __DIR__ . '/../partials/doc-view.php'; ?>
  <?php else: ?>
    <div class="card message-card"><h1><?= h(tr('help.title')) ?></h1><p><?= h(tr('help.missing')) ?></p></div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
