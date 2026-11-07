<?php
// Manual editor: Markdown with toolbar, live preview, image upload.
require __DIR__ . '/../lib.php';
require_admin();

$slug = isset(DOCS[$_GET['doc'] ?? '']) ? (string)$_GET['doc'] : 'admin';
$lang = in_array($_GET['lang'] ?? '', LANGS, true) ? (string)$_GET['lang'] : doc_langs($slug)[0];
$selfUrl = 'docs?doc=' . rawurlencode($slug) . '&lang=' . rawurlencode($lang);
$doc = doc_get($slug, $lang);
$who = (string)($_SESSION['zoty_admin'] ?? '');
$errors = [];
$notice = null;
$title = $doc ? (string)$doc['title'] : '';

if ($doc) {
    $body = (string)$doc['body'];
} else {
    // new language version: start from another language of the same manual
    $from = doc_for($slug, $lang);
    $body = $from ? (string)$from['body'] : '# ' . doc_name($slug, $lang) . "\n\n";
    $notice = $from ? 'Verze ' . strtoupper($lang) . ' zatím neexistuje – předvyplněno textem verze ' . strtoupper($from['lang']) . ' k přeložení.' : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'preview') {
        header('Content-Type: application/json; charset=utf-8');
        if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo '{"error":"csrf"}'; exit; }
        echo json_encode(['html' => doc_render(str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')))], JSON_UNESCAPED_UNICODE);
        exit;
    }
    csrf_check();
    if ($action === 'rename') {
        $title = trim(preg_replace('/\s+/u', ' ', (string)($_POST['title'] ?? '')));
        if ($doc && mb_strlen($title) <= DOC_NAME_MAX) {
            doc_rename($slug, $lang, $title);
            flash($title === '' ? 'Obnoven výchozí název manuálu.' : 'Manuál přejmenován na „' . $title . '“.');
        }
        header('Location: manual?doc=' . rawurlencode($slug) . '&lang=' . rawurlencode($lang));
        exit;
    }
    if ($action === 'reset') {
        $seed = doc_seed($slug, $lang);
        if ($seed !== null) {
            doc_save($slug, $lang, $seed, $who);
            flash('Obnoven výchozí text manuálu.');
        }
        header('Location: ' . $selfUrl);
        exit;
    }
    $body = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
    $seen = (string)($_POST['updated_at'] ?? '');
    $title = trim(preg_replace('/\s+/u', ' ', (string)($_POST['title'] ?? '')));
    if (mb_strlen($title) > DOC_NAME_MAX) $errors[] = 'Název manuálu může mít nejvýše ' . DOC_NAME_MAX . ' znaků.';
    if (trim($body) === '') $errors[] = 'Manuál nemůže být prázdný.';
    if (strlen($body) > 2000000) $errors[] = 'Text je příliš dlouhý.';
    if ($doc && $seen !== '' && $seen !== (string)$doc['updated_at']) {
        $errors[] = 'Manuál mezitím uložil někdo jiný (' . ($doc['updated_by'] ?: 'systém') . ', ' . date('d.m.Y H:i', strtotime((string)$doc['updated_at']))
                  . '). Váš text je níže – zkopírujte si změny, obnovte stránku a uložte znovu, nebo uložte ještě jednou a jeho změny přepíšete.';
    }
    if (!$errors) {
        doc_save($slug, $lang, $body, $who, $title);
        flash('Manuál byl uložen.');
        header('Location: ' . (isset($_POST['view']) ? 'manual?doc=' . rawurlencode($slug) . '&lang=' . rawurlencode($lang) : $selfUrl));
        exit;
    }
}

$base_path = '../';
$admin_area = true;
$pageTitle = 'Upravit manuál';
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin admin--wide">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?> <a href="manual?doc=<?= h($slug) ?>&amp;lang=<?= h($lang) ?>">Zobrazit manuál</a></div><?php endif; ?>
  <h1 class="visually-hidden">Upravit manuál: <?= h(doc_name($slug, $lang)) ?> (<?= strtoupper($lang) ?>)</h1>
  <?php $barMode = 'edit'; require __DIR__ . '/../partials/doc-bar.php'; ?>

  <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="flash"><?= h($notice) ?></div><?php endif; ?>

  <form method="post" class="doc-edit" id="doc-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="updated_at" value="<?= h((string)($doc['updated_at'] ?? '')) ?>">
    <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden data-img-input id="doc-img">

    <div class="field doc-edit__name">
      <label for="doc-title">Název manuálu <span class="muted">(<?= strtoupper($lang) ?>)</span></label>
      <input type="text" id="doc-title" name="title" value="<?= h($title) ?>" maxlength="<?= DOC_NAME_MAX ?>"
             placeholder="<?= h(DOCS[$slug]['label']) ?>" aria-describedby="doc-title-hint">
      <small class="admin__hint" id="doc-title-hint">Zobrazuje se v záložkách manuálů<?= $slug === 'portal' ? ' a jako titulek stránky v zákaznickém portálu' : '' ?>. Prázdné = „<?= h(DOCS[$slug]['label']) ?>“.</small>
    </div>

    <div class="doc-edit__cols">
      <div class="doc-edit__src">
        <div class="doc-edit__bar">
          <span class="doc-edit__label">Markdown</span>
          <span class="doc-edit__status" id="doc-status" aria-live="polite"></span>
        </div>
        <?php $edName = 'body'; $edId = 'doc-body'; $edValue = $body; $edRequired = false; $edInvalid = false; $edDoc = true;
              $edPlaceholder = '# Název manuálu';
              require __DIR__ . '/../partials/ticket-editor.php'; ?>
      </div>
      <div class="doc-edit__preview">
        <div class="doc-edit__bar"><span class="doc-edit__label">Náhled</span></div>
        <div class="md-doc doc-edit__out" id="doc-preview"><?= doc_render($body) ?></div>
      </div>
    </div>

    <div class="form-actions doc-edit__actions">
      <button class="button button--primary" type="submit">Uložit</button>
      <button class="button button--ghost" type="submit" name="view" value="1">Uložit a zobrazit</button>
      <a class="button button--ghost" href="manual?doc=<?= h($slug) ?>&amp;lang=<?= h($lang) ?>">Zrušit</a>
    </div>
  </form>

  <?php if (doc_seed($slug, $lang) !== null && $doc): ?>
    <form method="post" class="danger-zone" onsubmit="return confirm('Nahradit text manuálu výchozím textem ze souboru? Vaše úpravy se ztratí.')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset">
      <button class="button button--danger button--sm" type="submit">Obnovit výchozí text</button>
      <small class="muted">Načte text ze souboru <code><?= h(DOCS[$slug]['seed'][$lang]) ?></code>.</small>
    </form>
  <?php endif; ?>
</main>
<script>
  (function () {
    var form = document.getElementById('doc-form'), ta = document.getElementById('doc-body'),
        out = document.getElementById('doc-preview'), status = document.getElementById('doc-status'),
        img = document.getElementById('doc-img'), csrf = form.querySelector('input[name="csrf"]').value,
        dirty = false, timer = null, seq = 0;

    // live preview (server renders exactly like the published page)
    function preview() {
      var my = ++seq, fd = new FormData();
      fd.append('action', 'preview'); fd.append('csrf', csrf); fd.append('body', ta.value);
      fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (my === seq && j.html !== undefined) out.innerHTML = j.html; })
        .catch(function () {});
    }
    document.getElementById('doc-title').addEventListener('input', function () { dirty = true; status.textContent = 'Neuloženo'; });
    ta.addEventListener('input', function () {
      dirty = true;
      status.textContent = 'Neuloženo';
      clearTimeout(timer);
      timer = setTimeout(preview, 450);
    });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    form.addEventListener('submit', function () { dirty = false; });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); dirty = false; form.submit(); }
    });

    // images: toolbar button, paste, drag & drop -> admin/upload -> docs/manual/img -> ![alt](img/…)
    function insert(text) {
      var a = ta.selectionStart, b = ta.selectionEnd, v = ta.value;
      var before = v.slice(0, a), after = v.slice(b);
      var pre = before && !/\n\n$/.test(before) ? (/\n$/.test(before) ? '\n' : '\n\n') : '';
      ta.value = before + pre + text + '\n\n' + after;
      var pos = (before + pre + text).length + 2;
      ta.focus(); ta.setSelectionRange(pos, pos);
      ta.dispatchEvent(new Event('input'));
    }
    function upload(files) {
      files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type); });
      if (!files.length) return;
      status.textContent = 'Nahrávám obrázek…';
      files.forEach(function (f) {
        var fd = new FormData();
        fd.append('csrf', csrf);
        fd.append('image', f, f.name || 'obrazek.png');
        fetch('upload?for=doc', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) { if (j.error) throw new Error(j.error); insert(j.markdown); status.textContent = 'Obrázek vložen – neuloženo'; })
          .catch(function (err) { status.textContent = 'Chyba: ' + (err.message || 'nahrání selhalo'); });
      });
    }
    img.addEventListener('change', function () { upload(img.files); img.value = ''; });
    ta.addEventListener('paste', function (e) {
      var f = e.clipboardData && e.clipboardData.files;
      if (f && f.length) { e.preventDefault(); upload(f); }
    });
    ta.addEventListener('dragover', function (e) { e.preventDefault(); });
    ta.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); upload(e.dataTransfer.files); }
    });
  })();
</script>
<?php require __DIR__ . '/../partials/ticket-scripts.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
