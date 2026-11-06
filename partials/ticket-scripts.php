<?php
/** Texts for assets/tickets.js + the script itself. */
$jsT = [];
foreach (['search', 'no_match', 'remove', 'link_prompt', 'bold_text', 'italic_text', 'strike_text', 'err_type', 'err_size', 'err_count'] as $k) {
    $jsT[$k] = tr('js.' . $k);
}
?>
<script>window.ZOTY_T = <?= json_encode($jsT, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= $base ?? '' ?>assets/tickets.js?v=1" defer></script>
