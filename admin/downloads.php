<?php
require __DIR__ . '/../lib.php';
require_admin();

$errors = [];
$editId = (int)($_GET['id'] ?? 0);
$editing = $editId ? get_download($editId) : null;
$langNames = ['' => 'Všechny jazyky', 'cs' => 'Čeština', 'en' => 'English'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'delete' && ($target = get_download((int)($_POST['id'] ?? 0)))) {
        delete_download((int)$target['id']);
        flash('Dokument ' . $target['title'] . ' byl smazán.');
        header('Location: downloads');
        exit;
    }
    if ($action === 'save' && $editing) {
        rename_download((int)$editing['id'], (string)($_POST['title'] ?? ''), (string)($_POST['lang'] ?? ''));
        flash('Dokument byl uložen.');
        header('Location: downloads');
        exit;
    }
    if ($action === 'upload') {
        $errors = add_download((string)($_POST['title'] ?? ''), (string)($_POST['lang'] ?? ''), $_FILES['file'] ?? null);
        if (!$errors) {
            flash('Dokument byl nahrán.');
            header('Location: downloads');
            exit;
        }
    }
}

$docs = fetch_downloads();

$base_path = '../';
$admin_area = true;
$pageTitle = 'Ke stažení';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>

  <div class="admin__head">
    <h1>Ke stažení</h1>
    <a class="button button--ghost" href="../portal/downloads" target="_blank" rel="noopener">Zobrazit v portálu ↗</a>
  </div>

  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

  <?php if ($editing): ?>
  <form class="card" method="post" action="downloads?id=<?= (int)$editing['id'] ?>" id="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <h2 class="card__title">Upravit dokument <?= h($editing['name']) ?></h2>
    <div class="form-grid">
      <div class="field">
        <label for="title">Název *</label>
        <input id="title" name="title" required maxlength="160" value="<?= h($editing['title']) ?>">
      </div>
      <div class="field">
        <label for="lang">Jazyk</label>
        <select id="lang" name="lang">
          <?php foreach ($langNames as $code => $label): ?>
            <option value="<?= $code ?>" <?= $editing['lang'] === (string)$code ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button class="button button--primary" type="submit">Uložit</button>
      <a class="button button--ghost" href="downloads">Zrušit</a>
    </div>
  </form>
  <?php else: ?>
  <form class="card" method="post" action="downloads" enctype="multipart/form-data" id="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <h2 class="card__title">Nahrát dokument PDF</h2>
    <div class="form-grid">
      <div class="field">
        <label for="title">Název</label>
        <input id="title" name="title" maxlength="160" value="<?= h((string)($_POST['title'] ?? '')) ?>" placeholder="např. Instalační manuál Zoty 1.45">
        <small>Prázdné = název souboru.</small>
      </div>
      <div class="field">
        <label for="lang">Jazyk</label>
        <select id="lang" name="lang">
          <?php foreach ($langNames as $code => $label): ?>
            <option value="<?= $code ?>" <?= (string)($_POST['lang'] ?? '') === (string)$code ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small>Zákazník vidí dokumenty ve svém jazyce a ty pro všechny jazyky.</small>
      </div>
      <div class="field full">
        <label for="file">Soubor PDF *</label>
        <input id="file" name="file" type="file" accept="application/pdf,.pdf" required>
        <small>Max. <?= DOWNLOAD_MAX / 1048576 ?> MB (a zároveň limit serveru upload_max_filesize = <?= h((string)ini_get('upload_max_filesize')) ?>).</small>
      </div>
    </div>
    <div class="form-actions">
      <button class="button button--primary" type="submit">Nahrát</button>
    </div>
  </form>
  <?php endif; ?>

  <div class="card table-wrap" style="margin-top:20px">
    <table class="table table--cards">
      <thead>
        <tr><th>Dokument</th><th>Jazyk</th><th>Velikost</th><th>Nahráno</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$docs): ?>
        <tr><td colspan="5" class="cell-empty">Zatím žádné dokumenty. Nahrajte první PDF formulářem výše.</td></tr>
      <?php endif; ?>
      <?php foreach ($docs as $d): ?>
        <tr<?= $editing && (int)$editing['id'] === (int)$d['id'] ? ' class="is-current"' : '' ?>>
          <td class="cell-title" data-label="Dokument"><a href="../portal/document?id=<?= (int)$d['id'] ?>"><?= h($d['title']) ?></a><br><small><?= h($d['name']) ?></small></td>
          <td data-label="Jazyk"><?= h($langNames[$d['lang']] ?? $d['lang']) ?></td>
          <td class="nowrap" data-label="Velikost"><?= h(fmt_bytes((int)$d['size'])) ?></td>
          <td class="nowrap" data-label="Nahráno"><?= h(date('j. n. Y', strtotime((string)$d['created_at']))) ?></td>
          <td class="cell-actions">
            <div class="row-actions">
              <a class="button button--ghost button--sm" href="downloads?id=<?= (int)$d['id'] ?>#form">Upravit</a>
              <form method="post" onsubmit="return confirm(<?= h(json_encode('Opravdu smazat dokument ' . $d['title'] . '?', JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="delete">
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
