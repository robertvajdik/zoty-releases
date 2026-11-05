<?php
// Unsubscribe: GET ?t=token shows a button (link scanners must not unsubscribe people),
// POST removes the address. Also handles RFC 8058 one-click POSTs from mail clients.
require __DIR__ . '/lib.php';

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$sub = subscriber_by_token($token);
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sub) subscriber_delete((int)$sub['id']);
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {   // mail client one-click
        http_response_code(200);
        exit('OK');
    }
    $done = true;
}

$pageTitle = tr('unsub.page_title');
$headExtra = '<meta name="robots" content="noindex">' . "\n";
require __DIR__ . '/partials/header.php';
?>
<main class="container message-page">
  <div class="card message-card">
    <?php if ($done): ?>
      <h1><?= h(tr('unsub.done_title')) ?></h1>
      <p><?= h(tr('unsub.done_text')) ?></p>
      <a class="button button--primary" href="./"><?= h(tr('common.back')) ?></a>
    <?php elseif ($sub): ?>
      <h1><?= h(tr('unsub.ask_title')) ?></h1>
      <p><?= trh('unsub.ask_text', [], ['email' => '<strong>' . h($sub['email']) . '</strong>']) ?></p>
      <form method="post" action="unsubscribe">
        <input type="hidden" name="t" value="<?= h($token) ?>">
        <div class="form-actions">
          <button class="button button--primary" type="submit"><?= h(tr('unsub.confirm')) ?></button>
          <a class="button button--ghost" href="./"><?= h(tr('unsub.keep')) ?></a>
        </div>
      </form>
    <?php else: ?>
      <h1><?= h(tr('unsub.bad_title')) ?></h1>
      <p><?= h(tr('unsub.bad_text')) ?></p>
      <a class="button button--primary" href="./"><?= h(tr('common.back')) ?></a>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
