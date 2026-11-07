<?php
require __DIR__ . '/../lib.php';

if (is_admin()) {
    header('Location: ./');
    exit;
}

$error = null;
$setup = !auth_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $human = recaptcha_check('admin_login');

    if (!$human) {
        usleep(400000);
        $error = tr('recaptcha.failed', [], 'cs');
    } elseif ($setup) {
        if (!filter_var($user, FILTER_VALIDATE_EMAIL) || mb_strlen($user) > 80) {
            $error = 'Zadejte platný email (max. 80 znaků).';
        } elseif (mb_strlen($pass) < 8) {
            $error = 'Heslo musí mít alespoň 8 znaků.';
        } elseif (auth_configured()) {
            $error = 'Administrátor už existuje, přihlaste se.';
            $setup = false;
        } else {
            auth_create($user, $pass);
            session_regenerate_id(true);
            $_SESSION['zoty_admin'] = $user;
            flash('Administrátorský účet byl vytvořen.');
            header('Location: ./');
            exit;
        }
    } else {
        if ($name = auth_verify($user, $pass)) {
            session_regenerate_id(true);
            $_SESSION['zoty_admin'] = $name;
            header('Location: ./');
            exit;
        }
        usleep(400000);
        $error = 'Nesprávný email nebo heslo.';
    }
}

$eye = '<svg class="eye-off" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 20 20 4"/></svg>'
     . '<svg class="eye-on" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
$keyIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="14.5" cy="9.5" r="5"/><path d="M11 13 4 20m2.5-2.5 2 2M4 20l1.5 1.5"/></svg>';
?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#6c7ae0">
  <meta name="author" content="Robert Vajdik">
  <meta name="robots" content="noindex">
  <title><?= $setup ? 'Nastavení administrace' : 'Přihlášení' ?> – Zoty</title>
  <link rel="icon" type="image/png" href="../assets/img/favicon-32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/login.css?v=3">
</head>
<body class="auth">
  <main class="auth__wrap">
    <form class="auth__card" method="post" novalidate>
      <div class="auth__logo" role="img" aria-label="Zoty">
        <img src="../assets/img/zoty-logo.png" alt="">
      </div>

      <?php if ($setup): ?>
        <p class="auth__note">První spuštění: vytvořte účet administrátora changelogu.</p>
      <?php endif; ?>

      <div class="auth__msg" role="alert" aria-live="polite"><?= $error ? h($error) : '' ?></div>

      <?= csrf_field() ?>

      <label class="auth__field">
        <span class="auth__icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1-3.8 4-5.5 7.5-5.5s6.5 1.7 7.5 5.5"/></svg>
        </span>
        <span class="visually-hidden">Email</span>
        <input name="user" type="<?= $setup ? 'email' : 'text' ?>" placeholder="E-mail" required autocomplete="username"
               autocapitalize="none" spellcheck="false" value="<?= h($_POST['user'] ?? '') ?>" <?= $error === null ? 'autofocus' : '' ?>>
      </label>

      <label class="auth__field">
        <span class="auth__icon"><?= $keyIcon ?></span>
        <span class="visually-hidden">Heslo</span>
        <input name="password" type="password" placeholder="Heslo" required
               autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" <?= $error !== null ? 'autofocus' : '' ?>>
        <button type="button" class="auth__eye" aria-label="Zobrazit heslo" aria-pressed="false"><?= $eye ?></button>
      </label>

      <button class="auth__submit" type="submit"><?= $setup ? 'Vytvořit účet' : 'Přihlásit se' ?></button>
      <?= recaptcha_field('admin_login', 'cs') ?>
    </form>
  </main>

  <script>
    document.querySelectorAll('.auth__eye').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = btn.parentNode.querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show);
        btn.setAttribute('aria-label', show ? 'Skrýt heslo' : 'Zobrazit heslo');
        input.focus();
      });
    });
  </script>
</body>
</html>
