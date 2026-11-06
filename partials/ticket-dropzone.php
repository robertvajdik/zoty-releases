<?php
/** Attachment dropzone (drag & drop, paste screenshots, browse). Field name: files[] */
?>
<div class="drop" data-drop data-max="<?= TICKET_FILE_MAX ?>" data-count="<?= TICKET_FILE_COUNT ?>" data-ext="<?= h(implode(',', TICKET_FILE_EXT)) ?>">
  <input type="file" name="files[]" multiple hidden data-drop-input accept=".<?= h(implode(',.', TICKET_FILE_EXT)) ?>">
  <p class="drop__text"><?= h(tr('drop.text')) ?></p>
  <button type="button" class="button button--ghost button--sm" data-drop-browse><?= h(tr('drop.browse')) ?></button>
  <p class="drop__limits"><?= h(tr('drop.limits', ['max' => TICKET_FILE_MAX / 1048576, 'count' => TICKET_FILE_COUNT])) ?></p>
  <p class="drop__err" role="alert" hidden></p>
  <ul class="drop__list" data-drop-list></ul>
</div>
