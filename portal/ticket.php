<?php
require __DIR__ . '/../lib.php';
$me = require_portal_user();

$ticket = get_ticket_by_key((string)($_GET['key'] ?? ''), (int)$me['customer_id']);
if (!$ticket) {
    http_response_code(404);
    $base_path = '../';
    $portal_area = true;
    $pageTitle = tr('portal.not_found');
    require __DIR__ . '/../partials/header.php';
    echo '<main class="container message-page"><div class="card message-card"><h1>' . h(tr('portal.not_found')) . '</h1><p>'
       . h(tr('portal.not_found_text')) . '</p><div class="form-actions"><a class="button button--primary" href="./">'
       . h(tr('portal.my_requests')) . '</a></div></div></main>';
    require __DIR__ . '/../partials/footer.php';
    exit;
}
$selfUrl = 'ticket?key=' . rawurlencode($ticket['key']);
$errors = [];
$draft = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !empty($_SERVER['CONTENT_LENGTH'])) {
    $errors[] = tr('ticket.err.post_size');   // a POST larger than post_max_size arrives empty
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'status') {
        // customers can only resolve their request or reopen it
        $to = (string)($_POST['status'] ?? '');
        $allowed = in_array($ticket['status'], TICKET_DONE, true) ? 'open' : 'resolved';
        if ($to === $allowed && ticket_set_status($ticket, $to, $me)) {
            flash(tr($to === 'open' ? 'portal.reopened' : 'portal.resolved'));
            notify_staff(get_ticket($ticket['id']), 'comment', tr('ticket.event.status', ['from' => status_label($ticket['status'], 'cs'), 'to' => status_label($to, 'cs')], 'cs'));
        }
        header('Location: ' . $selfUrl);
        exit;
    }
    if ($action === 'comment') {
        $draft = str_replace("\r\n", "\n", trim((string)($_POST['body'] ?? '')));
        $files = uploaded_list($_FILES['files'] ?? null);
        if ($draft === '' && !$files) $errors[] = tr('ticket.err.empty_comment');
        if (mb_strlen($draft) > 60000) $errors[] = tr('ticket.err.too_long', ['max' => 60000]);
        $errors = array_merge($errors, validate_uploads($files));
        if (!$errors) {
            $cid = ticket_add_comment($ticket['id'], $draft, $me);
            try {
                store_ticket_files($ticket['id'], $cid, $files);
            } catch (RuntimeException $ex) {
                error_log('Zoty ticket upload: ' . $ex->getMessage());
            }
            // a customer's reply puts the request back in the support queue
            if (in_array($ticket['status'], ['waiting', 'resolved', 'closed'], true)) ticket_set_status($ticket, 'open', $me);
            notify_staff(get_ticket($ticket['id']), 'comment', $draft !== '' ? $draft : '(' . count($files) . ' příloh)');
            flash(tr('portal.comment_added'));
            header('Location: ' . $selfUrl . '#c' . $cid);
            exit;
        }
    }
}

$comments = ticket_comments($ticket['id']);
$files = ticket_files($ticket['id']);
$isStaff = false;
$listUrl = './';
$fileUrl = 'file?id=';

$base_path = '../';
$portal_area = true;
$pageTitle = $ticket['key'] . ' ' . $ticket['summary'];
$headExtra = '<meta name="robots" content="noindex">';
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <?php if ($m = flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
  <?php require __DIR__ . '/../partials/ticket-view.php'; ?>
</main>
<?php require __DIR__ . '/../partials/ticket-scripts.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
