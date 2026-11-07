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
                'html' => mail_layout('Testovací e-mail', '<p>Testovací e-mail z administrace přehledu změn Zoty.</p><p><b>Odesílání funguje.</b></p>'), 'tag' => 'Test']]);
            flash($r['sent'] ? 'Testovací e-mail byl odeslán na ' . $to . '.' : 'Odeslání selhalo: ' . ($r['error'] ?? 'neznámá chyba'));
        }
        header('Location: subscribers#mail-log');
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
$logErrors = ($_GET['log'] ?? '') === 'errors';
$logLimit = ($_GET['all'] ?? '') === '1' ? MAIL_LOG_KEEP : 30;
$mailLog = mail_log_tail($logLimit, $logErrors);
$logStats = mail_log_stats();

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
  </form>

  <section class="card mail-log-card" id="mail-log" aria-labelledby="mail-log-h">
    <div class="mail-log-card__head">
      <h2 class="card__title" id="mail-log-h">Log odesílání e-mailů</h2>
      <nav class="tabs tabs--compact" aria-label="Filtr logu">
        <a href="subscribers#mail-log" <?= !$logErrors ? 'aria-current="page"' : '' ?>>Vše<b><?= $logStats['ok'] + $logStats['failed'] ?></b></a>
        <a href="subscribers?log=errors#mail-log" <?= $logErrors ? 'aria-current="page"' : '' ?>>Jen chyby<b><?= $logStats['failed'] ?></b></a>
      </nav>
    </div>
    <p class="admin__hint">Každý odeslaný i neodeslaný e-mail: potvrzení odběru, rozesílky verzí, upozornění k požadavkům, přístupy do portálu a testy. Uchovává se posledních <?= MAIL_LOG_KEEP ?> záznamů (<code>data/mail.log</code>).</p>
    <div class="table-wrap">
      <table class="table table--cards mail-log-table">
        <thead><tr><th>Čas</th><th>Stav</th><th>Příjemce</th><th>Předmět</th><th>Účel</th><th>Detail</th></tr></thead>
        <tbody>
        <?php if (!$mailLog): ?>
          <tr><td colspan="6" class="cell-empty"><?= $logErrors ? 'Žádné chyby odesílání.' : 'Zatím nebyl odeslán žádný e-mail.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($mailLog as $e): ?>
          <tr class="<?= $e['ok'] ? '' : 'is-failed' ?>">
            <td class="nowrap cell-muted" data-label="Čas"><?= h($e['t'] !== '' && strtotime($e['t']) ? date('d.m.Y H:i:s', strtotime($e['t'])) : $e['t']) ?></td>
            <td data-label="Stav"><?= $e['ok'] ? '<span class="tag tag--new">Odesláno</span>' : '<span class="tag tag--security">Chyba</span>' ?></td>
            <td class="cell-title" data-label="Příjemce"><?= $e['to'] !== '' ? h($e['to']) : '<span class="muted">—</span>' ?></td>
            <td data-label="Předmět"><?= h($e['subject']) ?></td>
            <td class="nowrap" data-label="Účel"><?= h($e['tag']) ?></td>
            <td class="mail-log-table__detail" data-label="Detail"><?= h($e['error'] === '' ? $e['via'] : (str_starts_with($e['error'], $e['via']) || $e['via'] === '' ? $e['error'] : $e['via'] . ' · ' . $e['error'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($logLimit < MAIL_LOG_KEEP && count($mailLog) >= $logLimit): ?>
      <p class="mail-log-card__more"><a href="subscribers?<?= $logErrors ? 'log=errors&amp;' : '' ?>all=1#mail-log">Zobrazit celý log</a></p>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
