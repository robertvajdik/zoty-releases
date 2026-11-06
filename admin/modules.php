<?php
require __DIR__ . '/../lib.php';
require_admin();

$errors = [];
$editId = (int)($_GET['id'] ?? 0);
$editing = $editId ? get_module($editId) : null;
if ($editId && !$editing) {
    http_response_code(404);
    exit('Modul nenalezen.');
}
$name = $editing['name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $target = get_module((int)($_POST['id'] ?? 0));
        if ($target && delete_module($target['id'])) {
            flash('Modul ' . $target['name'] . ' byl smazán.');
        } elseif ($target) {
            flash('Modul ' . $target['name'] . ' nelze smazat – používají ho záznamy. Nejdřív jim modul změňte.');
        }
        header('Location: modules');
        exit;
    }
    if ($action === 'save') {
        $name = trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')));
        $errors = validate_module($name, $editing['id'] ?? 0);
        if (!$errors) {
            save_module($name, $editing['id'] ?? 0);
            flash($editing ? 'Modul byl přejmenován na ' . $name . '.' : 'Modul ' . $name . ' byl přidán.');
            header('Location: modules');
            exit;
        }
    }
}

$modules = fetch_modules();

$base_path = '../';
$admin_area = true;
$pageTitle = 'Moduly';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash<?= str_contains($m, 'nelze') ? ' flash--error' : '' ?>"><?= h($m) ?></div><?php endif; ?>

  <div class="admin__head">
    <h1>Moduly</h1>
    <a class="button button--ghost" href="./">← Záznamy</a>
  </div>

  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

  <form class="card" method="post" action="modules<?= $editing ? '?id=' . $editing['id'] : '' ?>" id="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <h2 class="card__title"><?= $editing ? 'Přejmenovat modul ' . h($editing['name']) : 'Nový modul' ?></h2>
    <div class="form-grid">
      <div class="field full">
        <label for="name">Název modulu *</label>
        <input id="name" name="name" required maxlength="80" value="<?= h($name) ?>" placeholder="např. DataLink, Audit, Reporty" autocomplete="off">
        <?php if ($editing && $editing['entry_count']): ?><small>Nový název se použije i u <?= $editing['entry_count'] ?> záznamů tohoto modulu.</small><?php endif; ?>
      </div>
    </div>
    <div class="form-actions">
      <button class="button button--primary" type="submit"><?= $editing ? 'Uložit' : 'Přidat modul' ?></button>
      <?php if ($editing): ?><a class="button button--ghost" href="modules">Zrušit</a><?php endif; ?>
    </div>
  </form>

  <div class="card table-wrap" style="margin-top:20px">
    <table class="table table--cards">
      <thead>
        <tr><th>Modul</th><th>Záznamů</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$modules): ?>
        <tr><td colspan="3" class="cell-empty">Zatím žádné moduly. Přidejte první modul formulářem výše.</td></tr>
      <?php endif; ?>
      <?php foreach ($modules as $row): ?>
        <tr<?= $editing && $editing['id'] === $row['id'] ? ' class="is-current"' : '' ?>>
          <td class="cell-title" data-label="Modul"><a href="modules?id=<?= $row['id'] ?>#form"><?= h($row['name']) ?></a></td>
          <td data-label="Záznamů"><a href="./?module=<?= urlencode($row['name']) ?>"><?= $row['entry_count'] ?></a></td>
          <td class="cell-actions">
            <div class="row-actions">
              <a class="button button--ghost button--sm" href="modules?id=<?= $row['id'] ?>#form">Přejmenovat</a>
              <?php if ($row['entry_count'] === 0): ?>
                <form method="post" onsubmit="return confirm(<?= h(json_encode('Opravdu smazat modul ' . $row['name'] . '?', JSON_UNESCAPED_UNICODE)) ?>)">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><input type="hidden" name="action" value="delete">
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
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
