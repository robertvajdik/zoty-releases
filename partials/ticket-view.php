<?php
/**
 * Ticket detail, shared by portal/ticket.php and admin/ticket.php.
 * @var array $ticket  @var array $comments  @var array $files  @var bool $isStaff
 * @var string $listUrl  link back to the list   @var string $fileUrl  prefix for attachment links ("file?id=")
 * @var array $errors   form errors              @var string $draft  unsent comment text
 * @var array $releaseEntries  release-note entries that mention this ticket
 * @var bool $readOnly  guest view (portal/shared.php)
 */
$releaseEntries = $releaseEntries ?? [];
$participants = $participants ?? [];          // people the ticket is shared with (ticket_participants())
$shareCandidates = $shareCandidates ?? [];    // portal only: colleagues suggested in the share field
$shareUrl = $shareUrl ?? '';                  // portal only: link to copy
$shareError = $shareError ?? '';  $shareEmail = $shareEmail ?? '';
$readOnly = $readOnly ?? false;               // guest through a share link: no replying, no status, no sharing
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
  <div class="tk__head">
    <nav class="crumbs" aria-label="<?= h(tr('ticket.breadcrumb')) ?>">
      <?php if ($listUrl !== ''): ?>
        <a href="<?= h($listUrl) ?>"><?= h($isStaff ? 'Požadavky' : tr('portal.my_requests')) ?></a>
        <span aria-hidden="true">/</span>
      <?php endif; ?>
      <span aria-current="page"><?= h($ticket['key']) ?></span>
    </nav>
    <h1 class="tk__title"><?= h($ticket['summary']) ?></h1>
    <p class="tk__by"><?= $avatar($reporter) ?>
      <span><?= trh('ticket.raised_by', ['name' => $reporter],['date' => '<time datetime="' . h(date('c', strtotime($ticket['created_at']))) . '">' . $fmt($ticket['created_at']) . '</time>']) ?></span>
    </p>
  </div>

  <div class="tk__main">
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

      <?php if (!$readOnly): ?>
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
      <?php endif; ?>
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
        <?php elseif (!$readOnly): ?>
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
        <?php if ($isStaff): ?><dt>Stáří</dt><dd><?= ticket_age_badge($ticket) ?></dd><?php endif; ?>
        <dt><?= h(tr('ticket.updated')) ?></dt><dd><?= $fmt($ticket['updated_at']) ?></dd>
      </dl>
    </div>

    <?php if (!$readOnly): ?>
    <section class="side-card share" aria-labelledby="share-h">
      <h2 class="share__h" id="share-h"><?= h($isStaff ? 'Sdíleno s' : tr('ticket.share.title')) ?></h2>
      <?php if ($participants): ?>
        <ul class="share__list">
          <?php foreach ($participants as $p):
              $pName = $p['name'] ?: $p['email'];
              $pKind = $p['member'] ? ($isStaff ? 'účet v portálu' : tr('ticket.share.member'))
                     : ($p['blocked'] ? ($isStaff ? 'deaktivován' : tr('ticket.share.blocked')) : ($isStaff ? 'host – odkaz bez přihlášení' : tr('ticket.share.guest'))); ?>
            <li class="share__item<?= $p['blocked'] ? ' is-inactive' : '' ?>">
              <?= $avatar($pName) ?>
              <span class="share__who"><?= h($pName) ?>
                <small><?= $p['name'] !== '' ? h($p['email']) . ' · ' : '' ?><?= h($pKind) ?></small></span>
              <?php if (!$isStaff): ?>
                <form method="post" class="side-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unshare">
                  <input type="hidden" name="participant" value="<?= (int)$p['id'] ?>">
                  <button class="share__remove" type="submit" title="<?= h(tr('ticket.share.remove', ['name' => $pName])) ?>"
                          aria-label="<?= h(tr('ticket.share.remove', ['name' => $pName])) ?>">×</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="share__none"><?= h($isStaff ? 'Zákazník požadavek s nikým nesdílí.' : tr('ticket.share.none')) ?></p>
      <?php endif; ?>
      <?php if (!$isStaff): ?>
        <form method="post" class="share__form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="share">
          <label class="visually-hidden" for="share-email"><?= h(tr('ticket.share.with')) ?></label>
          <input type="email" id="share-email" name="email" required maxlength="190" autocomplete="off" value="<?= h($shareEmail) ?>"
                 placeholder="<?= h(tr('ticket.share.placeholder')) ?>"<?= $shareCandidates ? ' list="share-colleagues"' : '' ?>
                 <?= $shareError !== '' ? 'aria-invalid="true" aria-describedby="share-err"' : '' ?>>
          <?php if ($shareCandidates): ?>
            <datalist id="share-colleagues">
              <?php foreach ($shareCandidates as $u): ?><option value="<?= h($u['email']) ?>"><?= h($u['name']) ?></option><?php endforeach; ?>
            </datalist>
          <?php endif; ?>
          <button class="button button--primary button--sm" type="submit"><?= h(tr('ticket.share.button')) ?></button>
        </form>
        <?php if ($shareError !== ''): ?><p class="share__err" id="share-err" role="alert"><?= h($shareError) ?></p><?php endif; ?>
        <p class="share__hint"><?= h(tr('ticket.share.hint')) ?></p>
        <?php if ($shareUrl !== ''): ?>
          <div class="share__link">
            <button class="button button--ghost button--sm share__copy" type="button" data-copy="<?= h($shareUrl) ?>"><?= h(tr('ticket.share.copy_link')) ?></button>
            <form method="post" class="side-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reset_link">
              <button class="share__reset" type="submit" title="<?= h(tr('ticket.share.reset_hint')) ?>"><?= h(tr('ticket.share.reset_link')) ?></button>
            </form>
          </div>
          <p class="share__hint"><?= h(tr('ticket.share.copy_hint')) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>
