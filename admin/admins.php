<?php
// Administrators: add / password / delete, and who gets the e-mails about new tickets.
require __DIR__ . '/../lib.php';
require_admin();

$me = (string)$_SESSION['zoty_admin'];
$errors = [];
$nf = ['email' => '', 'password' => '', 'send' => true];

/** E-mails the sign-in details to a new administrator. */
function send_admin_access(string $email, string $password): bool
{
    $url = site_url('admin/');
    $text = "Byl vám vytvořen účet do administrace přehledu změn a podpory Zoty.\r\n\r\n" . $url . "\r\nE-mail: " . $email
          . "\r\nHeslo: " . $password . "\r\n\r\nPo přihlášení si heslo změňte v sekci Podpora → Administrátoři.\r\n";
    $html = '<p>Byl vám vytvořen účet do administrace přehledu změn a podpory Zoty.</p>'
          . '<p style="background:#f9f9fc;border:1px solid #ecebec;border-radius:8px;padding:12px 16px">E-mail: <b>' . h($email) . '</b><br>Heslo: <b>' . h($password) . '</b></p>'
          . '<p style="margin:24px 0"><a href="' . h($url) . '" style="background:#6144a3;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">Přihlásit se</a></p>'
          . '<p style="color:#656565;font-size:13px">Po přihlášení si heslo změňte v sekci Podpora → Administrátoři.</p>';
    $r = mail_send([['to' => $email, 'subject' => 'Přístup do administrace Zoty', 'text' => $text, 'html' => mail_layout('Přístup do administrace', $html), 'tag' => 'Přístup admin']]);
    return $r['sent'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $target = admin_get((int)($_POST['id'] ?? 0));

    if ($action === 'add') {
        $nf['email'] = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $nf['password'] = (string)($_POST['password'] ?? '');
        $nf['send'] = !empty($_POST['send']);
        $errors = validate_admin($nf['email'], $nf['password']);
        if (!$errors) {
            auth_create($nf['email'], $nf['password']);
            $msg = 'Administrátor ' . $nf['email'] . ' byl přidán.';
            if ($nf['send']) $msg .= send_admin_access($nf['email'], $nf['password']) ? ' Přístup byl odeslán e-mailem.' : ' E-mail s přístupem se nepodařilo odeslat – předejte heslo jinak.';
            flash($msg);
            header('Location: admins');
            exit;
        }
    } elseif ($target && $action === 'notify') {
        admin_set_notify((int)$target['id'], !(int)$target['notify_tickets']);
        flash((int)$target['notify_tickets'] ? 'E-maily o požadavcích pro ' . $target['username'] . ' vypnuty.' : 'E-maily o požadavcích pro ' . $target['username'] . ' zapnuty.');
        header('Location: admins');
        exit;
    } elseif ($target && $action === 'password') {
        $pw = (string)($_POST['password'] ?? '');
        if (mb_strlen($pw) < 8) {
            $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        } else {
            admin_set_password((int)$target['id'], $pw);
            flash('Heslo pro ' . $target['username'] . ' bylo změněno.');
            header('Location: admins');
            exit;
        }
    } elseif ($target && $action === 'delete') {
        if ($target['username'] === $me) {
            $errors[] = 'Sami sebe smazat nemůžete.';
        } elseif (count(admin_list()) <= 1) {
            $errors[] = 'Poslední administrátor nejde smazat.';
        } else {
            admin_delete((int)$target['id']);
            flash('Administrátor ' . $target['username'] . ' byl smazán.');
            header('Location: admins');
            exit;
        }
    }
}

$admins = admin_list();
$configured = array_values(array_filter(ticket_staff_emails(), fn($e) => !in_array(mb_strtolower($e), array_map('mb_strtolower', admin_notify_emails()), true)));
$pwFor = (int)($_GET['password'] ?? 0);

$base_path = '../';
$admin_area = true;
$pageTitle = 'Administrátoři';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

  <div class="admin__head">
    <h1>Administrátoři</h1>
  </div>

  <section class="card">
    <p class="admin__hint">Všichni administrátoři mají stejná práva: release notes, podpora i manuály.
      Kdo má zapnuté <b>E-maily o požadavcích</b>, dostane zprávu o každém novém požadavku a o každé odpovědi zákazníka.</p>
    <div class="table-wrap">
      <table class="table table--cards">
        <thead><tr><th>Administrátor</th><th>E-maily o požadavcích</th><th>Poslední přihlášení</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a): $isMe = $a['username'] === $me; $email = (bool)filter_var($a['username'], FILTER_VALIDATE_EMAIL); ?>
          <tr>
            <td class="cell-title"><?= h($a['username']) ?><?php if ($isMe): ?> <span class="tag tag--module">vy</span><?php endif; ?>
              <span class="tmeta">od <?= h(date('d.m.Y', strtotime((string)$a['created_at']))) ?></span></td>
            <td data-label="E-maily">
              <?php if (!$email): ?>
                <span class="muted" title="Přihlašovací jméno není e-mail">— není e-mail</span>
              <?php else: ?>
                <form method="post" class="admin-notify">
                  <?= csrf_field() ?><input type="hidden" name="action" value="notify"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="switch" type="submit" role="switch" aria-checked="<?= (int)$a['notify_tickets'] ? 'true' : 'false' ?>"
                          aria-label="E-maily o požadavcích pro <?= h($a['username']) ?>"><span></span></button>
                  <span class="admin-notify__label"><?= (int)$a['notify_tickets'] ? 'Zapnuto' : 'Vypnuto' ?></span>
                </form>
              <?php endif; ?>
            </td>
            <td class="cell-muted" data-label="Přihlášení"><?= $a['last_login'] ? h(date('d.m.Y H:i', strtotime((string)$a['last_login']))) : '—' ?></td>
            <td class="cell-actions">
              <div class="row-actions">
                <a class="button button--ghost button--sm" href="admins?password=<?= (int)$a['id'] ?>#pw-form">Změnit heslo</a>
                <?php if (!$isMe && count($admins) > 1): ?>
                  <form method="post" onsubmit="return confirm(<?= h(json_encode('Smazat administrátora ' . $a['username'] . '?', JSON_UNESCAPED_UNICODE)) ?>)">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="button button--danger button--sm" type="submit">Smazat</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($configured): ?>
      <p class="admin__hint" style="margin-top:12px">E-maily o požadavcích chodí navíc na adresy z <code>config.php</code> (<code>support.notify</code>): <?= h(implode(', ', $configured)) ?>.</p>
    <?php endif; ?>

    <?php if ($pwFor && ($pa = admin_get($pwFor))): ?>
      <form method="post" id="pw-form" class="new-version" style="margin-top:16px" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$pa['id'] ?>">
        <h3 class="card__title" style="font-size:15px">Nové heslo pro <?= h($pa['username']) ?></h3>
        <div class="field">
          <label for="pw">Nové heslo *</label>
          <div class="inline-form">
            <input id="pw" name="password" type="text" minlength="8" required autocomplete="new-password" spellcheck="false" autofocus>
            <button class="button button--ghost button--sm" type="button" data-gen="pw">Vygenerovat</button>
          </div>
        </div>
        <div class="form-actions">
          <button class="button button--primary" type="submit">Změnit heslo</button>
          <a class="button button--ghost" href="admins">Zrušit</a>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <form class="card" method="post" id="new" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <h2 class="card__title">Přidat administrátora</h2>
    <div class="form-grid">
      <div class="field">
        <label for="email">E-mail (přihlašovací jméno) *</label>
        <input id="email" type="email" name="email" required maxlength="80" value="<?= h($nf['email']) ?>" autocomplete="off">
      </div>
      <div class="field">
        <label for="password">Heslo *</label>
        <div class="inline-form">
          <input id="password" name="password" type="text" minlength="8" required value="<?= h($nf['password']) ?>" autocomplete="new-password" spellcheck="false">
          <button class="button button--ghost button--sm" type="button" data-gen="password">Vygenerovat</button>
        </div>
      </div>
      <div class="field full">
        <label class="check"><input type="checkbox" name="send" value="1" <?= $nf['send'] ? 'checked' : '' ?>> Poslat přihlašovací údaje e-mailem</label>
        <small>Nový administrátor má e-maily o požadavcích zapnuté; vypnout si je může tady.</small>
      </div>
    </div>
    <div class="form-actions">
      <button class="button button--primary" type="submit">Přidat administrátora</button>
    </div>
  </form>
</main>
<script>
  document.querySelectorAll('[data-gen]').forEach(function (b) {
    b.addEventListener('click', function () {
      var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', a = new Uint32Array(14), s = '';
      crypto.getRandomValues(a);
      for (var i = 0; i < a.length; i++) s += chars[a[i] % chars.length];
      var p = document.getElementById(b.getAttribute('data-gen'));
      p.value = s; p.focus(); p.select();
    });
  });
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
