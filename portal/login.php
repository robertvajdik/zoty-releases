<?php
require __DIR__ . '/../lib.php';

/** Only same-site paths are allowed as the return address. */
function safe_next(string $n): string
{
    return preg_match('#^/(?!/)[^\s\\\\]*$#', $n) && str_contains($n, '/portal/') ? $n : './';
}

$next = safe_next((string)($_GET['next'] ?? $_POST['next'] ?? ''));
if (portal_user()) {
    header('Location: ' . $next);
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $human = recaptcha_check('portal_login');
    $login = $human ? customer_login((string)($_POST['user'] ?? ''), (string)($_POST['password'] ?? '')) : ['status' => 'invalid'];
    $user = $login['user'] ?? null;
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['zoty_customer'] = (int)$user['id'];
        header('Location: ' . $next);
        exit;
    }
    usleep(400000);
    if (!$human) {
        $error = tr('recaptcha.failed');
    } elseif ($login['status'] === 'inactive') {
        $error = tr('portal.login_inactive');
    } elseif (auth_verify(trim((string)($_POST['user'] ?? '')), (string)($_POST['password'] ?? ''))) {
        // the administrator's account typed into the customer portal
        $error = tr('portal.login_is_admin');
        $errorLink = ['href' => '../admin/login', 'text' => tr('portal.login_admin_link')];
    } else {
        $error = tr('portal.login_error');
    }
}

$L = lang();
$eye = '<svg class="eye-off" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 20 20 4"/></svg>'
     . '<svg class="eye-on" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
$keyIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="14.5" cy="9.5" r="5"/><path d="M11 13 4 20m2.5-2.5 2 2M4 20l1.5 1.5"/></svg>';
?><!doctype html>
<html lang="<?= $L ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#6c7ae0">
  <meta name="robots" content="noindex">
  <title><?= h(tr('portal.login_title')) ?> – Zoty</title>
  <link rel="icon" type="image/png" href="../assets/img/favicon-32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/login.css?v=4">
  <link rel="stylesheet" href="../assets/tickets.css?v=1">
</head>
<body class="auth">
  <main class="auth__wrap">
    <form class="auth__card" method="post" novalidate>
      <div class="auth__logo" role="img" aria-label="Zoty">
        <img src="../assets/img/zoty-logo.png" alt="">
      </div>
      <p class="auth__note"><?= h(tr('portal.login_intro')) ?></p>
      <div class="auth__msg" role="alert" aria-live="polite"><?= $error ? h($error) : '' ?><?php if (!empty($errorLink)): ?> <a href="<?= h($errorLink['href']) ?>"><?= h($errorLink['text']) ?></a><?php endif; ?></div>

      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= h($next) ?>">

      <label class="auth__field">
        <span class="auth__icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1-3.8 4-5.5 7.5-5.5s6.5 1.7 7.5 5.5"/></svg>
        </span>
        <span class="visually-hidden"><?= h(tr('portal.email')) ?></span>
        <input name="user" type="email" placeholder="<?= h(tr('portal.email')) ?>" required autocomplete="username"
               autocapitalize="none" spellcheck="false" value="<?= h($_POST['user'] ?? '') ?>" <?= $error === null ? 'autofocus' : '' ?>>
      </label>

      <label class="auth__field">
        <span class="auth__icon"><?= $keyIcon ?></span>
        <span class="visually-hidden"><?= h(tr('portal.password')) ?></span>
        <input name="password" type="password" placeholder="<?= h(tr('portal.password')) ?>" required autocomplete="current-password" <?= $error !== null ? 'autofocus' : '' ?>>
        <button type="button" class="auth__eye" aria-label="<?= h(tr('portal.show_password')) ?>" aria-pressed="false"><?= $eye ?></button>
      </label>

      <button class="auth__submit" type="submit"><?= h(tr('portal.login_button')) ?></button>
      <?= recaptcha_field('portal_login') ?>
      <p class="auth__links">
        <?= h(tr('portal.no_account')) ?> <a href="https://zoty.cz/kontakt/"><?= h(tr('nav.contact')) ?></a>
        · <?php foreach (LANGS as $code): if ($code === $L) continue; ?><a href="<?= h(lang_url($code)) ?>" hreflang="<?= $code ?>"><?= h(tr('lang.name', [], $code)) ?></a><?php endforeach; ?>
      </p>
    </form>
  </main>
  <script>
    document.querySelectorAll('.auth__eye').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = btn.parentNode.querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show);
        input.focus();
      });
    });
  </script>
</body>
</html>
