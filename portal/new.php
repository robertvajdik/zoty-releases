<?php
require __DIR__ . '/../lib.php';
$me = require_portal_user();

$type = (string)($_GET['type'] ?? $_POST['type'] ?? '');
if ($type !== '' && !in_array($type, TICKET_TYPES, true)) $type = '';
$opt = ticket_options();
$d = ['type' => $type, 'product' => '', 'product_version' => '', 'os' => '', 'summary' => '', 'description' => '',
      'severity' => $type === 'incident' ? '' : 'low'];
if (count($opt['product']) === 1) $d['product'] = $opt['product'][0];
if ($opt['product_version']) $d['product_version'] = $opt['product_version'][0];   // newest version preselected
$err = [];
$fileErr = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !empty($_SERVER['CONTENT_LENGTH'])) {
    $fileErr[] = tr('ticket.err.post_size');   // a POST larger than post_max_size arrives empty
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $type !== '') {
    csrf_check();
    [$d, $err] = validate_ticket($_POST + ['type' => $type]);
    $files = uploaded_list($_FILES['files'] ?? null);
    $fileErr = validate_uploads($files);
    if (!$err && !$fileErr) {
        $id = create_ticket($me, $d);
        try {
            store_ticket_files($id, null, $files);
        } catch (RuntimeException $ex) {
            error_log('Zoty ticket upload: ' . $ex->getMessage());
            flash(tr('ticket.err.file_upload', ['name' => '']));
        }
        $ticket = get_ticket($id);
        notify_staff($ticket, 'new');
        flash(tr('portal.created', ['key' => $ticket['key']]));
        header('Location: ticket?key=' . rawurlencode($ticket['key']));
        exit;
    }
}

$icons = [
    'incident' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4M12 7v4M12 13.5v.01"/></svg>',
    'question' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17v.01"/></svg>',
    'feature'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.4.3.5.7.5 1.1h6c0-.4.2-.8.5-1.1A6 6 0 0 0 12 3z"/></svg>',
];
$fieldErr = fn(string $k) => isset($err[$k]) ? ' is-invalid' : '';
$errMsg = fn(string $k) => '<p class="tf__err" id="' . $k . '-err">' . h($err[$k] ?? tr('ticket.err.required')) . '</p>';

$base_path = '../';
$portal_area = true;
$pageTitle = $type ? ticket_type_label($type) : tr('portal.new_request');
$headExtra = '<meta name="robots" content="noindex">';
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <nav class="crumbs" aria-label="<?= h(tr('ticket.breadcrumb')) ?>">
    <a href="./"><?= h(tr('portal.my_requests')) ?></a><span aria-hidden="true">/</span>
    <?php if ($type): ?><a href="new"><?= h(tr('portal.new_request')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= h(ticket_type_label($type)) ?></span>
    <?php else: ?><span aria-current="page"><?= h(tr('portal.new_request')) ?></span><?php endif; ?>
  </nav>

  <?php if (!$type): ?>
    <div class="portal__head"><div>
      <h1><?= h(tr('portal.pick_title')) ?></h1>
      <p><?= h(tr('portal.pick_lead', ['customer' => $me['customer_name']])) ?></p>
    </div></div>
    <div class="rtypes tform">
      <?php foreach (TICKET_TYPES as $t): ?>
        <a class="rtype" href="new?type=<?= $t ?>">
          <span class="rt-icon rt-icon--<?= $t ?>"><?= $icons[$t] ?></span>
          <span><span class="rtype__title"><?= h(ticket_type_label($t)) ?></span><span class="rtype__desc"><?= h(tr('ticket.type_desc.' . $t)) ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <form class="tform" method="post" enctype="multipart/form-data" novalidate data-ticket-form>
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="<?= h($type) ?>">

      <div class="rtype rtype--chosen">
        <span class="rt-icon rt-icon--<?= $type ?>"><?= $icons[$type] ?></span>
        <span><span class="rtype__title"><?= h(ticket_type_label($type)) ?></span>
          <span class="rtype__desc"><?= h(tr('ticket.type_desc.' . $type)) ?> <a class="rtype__change" href="new"><?= h(tr('portal.change_type')) ?></a></span></span>
      </div>

      <?php if ($err || $fileErr): ?>
        <div class="flash flash--error" role="alert"><?= h(tr('portal.form_errors')) ?><?= $fileErr ? '<br>' . implode('<br>', array_map('h', $fileErr)) : '' ?></div>
      <?php endif; ?>

      <?php if ($type === 'incident'): ?><p class="tform__note"><?= h(tr('portal.crisis_note')) ?></p><?php endif; ?>
      <p class="tform__req"><?= trh('portal.required_note', [], ['star' => '<span class="req">*</span>']) ?></p>

      <?php foreach (['product' => 'ticket.product', 'product_version' => 'ticket.product_version', 'os' => 'ticket.os'] as $k => $labelKey): ?>
        <div class="tf<?= $fieldErr($k) ?>">
          <label for="<?= $k ?>"><?= h(tr($labelKey)) ?> <span class="req">*</span></label>
          <select id="<?= $k ?>" name="<?= $k ?>" data-combo data-required <?= isset($err[$k]) ? 'aria-invalid="true"' : '' ?> aria-describedby="<?= $k ?>-err">
            <option value=""><?= h(tr('js.search')) ?></option>
            <?php foreach ($opt[$k] as $o): ?><option value="<?= h($o) ?>" <?= $d[$k] === $o ? 'selected' : '' ?>><?= h($o) ?></option><?php endforeach; ?>
            <option value="other" <?= $d[$k] === 'other' ? 'selected' : '' ?>><?= h(tr('ticket.other')) ?></option>
          </select>
          <?= $errMsg($k) ?>
        </div>
      <?php endforeach; ?>

      <div class="tf<?= $fieldErr('summary') ?>">
        <label for="summary"><?= h(tr('ticket.summary')) ?> <span class="req">*</span></label>
        <input type="text" id="summary" name="summary" maxlength="255" value="<?= h($d['summary']) ?>" data-required
               <?= isset($err['summary']) ? 'aria-invalid="true"' : '' ?> aria-describedby="summary-err">
        <?= $errMsg('summary') ?>
      </div>

      <div class="tf<?= $fieldErr('description') ?>">
        <label for="description"><?= h(tr('ticket.description_label.' . $type)) ?> <span class="req">*</span></label>
        <?php $edName = 'description'; $edId = 'description'; $edValue = $d['description']; $edRequired = true;
              $edInvalid = isset($err['description']); $edPlaceholder = tr('ticket.description_placeholder.' . $type);
              require __DIR__ . '/../partials/ticket-editor.php'; ?>
        <?= $errMsg('description') ?>
      </div>

      <div class="tf">
        <span class="tf__label"><?= h(tr('ticket.attachments')) ?></span>
        <?php require __DIR__ . '/../partials/ticket-dropzone.php'; ?>
      </div>

      <fieldset class="tf<?= $fieldErr('severity') ?>">
        <legend><?= h(tr('ticket.severity')) ?> <span class="req">*</span></legend>
        <div class="sev-list">
          <?php foreach (TICKET_SEVERITIES as $s): ?>
            <label class="sev-opt">
              <input type="radio" name="severity" value="<?= $s ?>" <?= $d['severity'] === $s ? 'checked' : '' ?> data-required>
              <span><b><?= severity_badge($s) ?></b><span><?= h(tr('ticket.severity_desc.' . $s)) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?= $errMsg('severity') ?>
      </fieldset>

      <div class="tform__actions">
        <button class="button button--primary" type="submit"><?= h(tr('portal.submit')) ?></button>
        <a class="button button--ghost" href="./"><?= h(tr('portal.cancel')) ?></a>
      </div>
    </form>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/ticket-scripts.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
