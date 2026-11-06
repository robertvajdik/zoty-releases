<?php
require __DIR__ . '/../lib.php';
$me = require_portal_user();

// The customer's space: all requests of the customer's organisation.
$tab = in_array($_GET['status'] ?? 'open', ['open', 'done', 'all'], true) ? ($_GET['status'] ?? 'open') : 'open';
$mine = !empty($_GET['mine']);
$f = [
    'customer_id' => $me['customer_id'],
    'status' => $tab === 'all' ? '' : $tab,
    'type' => (string)($_GET['type'] ?? ''),
    'q' => trim((string)($_GET['q'] ?? '')),
    'user_id' => $mine ? $me['id'] : 0,
];
$tickets = fetch_tickets($f);
$stats = ticket_stats($me['customer_id']);
$openCount = $stats['open'] + $stats['in_progress'] + $stats['waiting'];
$doneCount = $stats['resolved'] + $stats['closed'];
$keep = array_filter(['q' => $f['q'], 'type' => $f['type'], 'mine' => $mine ? '1' : '']);
$tabUrl = fn(string $s) => '?' . http_build_query(['status' => $s] + $keep);

$base_path = '../';
$portal_area = true;
$pageTitle = tr('portal.my_requests');
$headExtra = '<meta name="robots" content="noindex">';
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <div class="portal__head">
    <div>
      <h1><?= h(tr('portal.my_requests')) ?></h1>
      <p><?= h(tr('portal.space_of', ['customer' => $me['customer_name']])) ?></p>
    </div>
    <a class="button button--primary" href="new"><?= h(tr('portal.new_request')) ?></a>
  </div>

  <nav class="tabs" aria-label="<?= h(tr('portal.filter_status')) ?>">
    <a href="<?= h($tabUrl('open')) ?>" <?= $tab === 'open' ? 'aria-current="page"' : '' ?>><?= h(tr('portal.tab_open')) ?><b><?= $openCount ?></b></a>
    <a href="<?= h($tabUrl('done')) ?>" <?= $tab === 'done' ? 'aria-current="page"' : '' ?>><?= h(tr('portal.tab_done')) ?><b><?= $doneCount ?></b></a>
    <a href="<?= h($tabUrl('all')) ?>" <?= $tab === 'all' ? 'aria-current="page"' : '' ?>><?= h(tr('portal.tab_all')) ?><b><?= $openCount + $doneCount ?></b></a>
  </nav>

  <form class="tlist-tools" method="get" role="search">
    <input type="hidden" name="status" value="<?= h($tab) ?>">
    <label class="visually-hidden" for="q"><?= h(tr('portal.search')) ?></label>
    <input type="search" id="q" name="q" value="<?= h($f['q']) ?>" placeholder="<?= h(tr('portal.search_placeholder')) ?>">
    <label class="visually-hidden" for="type"><?= h(tr('ticket.request_type')) ?></label>
    <select id="type" name="type" onchange="this.form.submit()">
      <option value=""><?= h(tr('portal.all_types')) ?></option>
      <?php foreach (TICKET_TYPES as $t): ?><option value="<?= $t ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= h(ticket_type_label($t)) ?></option><?php endforeach; ?>
    </select>
    <label class="check"><input type="checkbox" name="mine" value="1" <?= $mine ? 'checked' : '' ?> onchange="this.form.submit()"> <?= h(tr('portal.only_mine')) ?></label>
    <button class="button button--ghost" type="submit"><?= h(tr('portal.search')) ?></button>
  </form>

  <div class="table-wrap card">
    <table class="table table--cards">
      <thead><tr>
        <th><?= h(tr('ticket.key')) ?></th><th><?= h(tr('ticket.summary')) ?></th><th><?= h(tr('ticket.status')) ?></th>
        <th><?= h(tr('ticket.severity')) ?></th><th><?= h(tr('ticket.reporter')) ?></th><th><?= h(tr('ticket.updated')) ?></th>
      </tr></thead>
      <tbody>
      <?php if (!$tickets): ?>
        <tr><td colspan="6" class="cell-empty">
          <?= h(tr($f['q'] !== '' || $f['type'] !== '' || $mine ? 'portal.empty_filter' : 'portal.empty')) ?>
          <?php if ($tab === 'open' && !$openCount): ?><br><a href="new"><?= h(tr('portal.create_first')) ?></a><?php endif; ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($tickets as $t): $url = 'ticket?key=' . rawurlencode($t['key']); ?>
        <tr>
          <td class="nowrap" data-label="<?= h(tr('ticket.key')) ?>"><a class="tkey" href="<?= h($url) ?>"><?= h($t['key']) ?></a></td>
          <td class="cell-title"><a class="tsum" href="<?= h($url) ?>"><span class="rt rt--<?= h($t['type']) ?>" title="<?= h(ticket_type_label($t['type'])) ?>"></span><?= h($t['summary']) ?></a>
            <span class="tmeta"><?= h(ticket_type_label($t['type'])) ?> · <?= h(option_label($t['product'])) ?><?= (int)$t['comment_count'] ? ' · ' . h(tr('portal.comments', ['n' => (int)$t['comment_count']])) : '' ?></span></td>
          <td data-label="<?= h(tr('ticket.status')) ?>"><?= status_lozenge($t['status']) ?></td>
          <td data-label="<?= h(tr('ticket.severity')) ?>"><?= severity_badge($t['severity']) ?></td>
          <td class="cell-muted" data-label="<?= h(tr('ticket.reporter')) ?>"><?= h($t['reporter_name'] ?: $t['reporter_email']) ?></td>
          <td class="cell-muted" data-label="<?= h(tr('ticket.updated')) ?>"><?= fmt_date($t['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
