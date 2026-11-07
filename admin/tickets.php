<?php
require __DIR__ . '/../lib.php';
require_admin();

$status = (string)($_GET['status'] ?? 'unresolved');
$f = [
    'status'      => $status === 'all' ? '' : $status,
    'customer_id' => (int)($_GET['customer'] ?? 0),
    'type'        => (string)($_GET['type'] ?? ''),
    'severity'    => (string)($_GET['severity'] ?? ''),
    'q'           => trim((string)($_GET['q'] ?? '')),
];
$tickets = fetch_tickets($f);
$stats = ticket_stats();
$customers = fetch_customers();
$ov = support_overview();
$keep = array_filter(['customer' => $f['customer_id'] ?: '', 'type' => $f['type'], 'severity' => $f['severity'], 'q' => $f['q']]);
$tabUrl = fn(string $s) => '?' . http_build_query(['status' => $s] + $keep);
$tabs = ['unresolved' => ['Nevyřešené', $stats['open'] + $stats['in_progress'] + $stats['waiting']]];
foreach (TICKET_STATUSES as $s) $tabs[$s] = [status_label($s), $stats[$s]];
$tabs['all'] = ['Vše', array_sum($stats)];

$base_path = '../';
$admin_area = true;
$pageTitle = 'Požadavky';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <div class="admin__head">
    <h1>Požadavky zákazníků</h1>
  </div>

  <div class="overview">
    <a class="ov<?= $stats['open'] ? ' ov--alert' : ' ov--zero' ?>" href="?status=open"><b><?= $stats['open'] ?></b><span>čeká na podporu (nové / odpověď zákazníka)</span></a>
    <a class="ov<?= $ov['crisis'] ? ' ov--alert' : ' ov--zero' ?>" href="?status=unresolved&amp;severity=crisis"><b><?= $ov['crisis'] ?></b><span>nevyřešené v závažnosti Krize</span></a>
    <a class="ov<?= $stats['in_progress'] ? '' : ' ov--zero' ?>" href="?status=in_progress"><b><?= $stats['in_progress'] ?></b><span>řeší se</span></a>
    <a class="ov<?= $stats['waiting'] ? ' ov--warn' : ' ov--zero' ?>" href="?status=waiting"><b><?= $stats['waiting'] ?></b><span>čeká na zákazníka</span></a>
    <a class="ov ov--ok" href="?status=done"><b><?= $ov['resolved'] ?></b><span>vyřešeno za 7 dní (nových <?= $ov['new_week'] ?>)</span></a>
  </div>

  <?php if (!ticket_staff_emails()): ?>
    <p class="admin__hint">Tip: nastavte v <code>config.php</code> položku <code>'support' => ['notify' => ['podpora@…']]</code> a nové požadavky vám budou chodit e-mailem.</p>
  <?php endif; ?>

  <nav class="tabs" aria-label="Stav">
    <?php foreach ($tabs as $k => [$label, $n]): ?>
      <a href="<?= h($tabUrl($k)) ?>" <?= $status === $k ? 'aria-current="page"' : '' ?>><?= h($label) ?><b><?= $n ?></b></a>
    <?php endforeach; ?>
  </nav>

  <form class="tlist-tools" method="get" role="search">
    <input type="hidden" name="status" value="<?= h($status) ?>">
    <input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Hledat text nebo klíč (ACME-12)…" aria-label="Hledat">
    <select name="customer" onchange="this.form.submit()" aria-label="Zákazník">
      <option value="">Všichni zákazníci</option>
      <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $f['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="type" onchange="this.form.submit()" aria-label="Typ">
      <option value="">Všechny typy</option>
      <?php foreach (TICKET_TYPES as $t): ?><option value="<?= $t ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= h(ticket_type_label($t)) ?></option><?php endforeach; ?>
    </select>
    <select name="severity" onchange="this.form.submit()" aria-label="Závažnost">
      <option value="">Všechny závažnosti</option>
      <?php foreach (TICKET_SEVERITIES as $s): ?><option value="<?= $s ?>" <?= $f['severity'] === $s ? 'selected' : '' ?>><?= h(severity_label($s)) ?></option><?php endforeach; ?>
    </select>
    <button class="button button--ghost" type="submit">Hledat</button>
  </form>

  <div class="table-wrap card">
    <table class="table table--cards">
      <thead><tr><th>Klíč</th><th>Shrnutí</th><th>Zákazník</th><th>Stav</th><th>Závažnost</th><th>Aktualizováno</th></tr></thead>
      <tbody>
      <?php if (!$tickets): ?>
        <tr><td colspan="6" class="cell-empty"><?= $customers ? 'Žádné požadavky pro tento filtr.' : 'Zatím žádní zákazníci. <a href="customers">Založte prvního zákazníka</a> a pošlete mu přístup do portálu.' ?></td></tr>
      <?php endif; ?>
      <?php foreach ($tickets as $t): $url = 'ticket?key=' . rawurlencode($t['key']); ?>
        <tr>
          <td class="nowrap" data-label="Klíč"><a class="tkey" href="<?= h($url) ?>"><?= h($t['key']) ?></a></td>
          <td class="cell-title"><a class="tsum" href="<?= h($url) ?>"><span class="rt rt--<?= h($t['type']) ?>" title="<?= h(ticket_type_label($t['type'])) ?>"></span><?= h($t['summary']) ?></a>
            <span class="tmeta"><?= h(ticket_type_label($t['type'])) ?> · <?= h(option_label($t['product'])) ?> <?= h(option_label($t['product_version'])) ?> · <?= h($t['reporter_name'] ?: $t['reporter_email']) ?><?= (int)$t['comment_count'] ? ' · <svg class="i-comment" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg><span class="visually-hidden">komentáře: </span>' . (int)$t['comment_count'] : '' ?></span></td>
          <td class="cell-muted" data-label="Zákazník"><?= h($t['customer_name']) ?></td>
          <td data-label="Stav"><?= status_lozenge($t['status']) ?></td>
          <td data-label="Závažnost"><?= severity_badge($t['severity']) ?></td>
          <td class="cell-muted" data-label="Aktualizováno"><?= h(date('d.m.Y H:i', strtotime($t['updated_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
