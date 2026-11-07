<?php
// Customer help – public (also readable before signing in).
require __DIR__ . '/../lib.php';

$doc = doc_for('portal', lang());
$tocLabel = tr('help.toc');
$updatedLabel = tr('help.updated');
$docTools = '';

$base_path = '../';
$portal_area = true;
$pageTitle = $doc ? doc_title($doc['body'], tr('help.title')) : tr('help.title');
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <?php if ($doc): ?>
    <?php require __DIR__ . '/../partials/doc-view.php'; ?>
  <?php else: ?>
    <div class="card message-card"><h1><?= h(tr('help.title')) ?></h1><p><?= h(tr('help.missing')) ?></p></div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
