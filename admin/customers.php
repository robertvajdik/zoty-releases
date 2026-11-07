<?php
require __DIR__ . '/../lib.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$customer = $id ? get_customer($id) : null;
if ($id && !$customer) {
    http_response_code(404);
    exit('Zákazník nenalezen.');
}
$errors = [];
$cf = ['name' => $customer['name'] ?? '', 'code' => $customer['code'] ?? ''];
$uf = ['id' => 0, 'email' => '', 'name' => '', 'password' => '', 'send' => true];

/** E-mails portal access to a customer user. */
function send_access(array $user, string $password): bool
{
    $L = 'cs';
    $url = site_url('portal/');
    $text = tr('access.mail.intro', ['customer' => $user['customer_name']], $L) . "\r\n\r\n"
          . $url . "\r\n" . tr('portal.email', [], $L) . ': ' . $user['email'] . "\r\n" . tr('portal.password', [], $L) . ': ' . $password . "\r\n\r\n"
          . tr('access.mail.change', [], $L) . "\r\n";
    $html = '<p>' . h(tr('access.mail.intro', ['customer' => $user['customer_name']], $L)) . '</p>'
          . '<p style="background:#f9f9fc;border:1px solid #ecebec;border-radius:8px;padding:12px 16px">'
          . h(tr('portal.email', [], $L)) . ': <b>' . h($user['email']) . '</b><br>' . h(tr('portal.password', [], $L)) . ': <b>' . h($password) . '</b></p>'
          . '<p style="margin:24px 0"><a href="' . h($url) . '" style="background:#6144a3;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">'
          . h(tr('access.mail.button', [], $L)) . '</a></p><p style="color:#656565;font-size:13px">' . h(tr('access.mail.change', [], $L)) . '</p>';
    $r = mail_send([['to' => $user['email'], 'subject' => tr('access.mail.subject', [], $L), 'text' => $text, 'html' => mail_layout(tr('access.mail.subject', [], $L), $html)]]);
    if (!empty($r['error'])) mail_log('Přístup ' . $user['email'] . ': ' . $r['error']);
    return $r['sent'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $back = 'customers' . ($customer ? '?id=' . $customer['id'] : '');

    if ($action === 'save_customer') {
        $cf['name'] = trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')));
        // tolerant input: "nbu zoty" / "NBU-ZOTY" -> NBU_ZOTY (diacritics and other characters are dropped)
        $raw = (string)($_POST['code'] ?? '');
        $raw = function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT', $raw) : $raw;
        $cf['code'] = trim(preg_replace('/[^A-Z0-9_]/', '', preg_replace('/[\s\-.]+/', '_', strtoupper(trim($raw)))), '_');
        if ($customer && $cf['code'] !== $customer['code']) {
            $cnt = fetch_tickets(['customer_id' => $customer['id']]);
            if ($cnt) $errors[] = 'Klíč nelze změnit – zákazník už má požadavky (' . $customer['code'] . '-…).';
        }
        $errors = array_merge($errors, validate_customer($cf['name'], $cf['code'], $customer['id'] ?? 0));
        if (!$errors) {
            $newId = save_customer($cf['name'], $cf['code'], $customer['id'] ?? 0);
            flash($customer ? 'Zákazník byl uložen.' : 'Zákazník ' . $cf['name'] . ' byl založen. Teď mu přidejte uživatele.');
            header('Location: customers?id=' . $newId);
            exit;
        }
    } elseif ($action === 'delete_customer' && $customer) {
        flash(delete_customer($customer['id']) ? 'Zákazník ' . $customer['name'] . ' byl smazán.' : 'Zákazníka nelze smazat, protože má požadavky.');
        header('Location: customers');
        exit;
    } elseif ($action === 'save_user' && $customer) {
        $uf['id']       = (int)($_POST['user_id'] ?? 0);
        $uf['email']    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $uf['name']     = trim((string)($_POST['uname'] ?? ''));
        $uf['password'] = (string)($_POST['password'] ?? '');
        $uf['send']     = !empty($_POST['send']);
        $existing = $uf['id'] ? get_customer_user($uf['id']) : null;
        if ($uf['id'] && (!$existing || (int)$existing['customer_id'] !== $customer['id'])) {
            $errors[] = 'Uživatel nenalezen.';
        } else {
            $pass = $uf['password'] === '' && $existing ? null : $uf['password'];
            $errors = validate_customer_user($uf['email'], $uf['name'], $pass, $uf['id']);
            if (!$errors) {
                $uid = save_customer_user($customer['id'], $uf['email'], $uf['name'], $pass, $uf['id']);
                $msg = $existing ? 'Uživatel byl uložen.' : 'Uživatel ' . $uf['email'] . ' byl přidán.';
                if ($pass !== null && $uf['send']) {
                    $msg .= send_access(get_customer_user($uid), $pass) ? ' Přístup byl odeslán e-mailem.' : ' E-mail s přístupem se nepodařilo odeslat – předejte heslo jinak.';
                }
                flash($msg);
                header('Location: ' . $back . '#users');
                exit;
            }
        }
    } elseif (in_array($action, ['toggle_user', 'delete_user'], true) && $customer) {
        $u = get_customer_user((int)($_POST['user_id'] ?? 0));
        if ($u && (int)$u['customer_id'] === $customer['id']) {
            if ($action === 'toggle_user') {
                customer_user_set_active((int)$u['id'], !(int)$u['active']);
                flash('Přístup uživatele ' . $u['email'] . ((int)$u['active'] ? ' byl zablokován.' : ' byl obnoven.'));
            } else {
                delete_customer_user((int)$u['id']);
                flash('Uživatel ' . $u['email'] . ' byl smazán. Jeho požadavky zůstávají.');
            }
        }
        header('Location: ' . $back . '#users');
        exit;
    }
}

$editUser = null;
if ($customer && ($eu = (int)($_GET['user'] ?? 0)) && !$errors) {
    $editUser = get_customer_user($eu);
    if ($editUser && (int)$editUser['customer_id'] === $customer['id']) {
        $uf = ['id' => (int)$editUser['id'], 'email' => $editUser['email'], 'name' => $editUser['name'], 'password' => '', 'send' => false];
    } else {
        $editUser = null;
    }
} elseif ($uf['id']) {
    $editUser = get_customer_user($uf['id']);
}

$customers = fetch_customers();
$users = $customer ? fetch_customer_users($customer['id']) : [];

$base_path = '../';
$admin_area = true;
$pageTitle = $customer ? $customer['name'] : 'Zákazníci';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash<?= str_contains($m, 'nelze') || str_contains($m, 'nepodařilo') ? ' flash--error' : '' ?>"><?= h($m) ?></div><?php endif; ?>
  <div class="admin__head">
    <h1><?= $customer ? h($customer['name']) . ' <span class="code-tag">' . h($customer['code']) . '</span>' : 'Zákazníci' ?></h1>
    <div class="admin__actions">
      <?php if ($customer): ?>
        <a class="button button--ghost" href="tickets?status=all&amp;customer=<?= $customer['id'] ?>">Požadavky zákazníka</a>
        <a class="button button--ghost" href="customers">← Všichni zákazníci</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

  <div class="cust-grid">
    <?php if (!$customer): ?>
      <div class="table-wrap card" style="margin-top:0">
        <table class="table table--cards">
          <thead><tr><th>Zákazník</th><th>Klíč</th><th>Uživatelé</th><th>Otevřené</th></tr></thead>
          <tbody>
          <?php if (!$customers): ?><tr><td colspan="4" class="cell-empty">Zatím žádní zákazníci.</td></tr><?php endif; ?>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td class="cell-title"><a href="customers?id=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a></td>
              <td data-label="Klíč"><span class="code-tag"><?= h($c['code']) ?></span></td>
              <td data-label="Uživatelé"><?= (int)$c['user_count'] ?></td>
              <td data-label="Otevřené"><?= (int)$c['open_count'] ?> <span class="muted">/ <?= (int)$c['ticket_count'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <form class="card" method="post" id="<?= $customer ? 'edit' : 'new' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_customer">
      <h2 class="card__title"><?= $customer ? 'Údaje zákazníka' : 'Nový zákazník' ?></h2>
      <div class="form-grid">
        <div class="field full">
          <label for="name">Název *</label>
          <input id="name" name="name" required maxlength="160" value="<?= h($cf['name']) ?>" placeholder="např. ACME s.r.o.">
        </div>
        <div class="field full">
          <label for="code">Klíč požadavků *</label>
          <input id="code" name="code" required maxlength="10" value="<?= h($cf['code']) ?>" placeholder="ACME"
                 autocapitalize="characters" autocomplete="off" spellcheck="false" style="text-transform:uppercase">
          <small>Požadavky se číslují ACME-1, ACME-2… (NBU_ZOTY-1). 2–10 znaků A–Z, 0–9 nebo _, nelze změnit, jakmile existují požadavky.</small>
        </div>
      </div>
      <div class="form-actions">
        <button class="button button--primary" type="submit"><?= $customer ? 'Uložit' : 'Založit zákazníka' ?></button>
      </div>
    </form>

    <?php if ($customer): ?>
      <section class="card" id="users">
        <h2 class="card__title">Uživatelé portálu</h2>
        <p class="admin__hint">Každý uživatel se přihlásí na <a href="../portal/" target="_blank"><?= h(site_url('portal/')) ?></a> a vidí všechny požadavky zákazníka <?= h($customer['name']) ?>.</p>
        <div class="table-wrap">
          <table class="table table--cards">
            <thead><tr><th>Uživatel</th><th>Stav</th><th>Poslední přihlášení</th><th></th></tr></thead>
            <tbody>
            <?php if (!$users): ?><tr><td colspan="4" class="cell-empty">Zatím žádní uživatelé.</td></tr><?php endif; ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td class="cell-title"><?= h($u['name'] ?: $u['email']) ?><?php if ($u['name']): ?><span class="tmeta"><?= h($u['email']) ?></span><?php endif; ?></td>
                <td data-label="Stav"><?= (int)$u['active'] ? '<span class="tag tag--new">Aktivní</span>' : '<span class="tag tag--draft">Blokován</span>' ?></td>
                <td class="cell-muted" data-label="Přihlášení"><?= $u['last_login'] ? h(date('d.m.Y H:i', strtotime($u['last_login']))) : '—' ?></td>
                <td class="cell-actions">
                  <div class="row-actions">
                    <a class="button button--ghost button--sm" href="customers?id=<?= $customer['id'] ?>&amp;user=<?= (int)$u['id'] ?>#user-form">Upravit</a>
                    <form method="post">
                      <?= csrf_field() ?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <button class="button button--ghost button--sm" type="submit"><?= (int)$u['active'] ? 'Zablokovat' : 'Odblokovat' ?></button>
                    </form>
                    <form method="post" onsubmit="return confirm(<?= h(json_encode('Smazat uživatele ' . $u['email'] . '?', JSON_UNESCAPED_UNICODE)) ?>)">
                      <?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <button class="button button--danger button--sm" type="submit">Smazat</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <form method="post" id="user-form" class="new-version" style="margin-top:16px" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_user">
          <input type="hidden" name="user_id" value="<?= (int)$uf['id'] ?>">
          <h3 class="card__title" style="font-size:15px"><?= $uf['id'] ? 'Upravit uživatele ' . h($uf['email']) : 'Přidat uživatele' ?></h3>
          <div class="form-grid">
            <div class="field">
              <label for="email">E-mail (přihlašovací jméno) *</label>
              <input id="email" type="email" name="email" required maxlength="190" value="<?= h($uf['email']) ?>" autocomplete="off">
            </div>
            <div class="field">
              <label for="uname">Jméno</label>
              <input id="uname" name="uname" maxlength="160" value="<?= h($uf['name']) ?>" placeholder="Jan Novák">
            </div>
            <div class="field full">
              <label for="password"><?= $uf['id'] ? 'Nové heslo (prázdné = beze změny)' : 'Heslo *' ?></label>
              <div class="inline-form">
                <input id="password" name="password" type="text" minlength="8" value="<?= h($uf['password']) ?>" <?= $uf['id'] ? '' : 'required' ?> autocomplete="new-password" spellcheck="false">
                <button class="button button--ghost button--sm" type="button" id="gen-pass">Vygenerovat</button>
              </div>
            </div>
            <div class="field full">
              <label class="check"><input type="checkbox" name="send" value="1" <?= $uf['send'] ? 'checked' : '' ?>> Poslat přihlašovací údaje uživateli e-mailem (jen při zadání hesla)</label>
            </div>
          </div>
          <div class="form-actions">
            <button class="button button--primary" type="submit"><?= $uf['id'] ? 'Uložit uživatele' : 'Přidat uživatele' ?></button>
            <?php if ($uf['id']): ?><a class="button button--ghost" href="customers?id=<?= $customer['id'] ?>#users">Zrušit</a><?php endif; ?>
          </div>
        </form>
      </section>

      <form method="post" class="danger-zone" onsubmit="return confirm(<?= h(json_encode('Smazat zákazníka ' . $customer['name'] . ' i s jeho uživateli?', JSON_UNESCAPED_UNICODE)) ?>)">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_customer">
        <button class="button button--danger button--sm" type="submit">Smazat zákazníka</button>
        <small class="muted">Jde jen u zákazníka bez požadavků.</small>
      </form>
    <?php endif; ?>
  </div>
</main>
<script>
  (function () {
    var b = document.getElementById('gen-pass');
    if (!b) return;
    b.addEventListener('click', function () {
      var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', a = new Uint32Array(12), s = '';
      crypto.getRandomValues(a);
      for (var i = 0; i < a.length; i++) s += chars[a[i] % chars.length];
      var p = document.getElementById('password');
      p.value = s; p.focus(); p.select();
    });
  })();
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
