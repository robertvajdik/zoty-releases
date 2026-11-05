<?php
require __DIR__ . '/../lib.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'test') {
        $to = trim((string)($_POST['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('Zadejte platnou adresu pro testovací e-mail.');
        } else {
            $r = mail_send([['to' => $to, 'subject' => 'Zoty – testovací e-mail',
                'text' => "Testovací e-mail z administrace přehledu změn Zoty. Odesílání funguje.",
                'html' => mail_layout('Testovací e-mail', '<p>Testovací e-mail z administrace přehledu změn Zoty.</p><p><b>Odesílání funguje.</b></p>')]]);
            flash($r['sent'] ? 'Testovací e-mail byl odeslán na ' . $to . '.' : 'Odeslání selhalo: ' . ($r['error'] ?? 'neznámá chyba'));
        }
        header('Location: subscribers#mail-test');
        exit;
    }
    if (($_POST['action'] ?? '') === 'delete' && ($id = (int)($_POST['id'] ?? 0))) {
        subscriber_delete($id);
        flash('Odběratel byl odebrán.');
    }
    header('Location: subscribers');
    exit;
}

$subs = fetch_subscribers();
$confirmed = count(array_filter($subs, fn($s) => (int)$s['confirmed'] === 1));
$mc = config()['mail'] ?? [];
$mailLog = mail_log_tail(8);

$base_path = '../';
$admin_area = true;
$pageTitle = 'Odběratelé';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <div class="admin__head">
    <h1>Odběratelé novinek</h1>
    <div class="admin__actions">
      <a class="button button--ghost" href="versions">Rozeslat verzi…</a>
    </div>
  </div>

  <div class="stats">
    <div class="stat"><b><?= $confirmed ?></b><span>potvrzených</span></div>
    <div class="stat"><b><?= count($subs) - $confirmed ?></b><span>čeká na potvrzení</span></div>
  </div>

  <p class="admin__hint">E-mail o nové verzi rozešlete v sekci <a href="versions">Verze</a> tlačítkem <b>Rozeslat</b>. Dostanou ho jen potvrzení odběratelé.</p>

  <div class="table-wrap card">
    <table class="table table--cards">
      <thead><tr><th>E-mail</th><th>Stav</th><th>Přihlášen</th><th></th></tr></thead>
      <tbody>
      <?php if (!$subs): ?>
        <tr><td colspan="4" class="cell-empty">Zatím žádní odběratelé.</td></tr>
      <?php endif; ?>
      <?php foreach ($subs as $s): ?>
        <tr>
          <td class="cell-title" data-label="E-mail"><?= h($s['email']) ?></td>
          <td data-label="Stav"><?= (int)$s['confirmed'] ? '<span class="tag tag--new">Potvrzeno</span>' : '<span class="tag tag--draft">Čeká</span>' ?></td>
          <td class="nowrap" data-label="Přihlášen"><?= h(date('d.m.Y H:i', strtotime((string)($s['confirmed_at'] ?: $s['created_at'])))) ?></td>
          <td class="cell-actions">
            <div class="row-actions">
              <form method="post" onsubmit="return confirm(<?= h(json_encode('Odebrat ' . $s['email'] . ' z odběru?', JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="button button--danger button--sm" type="submit">Odebrat</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form class="card mail-test" method="post" id="mail-test">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test">
    <h2 class="card__title">Test odesílání e-mailů</h2>
    <p class="admin__hint">Odesílá se přes <b><?= h(($mc['transport'] ?? 'mail') === 'smtp' ? 'SMTP ' . ($mc['host'] ?? '') . ':' . ($mc['port'] ?? '') . ' (' . ($mc['secure'] ?? '') . ')' : 'PHP mail()') ?></b>
      jako <b><?= h($mc['from'] ?? '') ?></b><?= !empty($mc['fallback_mail']) ? ', při chybě SMTP záložně přes PHP mail()' : '' ?>.</p>
    <div class="mail-test__row">
      <input type="email" name="to" required placeholder="vas@email.cz" value="<?= h($_SESSION['zoty_admin'] ?? '') ?>" aria-label="Adresa pro test">
      <button class="button button--primary" type="submit">Odeslat testovací e-mail</button>
    </div>
    <?php if ($mailLog): ?>
      <h3 class="mail-test__h">Poslední chyby odesílání</h3>
      <pre class="mail-log"><?= h(implode("\n", $mailLog)) ?></pre>
    <?php endif; ?>
  </form>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
