<?php
require __DIR__ . '/../lib.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$existing = $id ? get_entry($id) : null;
if ($id && !$existing) {
    http_response_code(404);
    exit('Záznam nenalezen.');
}

$versions = fetch_versions();
$modules  = module_names();

$e = $existing ?? [
    'id' => 0,
    'version_id' => (int)($_GET['version_id'] ?? ($versions[0]['id'] ?? 0)),
    'type' => 'new', 'module' => '', 'title' => '', 'body' => '', 'published' => true,
];
// Fields for an inline "new version" – shown when there is no version yet or "+ Nová verze…" is chosen.
$nv = ['version' => '', 'date' => date('Y-m-d'), 'title' => '', 'summary' => '', 'published' => true];
$choice = $versions ? (string)$e['version_id'] : 'new';
$selectedVersion = $choice !== 'new' ? get_version((int)$choice) : null;
$releaseDate = $selectedVersion['date'] ?? date('Y-m-d');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $choice         = (string)($_POST['version_id'] ?? '');
    $e['type']      = (string)($_POST['type'] ?? '');
    $e['module']    = trim((string)($_POST['module'] ?? ''));
    $e['title']     = trim((string)($_POST['title'] ?? ''));
    $e['body']      = str_replace("\r\n", "\n", trim((string)($_POST['body'] ?? '')));
    $e['published'] = !empty($_POST['published']);

    if ($e['title'] === '') $errors[] = 'Vyplňte název změny.';
    if (mb_strlen($e['title']) > 255) $errors[] = 'Název je příliš dlouhý (max. 255 znaků).';
    if ($e['module'] !== '' && !in_array($e['module'], $modules, true) && $e['module'] !== ($existing['module'] ?? null)) {
        $errors[] = 'Neznámý modul. Moduly spravujete v sekci Moduly.';
    }
    if (!isset(TYPES[$e['type']])) $errors[] = 'Neplatný typ změny.';

    if ($choice === 'new') {
        $nv['version'] = normalize_version_number((string)($_POST['new_version'] ?? ''));
        $nv['date']    = trim((string)($_POST['new_date'] ?? ''));
        $nv['title']   = trim((string)($_POST['new_title'] ?? ''));
        $nv['published'] = true;
        $errors = array_merge($errors, validate_version($nv));
    } elseif (!($selectedVersion = get_version((int)$choice))) {
        $errors[] = 'Vyberte verzi.';
    } else {
        $releaseDate = trim((string)($_POST['release_date'] ?? $selectedVersion['date']));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate) || !strtotime($releaseDate)) {
            $errors[] = 'Neplatné datum vydání.';
        }
    }

    if (!$errors) {
        $e['version_id'] = $choice === 'new' ? save_version($nv) : (int)$choice;
        $dateChanged = $choice !== 'new' && $releaseDate !== $selectedVersion['date'];
        if ($dateChanged) save_version(['date' => $releaseDate] + $selectedVersion);
        save_entry($e);
        flash(($existing ? 'Záznam byl uložen.' : 'Záznam byl vytvořen.') . ($choice === 'new' ? ' Vytvořena verze ' . $nv['version'] . '.' : '')
            . ($dateChanged ? ' Datum vydání verze ' . $selectedVersion['version'] . ' změněno na ' . date('d.m.Y', strtotime($releaseDate)) . '.' : ''));
        header('Location: ' . (isset($_POST['save_new']) ? 'edit?version_id=' . $e['version_id'] : './'));
        exit;
    }
}

$base_path = '../';
$admin_area = true;
$pageTitle = $existing ? 'Upravit záznam' : 'Nový záznam';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <div class="admin__head">
    <h1><?= $existing ? 'Upravit záznam' : 'Nový záznam' ?></h1>
    <a class="button button--ghost" href="./">← Zpět na seznam</a>
  </div>

  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

  <form class="card" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field full">
        <label for="title">Název změny *</label>
        <input id="title" name="title" required maxlength="255" value="<?= h($e['title']) ?>" placeholder="např. Nový přehled úkolů na dashboardu">
      </div>

      <div class="field full">
        <label for="version_id">Verze *</label>
        <select id="version_id" name="version_id" data-toggle-new>
          <?php foreach ($versions as $v): ?>
            <option value="<?= $v['id'] ?>" data-date="<?= h($v['date']) ?>" <?= $choice === (string)$v['id'] ? 'selected' : '' ?>>
              <?= h($v['version']) ?> · <?= h(date('d.m.Y', strtotime($v['date']))) ?><?= $v['title'] !== '' ? ' · ' . h($v['title']) : '' ?><?= $v['published'] ? '' : ' (skrytá)' ?>
            </option>
          <?php endforeach; ?>
          <option value="new" <?= $choice === 'new' ? 'selected' : '' ?>>+ Nová verze…</option>
        </select>
      </div>

      <div class="field" id="release-date-field" <?= $choice === 'new' ? 'hidden' : '' ?>>
        <label for="release_date">Datum vydání verze *</label>
        <input id="release_date" type="date" name="release_date" value="<?= h($releaseDate) ?>" <?= $choice === 'new' ? 'disabled' : 'required' ?>>
        <small>Změna se projeví u celé verze (všech jejích záznamů).</small>
      </div>

      <fieldset class="new-version full" id="new-version" <?= $choice === 'new' ? '' : 'hidden' ?>>
        <legend>Nová verze</legend>
        <div class="form-grid">
          <div class="field">
            <label for="new_version">Číslo verze *</label>
            <input id="new_version" name="new_version" maxlength="50" value="<?= h($nv['version']) ?>" placeholder="např. v1.42.07" autocapitalize="none" autocomplete="off" spellcheck="false" pattern="[vV]?[0-9]+(\.[0-9]+){1,3}" title="Formát v1.42.07">
          </div>
          <div class="field">
            <label for="new_date">Datum vydání *</label>
            <input id="new_date" type="date" name="new_date" value="<?= h($nv['date']) ?>">
          </div>
          <div class="field full">
            <label for="new_title">Název verze</label>
            <input id="new_title" name="new_title" maxlength="255" value="<?= h($nv['title']) ?>" placeholder="volitelné, např. Podzimní aktualizace">
          </div>
        </div>
        <small>Popis a další údaje verze upravíte v sekci <a href="versions">Verze</a>.</small>
      </fieldset>

      <div class="field">
        <label for="type">Typ změny *</label>
        <select id="type" name="type">
          <?php foreach (TYPES as $k => $t): ?><option value="<?= $k ?>" <?= $e['type'] === $k ? 'selected' : '' ?>><?= h($t['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="module">Modul / oblast</label>
        <select id="module" name="module">
          <option value="">— bez modulu —</option>
          <?php if ($e['module'] !== '' && !in_array($e['module'], $modules, true)): ?><option value="<?= h($e['module']) ?>" selected><?= h($e['module']) ?> (není v seznamu)</option><?php endif; ?>
          <?php foreach ($modules as $m): ?><option value="<?= h($m) ?>" <?= $e['module'] === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?>
        </select>
        <small><a href="modules">Spravovat moduly</a></small>
      </div>
      <div class="field full">
        <label for="body">Popis</label>
        <textarea id="body" name="body" placeholder="Volitelný popis změny…"><?= h($e['body']) ?></textarea>
        <div class="upload-row">
          <button class="button button--ghost button--sm" type="button" id="img-btn">🖼 Vložit obrázek</button>
          <input type="file" id="img-file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
          <span id="img-status" class="upload-status" aria-live="polite"></span>
        </div>
        <small>Formátování: prázdný řádek = nový odstavec, <code>- </code> na začátku řádku = odrážka, <code>**tučně**</code>, číslo chyby <code>#20261101</code> se zvýrazní tučně, <code>`kód`</code>, <code>[text](https://odkaz)</code>, obrázek <code>![popis](adresa)</code>. Obrázek můžete do pole i přetáhnout nebo vložit (Ctrl+V). JPG/PNG/GIF/WebP, max. 5 MB.</small>
      </div>
      <div class="field full">
        <label class="check"><input type="checkbox" name="published" value="1" <?= !empty($e['published']) ? 'checked' : '' ?>> Publikovat na veřejném changelogu</label>
      </div>
    </div>
    <div class="form-actions">
      <button class="button button--primary" type="submit">Uložit</button>
      <button class="button button--ghost" type="submit" name="save_new" value="1">Uložit a přidat další</button>
      <a class="button button--ghost" href="./">Zrušit</a>
    </div>
  </form>
</main>
<script>
  (function () {
    var sel = document.getElementById('version_id'), box = document.getElementById('new-version');
    function sync() {
      var isNew = sel.value === 'new';
      box.hidden = !isNew;
      document.getElementById('new_version').required = isNew;
      document.getElementById('new_date').required = isNew;
      if (isNew) document.getElementById('new_version').focus();
      var df = document.getElementById('release-date-field'), di = document.getElementById('release_date');
      df.hidden = isNew; di.disabled = isNew; di.required = !isNew;
      if (!isNew) di.value = sel.options[sel.selectedIndex].getAttribute('data-date') || di.value;
    }
    sel.addEventListener('change', sync);
    document.getElementById('new_version').required = sel.value === 'new';
    document.getElementById('new_date').required = sel.value === 'new';
  })();
</script>
<script>
  (function () {
    var ta = document.getElementById('body'), file = document.getElementById('img-file'),
        status = document.getElementById('img-status'),
        csrf = document.querySelector('input[name="csrf"]').value;

    function insert(text) {
      var a = ta.selectionStart, b = ta.selectionEnd, v = ta.value;
      var before = v.slice(0, a), after = v.slice(b);
      var pre = before && !/\n\n$/.test(before) ? (/\n$/.test(before) ? '\n' : '\n\n') : '';
      var post = after && !/^\n/.test(after) ? '\n\n' : '';
      ta.value = before + pre + text + post + after;
      var pos = (before + pre + text).length;
      ta.setSelectionRange(pos, pos);
      ta.focus();
    }

    function upload(files) {
      files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type); });
      if (!files.length) return;
      var left = files.length;
      status.textContent = 'Nahrávám…';
      files.forEach(function (f) {
        var fd = new FormData();
        fd.append('csrf', csrf);
        fd.append('image', f, f.name || 'obrazek.png');
        fetch('upload', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.error) throw new Error(j.error);
            insert(j.markdown);
            if (--left === 0) status.textContent = 'Obrázek vložen.';
          })
          .catch(function (err) { status.textContent = 'Chyba: ' + (err.message || 'nahrání selhalo'); });
      });
    }

    document.getElementById('img-btn').addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () { upload(file.files); file.value = ''; });
    ta.addEventListener('paste', function (e) {
      var files = e.clipboardData && e.clipboardData.files;
      if (files && files.length) { e.preventDefault(); upload(files); }
    });
    ta.addEventListener('dragover', function (e) { e.preventDefault(); });
    ta.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); upload(e.dataTransfer.files); }
    });
  })();
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
