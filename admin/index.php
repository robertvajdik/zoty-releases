<?php
require __DIR__ . '/../lib.php';
require_admin();

// Actions: delete, toggle publish
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $e = get_entry((int)($_POST['id'] ?? 0));
    $action = (string)($_POST['action'] ?? '');
    if ($e && $action === 'delete') {
        delete_entry($e['id']);
        flash('Záznam „' . $e['title'] . '“ byl smazán.');
    } elseif ($e && $action === 'toggle') {
        set_published($e['id'], !$e['published']);
        flash(!$e['published'] ? 'Záznam byl publikován.' : 'Záznam byl přesunut mezi koncepty.');
    }
    header('Location: ./' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

$filters  = current_filters($_GET);
$list     = fetch_entries($filters, true);
$versions = distinct_values('version', false);
$modules  = distinct_values('module', false);
$qs       = http_build_query($filters);
$stats    = entry_stats();

$base_path = '../';
$admin_area = true;
$pageTitle = 'Administrace';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>

  <div class="admin__head">
    <h1>Záznamy changelogu</h1>
    <div class="admin__actions">
      <a class="button button--ghost" href="export<?= $qs ? '?' . h($qs) : '' ?>">Stáhnout MD (publikované)</a>
      <a class="button button--ghost" href="export?<?= h(http_build_query($filters + ['drafts' => 1])) ?>">Stáhnout MD (vč. konceptů)</a>
      <a class="button button--ghost" href="versions">Verze</a>
    </div>
  </div>

  <div class="stats">
    <div class="stat"><b><?= $stats['total'] ?></b><span>záznamů celkem</span></div>
    <div class="stat"><b><?= $stats['published'] ?></b><span>publikováno</span></div>
    <div class="stat"><b><?= $stats['total'] - $stats['published'] ?></b><span>konceptů</span></div>
    <div class="stat"><b><?= $stats['versions'] ?></b><span>verzí</span></div>
  </div>

  <form class="toolbar toolbar--admin" method="get">
    <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Hledat…" aria-label="Hledat">
    <select name="version" aria-label="Verze">
      <option value="">Všechny verze</option>
      <?php foreach ($versions as $v): ?><option value="<?= h($v) ?>" <?= ($filters['version'] ?? '') === $v ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
    </select>
    <select name="type" aria-label="Typ">
      <option value="">Všechny typy</option>
      <?php foreach (TYPES as $k => $t): ?><option value="<?= $k ?>" <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>><?= h($t['label']) ?></option><?php endforeach; ?>
    </select>
    <select name="module" aria-label="Modul">
      <option value="">Všechny moduly</option>
      <?php foreach ($modules as $m): ?><option value="<?= h($m) ?>" <?= ($filters['module'] ?? '') === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= h($filters['from'] ?? '') ?>" aria-label="Od data" title="Od data">
    <input type="date" name="to" value="<?= h($filters['to'] ?? '') ?>" aria-label="Do data" title="Do data">
    <button class="button button--primary" type="submit">Filtrovat</button>
    <?php if ($filters): ?><a class="button button--ghost" href="./">Zrušit</a><?php endif; ?>
  </form>

  <div class="card table-wrap">
    <table class="table table--cards">
      <thead>
        <tr><th>Datum</th><th>Verze</th><th>Typ</th><th>Modul</th><th>Název</th><th>Stav</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--muted)">Žádné záznamy. <a href="edit">Vytvořit první záznam</a></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $e): ?>
        <tr>
          <td class="nowrap" data-label="Datum"><?= h(date("d.m.Y", strtotime($e["date"]))) ?></td>
          <td class="nowrap" data-label="Verze"><?= h($e["version"]) ?><?= $e["version_published"] ? "" : " <span class=\"tag tag--draft\" title=\"Verze není publikovaná\">skrytá</span>" ?></td>
          <td data-label="Typ"><span class="tag tag--<?= h($e["type"]) ?>"><?= h(TYPES[$e['type']]['label'] ?? $e['type']) ?></span></td>
          <td data-label="Modul"><?= h($e["module"]) ?></td>
          <td class="cell-title" data-label="Název"><a href="edit?id=<?= $e["id"] ?>"><?= h($e["title"]) ?></a></td>
          <td data-label="Stav"><?= !empty($e["published"]) ? '<span class="tag tag--new">Publikováno</span>' : '<span class="tag tag--draft">Koncept</span>' ?></td>
          <td class="cell-actions">
            <div class="row-actions">
              <a class="button button--ghost button--sm" href="edit?id=<?= h($e['id']) ?>">Upravit</a>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($e['id']) ?>"><input type="hidden" name="action" value="toggle">
                <button class="button button--ghost button--sm" type="submit"><?= !empty($e['published']) ? 'Skrýt' : 'Publikovat' ?></button>
              </form>
              <form method="post" onsubmit="return confirm('Opravdu smazat tento záznam?')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($e['id']) ?>"><input type="hidden" name="action" value="delete">
                <button class="button button--danger button--sm" type="submit">Smazat</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
