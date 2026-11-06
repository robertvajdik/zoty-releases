<?php
/**
 * Markdown editor with a Jira-like toolbar.
 * @var string $edName  textarea name      @var string $edId  textarea id
 * @var string $edValue current text      @var bool $edRequired
 * @var string $edPlaceholder             @var bool $edInvalid
 */
$ic = fn(string $d) => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
?>
<div class="rte<?= !empty($edInvalid) ? ' is-invalid' : '' ?>" data-rte>
  <div class="rte__bar" role="toolbar" aria-label="<?= h(tr('rte.toolbar')) ?>" aria-controls="<?= h($edId) ?>">
    <div class="rte__menu">
      <button type="button" class="rte__btn rte__btn--text" data-menu aria-haspopup="true" aria-expanded="false" title="<?= h(tr('rte.style')) ?>">Tt<span class="rte__caret" aria-hidden="true"></span></button>
      <div class="rte__pop" hidden role="menu">
        <button type="button" role="menuitem" data-cmd="p"><?= h(tr('rte.normal')) ?></button>
        <button type="button" role="menuitem" data-cmd="h1" class="rte__h1"><?= h(tr('rte.h1')) ?></button>
        <button type="button" role="menuitem" data-cmd="h2" class="rte__h2"><?= h(tr('rte.h2')) ?></button>
        <button type="button" role="menuitem" data-cmd="h3" class="rte__h3"><?= h(tr('rte.h3')) ?></button>
      </div>
    </div>
    <span class="rte__sep" aria-hidden="true"></span>
    <button type="button" class="rte__btn" data-cmd="bold" title="<?= h(tr('rte.bold')) ?> (Ctrl+B)" aria-label="<?= h(tr('rte.bold')) ?>"><?= $ic('<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="italic" title="<?= h(tr('rte.italic')) ?> (Ctrl+I)" aria-label="<?= h(tr('rte.italic')) ?>"><?= $ic('<path d="M14 5h-4M14 19h-4M14 5l-4 14"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="strike" title="<?= h(tr('rte.strike')) ?>" aria-label="<?= h(tr('rte.strike')) ?>"><?= $ic('<path d="M5 12h14M16 7a4 3 0 0 0-4-2c-2.5 0-4 1.3-4 3s1.5 2.4 4 3M8 17a4 3 0 0 0 4 2c2.5 0 4-1.3 4-3"/>') ?></button>
    <span class="rte__sep" aria-hidden="true"></span>
    <button type="button" class="rte__btn" data-cmd="ul" title="<?= h(tr('rte.ul')) ?>" aria-label="<?= h(tr('rte.ul')) ?>"><?= $ic('<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="ol" title="<?= h(tr('rte.ol')) ?>" aria-label="<?= h(tr('rte.ol')) ?>"><?= $ic('<path d="M10 6h10M10 12h10M10 18h10M4 4.5h1.5V9M3.5 9h3M3.5 14.5c.4-.6 1-1 1.7-1 .8 0 1.3.5 1.3 1.2 0 1.2-3 2.1-3 3.8h3"/>') ?></button>
    <span class="rte__sep" aria-hidden="true"></span>
    <button type="button" class="rte__btn" data-cmd="link" title="<?= h(tr('rte.link')) ?> (Ctrl+K)" aria-label="<?= h(tr('rte.link')) ?>"><?= $ic('<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="attach" title="<?= h(tr('rte.attach')) ?>" aria-label="<?= h(tr('rte.attach')) ?>"><?= $ic('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="code" title="<?= h(tr('rte.code')) ?>" aria-label="<?= h(tr('rte.code')) ?>"><?= $ic('<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="codeblock" title="<?= h(tr('rte.codeblock')) ?>" aria-label="<?= h(tr('rte.codeblock')) ?>"><?= $ic('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m9 10-2 2 2 2M15 10l2 2-2 2"/>') ?></button>
    <button type="button" class="rte__btn" data-cmd="quote" title="<?= h(tr('rte.quote')) ?>" aria-label="<?= h(tr('rte.quote')) ?>"><?= $ic('<path d="M5 7v10M10 8h9M10 12h9M10 16h6"/>') ?></button>
  </div>
  <textarea id="<?= h($edId) ?>" name="<?= h($edName) ?>" rows="6" placeholder="<?= h($edPlaceholder ?? '') ?>"
    <?= !empty($edRequired) ? 'data-required' : '' ?> <?= !empty($edInvalid) ? 'aria-invalid="true"' : '' ?>><?= h($edValue ?? '') ?></textarea>
  <p class="rte__hint"><?= h(tr('rte.hint')) ?></p>
</div>
