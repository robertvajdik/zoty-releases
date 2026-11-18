<?php
// A request shared with someone who has no portal account: read-only view through their personal link (?t=<token>).
require __DIR__ . '/../lib.php';

$token = (string)($_GET['t'] ?? '');
$found = ticket_by_share_token($token);
$base_path = '../';
$portal_area = true;
$headExtra = '<meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">';

if (!$found) {
    http_response_code(404);
    $pageTitle = tr('portal.not_found');
    require __DIR__ . '/../partials/header.php';
    echo '<main class="container message-page"><div class="card message-card"><h1>' . h(tr('portal.not_found')) . '</h1><p>'
       . h(tr('ticket.share.link_invalid')) . '</p></div></main>';
    require __DIR__ . '/../partials/footer.php';
    exit;
}
[$ticket, $guest] = $found;

// a colleague with a portal account signs in and gets the full request (with replying)
if ($guest['member']) {
    header('Location: ticket.php?key=' . rawurlencode($ticket['key']));
    exit;
}

$comments = ticket_comments($ticket['id']);
$files = ticket_files($ticket['id']);
$releaseEntries = entries_for_ticket($ticket['key'], true);
$participants = ticket_participants($ticket);
$isStaff = false;
$readOnly = true;
$listUrl = '';
$fileUrl = 'file.php?t=' . $token . '&id=';
$errors = [];
$draft = '';

$pageTitle = $ticket['key'] . ' ' . $ticket['summary'];
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <div class="flash"><?= h($guest['email'] !== ''
      ? tr('ticket.share.guest_note', ['email' => $guest['email'], 'customer' => $ticket['customer_name']])
      : tr('ticket.share.link_note', ['customer' => $ticket['customer_name']])) ?></div>
  <?php require __DIR__ . '/../partials/ticket-view.php'; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
