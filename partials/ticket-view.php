<?php
/**
 * Ticket detail, shared by portal/ticket.php and admin/ticket.php.
 * @var array $ticket  @var array $comments  @var array $files  @var bool $isStaff
 * @var string $listUrl  link back to the list   @var string $fileUrl  prefix for attachment links ("file?id=")
 * @var array $errors   form errors              @var string $draft  unsent comment text
 * @var array $releaseEntries  release-note entries that mention this ticket
 */
$releaseEntries = $releaseEntries ?? [];
$filesByComment = [];
foreach ($files as $f) $filesByComment[(int)($f['comment_id'] ?? 0)][] = $f;
$done = in_array($ticket['status'], TICKET_DONE, true);

$avatar = function (string $name, bool $staff = false): string {
    $parts = preg_split('/[\s@.]+/u', trim($name)) ?: [];
    $ini = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    $hue = $staff ? 'staff' : (string)(crc32($name) % 6);
    return '<span class="avatar avatar--' . $hue . '" aria-hidden="true">' . h($ini ?: '?') . '</span>';
};
$fileList = function (array $list) use ($fileUrl): string {
    if (!$list) return '';
    $out = '<ul class="att">';
    foreach ($list as $f) {
        $url = $fileUrl . (int)$f['id'];
        $out .= '<li class="att__item"><a href="' . h($url) . '" target="_blank" rel="noopener">'
              . (ticket_file_is_image($f)
                  ? '<img src="' . h($url) . '" alt="" loading="lazy">'
                  : '<span class="att__ext">' . h(mb_strtoupper(mb_substr(pathinfo($f['name'], PATHINFO_EXTENSION), 0, 4))) . '</span>')
              . '<span class="att__name">' . h($f['name']) . '<small>' . h(fmt_bytes((int)$f['size'])) . '</small></span></a></li>';
    }
    return $out . '</ul>';
};
$fmt = fn(string $dt): string => fmt_date($dt) . ' ' . h(date('H:i', strtotime($dt)));
$reporter = $ticket['reporter_name'] ?: ($ticket['reporter_email'] ?: tr('ticket.deleted_user'));
?>
<div class="tk">
  <div class="tk__main">
    <nav class="crumbs" aria-label="<?= h(tr('ticket.breadcrumb')) ?>">
      <a href="<?= h($listUrl) ?>"><?= h($isStaff ? 'Požadavky' : tr('portal.my_requests')) ?></a>
      <span aria-hidden="true">/</span>
      <span aria-current="page"><?= h($ticket['key']) ?></span>
    </nav>
    <h1 class="tk__title"><?= h($ticket['summary']) ?></h1>
    <p class="tk__by"><?= $avatar($reporter) ?>
      <span><?= trh('ticket.raised_by', ['name' => $reporter],['date' => '<time datetime="' . h(date('c', strtotime($ticket['created_at']))) . '">' . $fmt($ticket['created_at']) . '</time>']) ?></span>
    </p>

    <?php if ($errors): ?><div class="flash flash--error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

    <section class="tk__section">
      <h2 class="tk__h"><?= h(tr('ticket.description')) ?></h2>
      <div class="md"><?= ticket_render($ticket['description']) ?></div>
      <?= $fileList($filesByComment[0] ?? []) ?>
    </section>

    <?php if ($releaseEntries): ?>
      <section class="tk__section tk__release" aria-labelledby="release-h">
        <h2 class="tk__h" id="release-h"><?= h(tr('ticket.in_release')) ?></h2>
        <ul class="rel-list">
          <?php foreach ($releaseEntries as $re): ?>
            <li>
              <span class="tag tag--<?= h($re['type']) ?>"><?= h(type_label($re['type'])) ?></span>
              <a href="<?= h($isStaff ? 'edit?id=' . $re['id'] : site_url('?version=' . rawurlencode($re['version']) . '#e' . $re['id'])) ?>"><?= h($re['title']) ?></a>
              <span class="rel-list__ver"><?= h(tr('release.version', ['version' => $re['version']])) ?> · <?= fmt_date($re['date']) ?><?= $isStaff && (!$re['published'] || !$re['version_published']) ? ' · <span class="tag tag--draft">nepublikováno</span>' : '' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="tk__section" aria-labelledby="activity-h">
      <h2 class="tk__h" id="activity-h"><?= h(tr('ticket.activity')) ?></h2>
      <ol class="activity">
        <?php foreach ($comments as $c):
            $staff = $c['user_id'] === null;
            $who = $staff ? ($isStaff && $c['staff_name'] !== '' ? $c['staff_name'] : tr('ticket.support_team')) : ($c['user_name'] ?: ($c['user_email'] ?: tr('ticket.deleted_user')));
            if ($c['event'] !== ''): ?>
          <li class="activity__event">
            <span class="activity__dot" aria-hidden="true"></span>
            <span><b><?= h($who) ?></b> <?= h(event_text($c['event'])) ?></span>
            <time datetime="<?= h(date('c', strtotime($c['created_at']))) ?>"><?= $fmt($c['created_at']) ?></time>
          </li>
        <?php else: ?>
          <li class="comment<?= $staff ? ' comment--staff' : '' ?>" id="c<?= (int)$c['id'] ?>">
            <?= $avatar($who, $staff) ?>
            <div class="comment__box">
              <p class="comment__head"><b><?= h($who) ?></b>
                <?php if ($staff): ?><span class="pill"><?= h(tr('ticket.staff_badge')) ?></span><?php endif; ?>
                <time datetime="<?= h(date('c', strtotime($c['created_at']))) ?>"><?= $fmt($c['created_at']) ?></time></p>
              <div class="md"><?= ticket_render((string)$c['body']) ?></div>
              <?= $fileList($filesByComment[(int)$c['id']] ?? []) ?>
            </div>
          </li>
        <?php endif; endforeach; ?>
        <?php if (!$comments): ?><li class="activity__empty"><?= h(tr('ticket.no_activity')) ?></li><?php endif; ?>
      </ol>

      <form class="reply" method="post" enctype="multipart/form-data" data-ticket-form id="reply">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="comment">
        <h3 class="reply__h"><?= h($isStaff ? 'Odpovědět zákazníkovi' : ($done ? tr('ticket.reply_reopen') : tr('ticket.add_comment'))) ?></h3>
        <?php $edName = 'body'; $edId = 'reply-body'; $edValue = $draft; $edRequired = false; $edInvalid = false;
              $edPlaceholder = $isStaff ? 'Napište odpověď…' : tr('ticket.comment_placeholder');
              require __DIR__ . '/ticket-editor.php'; ?>
        <?php require __DIR__ . '/ticket-dropzone.php'; ?>
        <div class="reply__actions">
          <?php if ($isStaff): ?>
            <label class="reply__status">Stav po odeslání
              <select name="status">
                <?php foreach (TICKET_STATUSES as $s): ?>
                  <option value="<?= $s ?>" <?= $s === ($ticket['status'] === 'open' ? 'waiting' : $ticket['status']) ? 'selected' : '' ?>><?= h(status_label($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php endif; ?>
          <button class="button button--primary" type="submit"><?= h($isStaff ? 'Odeslat odpověď' : tr('ticket.send')) ?></button>
        </div>
      </form>
    </section>
  </div>

  <aside class="tk__side">
    <div class="side-card">
      <div class="side-card__status">
        <?= status_lozenge($ticket['status']) ?>
        <?php if ($isStaff): ?>
          <form method="post" class="side-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <label class="visually-hidden" for="set-status">Změnit stav</label>
            <select id="set-status" name="status" onchange="this.form.submit()">
              <?php foreach (TICKET_STATUSES as $s): ?><option value="<?= $s ?>" <?= $s === $ticket['status'] ? 'selected' : '' ?>><?= h(status_label($s)) ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="button button--ghost button--sm" type="submit">Uložit</button></noscript>
          </form>
        <?php else: ?>
          <form method="post" class="side-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="status" value="<?= $done ? 'open' : 'resolved' ?>">
            <button class="button button--ghost button--sm" type="submit"><?= h(tr($done ? 'ticket.reopen' : 'ticket.mark_resolved')) ?></button>
          </form>
        <?php endif; ?>
      </div>
      <dl class="details">
        <?php if ($isStaff): ?>
          <dt>Release notes</dt>
          <dd><a class="button button--ghost button--sm" href="edit?ticket=<?= h(rawurlencode($ticket['key'])) ?>">+ Zapsat do release notes</a></dd>
        <?php endif; ?>
        <dt><?= h(tr('ticket.request_type')) ?></dt>
        <dd><span class="rt rt--<?= h($ticket['type']) ?>" aria-hidden="true"></span><?= h(ticket_type_label($ticket['type'])) ?></dd>
        <dt><?= h(tr('ticket.severity')) ?></dt>
        <dd>
          <?php if ($isStaff): ?>
            <form method="post" class="side-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="severity">
              <select name="severity" onchange="this.form.submit()" aria-label="Závažnost">
                <?php foreach (TICKET_SEVERITIES as $s): ?><option value="<?= $s ?>" <?= $s === $ticket['severity'] ? 'selected' : '' ?>><?= h(severity_label($s)) ?></option><?php endforeach; ?>
              </select>
            </form>
          <?php else: ?>
            <?= severity_badge($ticket['severity']) ?>
          <?php endif; ?>
        </dd>
        <dt><?= h(tr('ticket.product')) ?></dt><dd><?= h(option_label($ticket['product'])) ?></dd>
        <dt><?= h(tr('ticket.product_version')) ?></dt><dd><?= h(option_label($ticket['product_version'])) ?></dd>
        <dt><?= h(tr('ticket.os')) ?></dt><dd><?= h(option_label($ticket['os'])) ?></dd>
        <?php if ($isStaff): ?>
          <dt>Zákazník</dt><dd><a href="customers?id=<?= (int)$ticket['customer_id'] ?>"><?= h($ticket['customer_name']) ?></a></dd>
        <?php endif; ?>
        <dt><?= h(tr('ticket.reporter')) ?></dt>
        <dd><?= h($reporter) ?><?php if ($isStaff && $ticket['reporter_email'] !== ''): ?><br><a href="mailto:<?= h($ticket['reporter_email']) ?>"><?= h($ticket['reporter_email']) ?></a><?php endif; ?></dd>
        <dt><?= h(tr('ticket.created')) ?></dt><dd><?= $fmt($ticket['created_at']) ?></dd>
        <dt><?= h(tr('ticket.updated')) ?></dt><dd><?= $fmt($ticket['updated_at']) ?></dd>
      </dl>
    </div>
  </aside>
</div>
