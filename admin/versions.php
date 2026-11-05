<?php
require __DIR__ . '/../lib.php';
require_admin();

$errors = [];
$editId = (int)($_GET['id'] ?? 0);
$editing = $editId ? get_version($editId) : null;
if ($editId && !$editing) {
    http_response_code(404);
    exit('Verze nenalezena.');
}
$v = $editing ?? ['id' => 0, 'version' => '', 'date' => date('Y-m-d'), 'title' => '', 'summary' => '', 'published' => true];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? 'save');
    $target = get_version((int)($_POST['id'] ?? 0));

    if ($action === 'delete' && $target) {
        if (delete_version($target['id'])) {
            flash('Verze ' . $target['version'] . ' byla smazána.');
        } else {
            flash('Verzi ' . $target['version'] . ' nelze smazat – obsahuje záznamy. Nejdřív je smažte nebo přesuňte do jiné verze.');
        }
        header('Location: versions');
        exit;
    }
    if ($action === 'toggle' && $target) {
        set_version_published($target['id'], !$target['published']);
        flash('Verze ' . $target['version'] . (!$target['published'] ? ' je publikovaná.' : ' je skrytá – její záznamy se na webu nezobrazí.'));
        header('Location: versions');
        exit;
    }
    if ($action === 'notify' && $target) {
        if (!$target['published']) {
            flash('Verze ' . $target['version'] . ' je skrytá – nejdřív ji publikujte.');
        } else {
            $r = notify_subscribers($target);
            if ($r['sent']) {
                flash('Verze ' . $target['version'] . ' byla rozeslána ' . $r['sent'] . ' odběratelům.'
                    . ($r['failed'] ? ' Nepodařilo se doručit ' . $r['failed'] . ' (' . $r['error'] . ').' : ''));
            } else {
                flash('E-mail nebyl odeslán: ' . ($r['error'] ?? 'neznámá chyba') . '.');
            }
        }
        header('Location: versions');
        exit;
    }
    if ($action === 'save') {
        $v = [
            'id'        => $editing['id'] ?? 0,
            'version'   => normalize_version_number((string)($_POST['version'] ?? '')),
            'date'      => trim((string)($_POST['date'] ?? '')),
            'title'     => trim((string)($_POST['title'] ?? '')),
            'summary'   => str_replace("\r\n", "\n", trim((string)($_POST['summary'] ?? ''))),
            'published' => !empty($_POST['published']),
        ];
        $errors = validate_version($v);
        if (!$errors) {
            $newId = save_version($v);
            flash($editing ? 'Verze ' . $v['version'] . ' byla uložena.' : 'Verze ' . $v['version'] . ' byla vytvořena.');
            header('Location: ' . (isset($_POST['add_entry']) ? 'edit?version_id=' . $newId : 'versions'));
            exit;
        }
    }
}

$versions = fetch_versions();
$mailings = fetch_mailings();
$subscriberCount = count(fetch_subscribers(true));

$base_path = '../';
$admin_area = true;
$pageTitle = 'Verze';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash<?= str_contains($m, 'nelze') ? ' flash--error' : '' ?>"><?= h($m) ?></div><?php endif; ?>

  <div class="admin__head">
    <h1>Verze</h1>
    <a class="button button--ghost" href="./">← Záznamy</a>
  </div>

  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

  <form class="card" method="post" action="versions<?= $editing ? '?id=' . $editing['id'] : '' ?>" id="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <h2 class="card__title"><?= $editing ? 'Upravit verzi ' . h($editing['version']) : 'Nová verze' ?></h2>
    <div class="form-grid">
      <div class="field">
        <label for="version">Číslo verze *</label>
        <input id="version" name="version" required maxlength="50" value="<?= h($v['version']) ?>" placeholder="např. v1.42.07" autocapitalize="none" autocomplete="off" spellcheck="false" pattern="[vV]?[0-9]+(\.[0-9]+){1,3}" title="Formát v1.42.07">
      </div>
      <div class="field">
        <label for="date">Datum vydání *</label>
        <input id="date" type="date" name="date" required value="<?= h($v['date']) ?>">
      </div>
      <div class="field full">
        <label for="vtitle">Název verze</label>
        <input id="vtitle" name="title" maxlength="255" value="<?= h($v['title']) ?>" placeholder="volitelné, např. Podzimní aktualizace">
      </div>
      <div class="field full">
        <label for="summary">Shrnutí verze</label>
        <textarea id="summary" name="summary" class="textarea--short" placeholder="Volitelný úvodní text zobrazený nad změnami této verze…"><?= h($v['summary']) ?></textarea>
      </div>
      <div class="field full">
        <label class="check"><input type="checkbox" name="published" value="1" <?= !empty($v['published']) ? 'checked' : '' ?>> Publikovat verzi na veřejném changelogu</label>
      </div>
    </div>
    <div class="form-actions">
      <button class="button button--primary" type="submit"><?= $editing ? 'Uložit verzi' : 'Vytvořit verzi' ?></button>
      <?php if (!$editing): ?><button class="button button--ghost" type="submit" name="add_entry" value="1">Vytvořit a přidat záznam</button><?php endif; ?>
      <?php if ($editing): ?><a class="button button--ghost" href="versions">Zrušit</a><?php endif; ?>
    </div>
  </form>

  <div class="card table-wrap" style="margin-top:20px">
    <table class="table table--cards">
      <thead>
        <tr><th>Verze</th><th>Datum vydání</th><th>Název</th><th>Záznamů</th><th>Stav</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$versions): ?>
        <tr><td colspan="6" class="cell-empty">Zatím žádné verze. Vytvořte první verzi formulářem výše.</td></tr>
      <?php endif; ?>
      <?php foreach ($versions as $row): ?>
        <tr<?= $editing && $editing['id'] === $row['id'] ? ' class="is-current"' : '' ?>>
          <td class="cell-title" data-label="Verze"><a href="versions?id=<?= $row['id'] ?>#form"><?= h($row['version']) ?></a></td>
          <td class="nowrap" data-label="Datum"><?= h(date('d.m.Y', strtotime($row['date']))) ?></td>
          <td data-label="Název"><?= h($row['title']) ?></td>
          <td data-label="Záznamů"><a href="./?version=<?= urlencode($row['version']) ?>"><?= $row['entry_count'] ?></a></td>
          <td data-label="Stav"><?= $row['published'] ? '<span class="tag tag--new">Publikováno</span>' : '<span class="tag tag--draft">Skrytá</span>' ?></td>
          <td class="cell-actions">
            <div class="row-actions">
              <a class="button button--primary button--sm" href="edit?version_id=<?= $row['id'] ?>">+ Záznam</a>
              <a class="button button--ghost button--sm" href="versions?id=<?= $row['id'] ?>#form">Upravit</a>
              <?php if ($row['published'] && $row['entry_count'] > 0): $sentInfo = $mailings[$row['id']] ?? null; ?>
                <form method="post" onsubmit="return confirm(<?= h(json_encode(($sentInfo ? 'Verze ' . $row['version'] . ' už byla rozeslána ' . date('d.m.Y', strtotime($sentInfo['created_at'])) . '. Poslat znovu' : 'Rozeslat verzi ' . $row['version']) . ' ' . $subscriberCount . ' odběratelům?', JSON_UNESCAPED_UNICODE)) ?>)">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><input type="hidden" name="action" value="notify">
                  <button class="button button--<?= $sentInfo ? 'ghost' : 'green' ?> button--sm" type="submit" <?= $subscriberCount ? '' : 'disabled title="Žádní potvrzení odběratelé"' ?>
                    ><?= $sentInfo ? '✉ Rozesláno ' . h(date('d.m.', strtotime($sentInfo['created_at']))) : '✉ Rozeslat' ?></button>
                </form>
              <?php endif; ?>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $row['id'] ?>"><input type="hidden" name="action" value="toggle">
                <button class="button button--ghost button--sm" type="submit"><?= $row['published'] ? 'Skrýt' : 'Publikovat' ?></button>
              </form>
              <?php if ($row['entry_count'] === 0): ?>
                <form method="post" onsubmit="return confirm(<?= h(json_encode('Opravdu smazat verzi ' . $row['version'] . '?', JSON_UNESCAPED_UNICODE)) ?>)">
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
