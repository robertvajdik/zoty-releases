<?php
require __DIR__ . '/../lib.php';
require_admin();

$ticket = get_ticket_by_key((string)($_GET['key'] ?? ''));
if (!$ticket) {
    http_response_code(404);
    exit('Požadavek nenalezen.');
}
$selfUrl = 'ticket?key=' . rawurlencode($ticket['key']);
$staff = (string)($_SESSION['zoty_admin'] ?? 'Podpora');
$errors = [];
$draft = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !empty($_SERVER['CONTENT_LENGTH'])) {
    $errors[] = 'Odeslaná data jsou příliš velká (limit serveru post_max_size).';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'status') {
        $to = (string)($_POST['status'] ?? '');
        if (ticket_set_status($ticket, $to, null, $staff)) {
            notify_customer(get_ticket($ticket['id']), '', $to);
            flash('Stav změněn na ' . status_label($to) . '.');
        }
        header('Location: ' . $selfUrl);
        exit;
    }
    if ($action === 'severity') {
        if (ticket_set_severity($ticket, (string)($_POST['severity'] ?? ''), $staff)) flash('Závažnost byla změněna.');
        header('Location: ' . $selfUrl);
        exit;
    }
    if ($action === 'delete') {
        delete_ticket($ticket['id']);
        flash('Požadavek ' . $ticket['key'] . ' byl smazán.');
        header('Location: tickets');
        exit;
    }
    if ($action === 'comment') {
        $draft = str_replace("\r\n", "\n", trim((string)($_POST['body'] ?? '')));
        $files = uploaded_list($_FILES['files'] ?? null);
        $to = (string)($_POST['status'] ?? $ticket['status']);
        if ($draft === '' && !$files) $errors[] = 'Napište odpověď nebo přiložte soubor.';
        $errors = array_merge($errors, validate_uploads($files));
        if (!$errors) {
            $cid = ticket_add_comment($ticket['id'], $draft, null, $staff);
            try {
                store_ticket_files($ticket['id'], $cid, $files);
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
            $changed = ticket_set_status($ticket, $to, null, $staff);
            notify_customer(get_ticket($ticket['id']), $draft !== '' ? $draft : '(příloha)', $changed ? $to : '');
            flash('Odpověď byla odeslána' . ($changed ? ', stav: ' . status_label($to) : '') . '.');
            header('Location: ' . $selfUrl . '#c' . $cid);
            exit;
        }
    }
}

$comments = ticket_comments($ticket['id']);
$files = ticket_files($ticket['id']);
$releaseEntries = entries_for_ticket($ticket['key'], false);
$isStaff = true;
$listUrl = 'tickets';
$fileUrl = '../portal/file?id=';

$base_path = '../';
$admin_area = true;
$pageTitle = $ticket['key'];
require __DIR__ . '/../partials/header.php';
?>
<main class="container admin">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <?php require __DIR__ . '/../partials/ticket-view.php'; ?>
  <form method="post" class="danger-zone" onsubmit="return confirm(<?= h(json_encode('Smazat požadavek ' . $ticket['key'] . ' včetně komentářů a příloh? Nelze vrátit.', JSON_UNESCAPED_UNICODE)) ?>)">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="button button--danger button--sm" type="submit">Smazat požadavek</button>
  </form>
</main>
<?php require __DIR__ . '/../partials/ticket-scripts.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
