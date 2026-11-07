<?php
// Public e-mail subscription: POST = sign up (sends confirmation e-mail), GET ?confirm=token = confirm.
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $back = './#subscribe';

    // honeypot + simple per-session rate limit
    $_SESSION['sub_tries'] = array_filter($_SESSION['sub_tries'] ?? [], fn($t) => $t > time() - 3600);
    if (trim((string)($_POST['website'] ?? '')) !== '' || count($_SESSION['sub_tries']) >= 5) {
        flash(tr('subscribe.flash.generic'));
        header('Location: ' . $back);
        exit;
    }
    $_SESSION['sub_tries'][] = time();

    if (!recaptcha_check('subscribe')) {
        flash(tr('recaptcha.failed'));
        header('Location: ' . $back);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        flash(tr('subscribe.flash.invalid'));
    } else {
        $r = subscriber_add($email);
        flash($r === 'error' ? tr('subscribe.flash.error') : tr('subscribe.flash.sent', ['email' => $email]));
    }
    header('Location: ' . $back);
    exit;
}

$email = isset($_GET['confirm']) ? subscriber_confirm((string)$_GET['confirm']) : null;
$pageTitle = tr('confirm.page_title');
$headExtra = '<meta name="robots" content="noindex">' . "\n";
require __DIR__ . '/partials/header.php';
?>
<main class="container message-page">
  <div class="card message-card">
    <?php if ($email): ?>
      <h1><?= h(tr('confirm.ok_title')) ?></h1>
      <p><?= trh('confirm.ok_text', [], ['email' => '<strong>' . h($email) . '</strong>']) ?></p>
    <?php else: ?>
      <h1><?= h(tr('confirm.bad_title')) ?></h1>
      <p><?= h(tr('confirm.bad_text')) ?></p>
    <?php endif; ?>
    <a class="button button--primary" href="./"><?= h(tr('common.back')) ?></a>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
