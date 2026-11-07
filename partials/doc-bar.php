<?php
/**
 * Admin navigation of the manuals: one tab per manual, then a bar with the language switch,
 * who can read it, last change and the actions.
 * @var string $slug  @var string $lang  @var ?array $doc  @var string $barMode 'view' | 'edit'
 */
$barMode = $barMode ?? 'view';
$page = $barMode === 'edit' ? 'docs' : 'manual';
$url = fn(string $s, string $l) => $page . '?doc=' . rawurlencode($s) . '&amp;lang=' . rawurlencode($l);
?>
<nav class="tabs doc-tabs" aria-label="Manuály">
  <?php foreach (DOCS as $s => $d):
      $langs = doc_langs($s);
      $l = $s === $slug ? $lang : (in_array('cs', $langs, true) ? 'cs' : $langs[0]); ?>
    <a href="<?= $url($s, $s === $slug ? $lang : $langs[0]) ?>" <?= $s === $slug ? 'aria-current="page"' : '' ?>>
      <?= h(doc_name($s, $l)) ?>
      <?php if (!empty($d['portal'])): ?><svg class="doc-tabs__eye" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="vidí zákazníci" role="img"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($barMode === 'view' && $doc): ?>
  <div class="doc-name">
    <h1 class="doc-name__h"><?= h(doc_name($slug, $lang)) ?></h1>
    <details class="doc-name__edit">
      <summary class="button button--ghost button--sm" title="Přejmenovat jazykovou verzi <?= strtoupper($lang) ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        Přejmenovat
      </summary>
      <form method="post" action="docs?doc=<?= h(rawurlencode($slug)) ?>&amp;lang=<?= h($lang) ?>" class="doc-name__form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="rename">
        <label class="visually-hidden" for="doc-rename">Název manuálu (<?= strtoupper($lang) ?>)</label>
        <input type="text" id="doc-rename" name="title" value="<?= h((string)$doc['title']) ?>" maxlength="<?= DOC_NAME_MAX ?>"
               placeholder="<?= h(DOCS[$slug]['label']) ?>">
        <button class="button button--primary button--sm" type="submit">Uložit název</button>
        <small class="muted">Jen pro verzi <?= strtoupper($lang) ?>. Prázdné = výchozí název.</small>
      </form>
    </details>
  </div>
<?php endif; ?>

<div class="doc-bar">
  <div class="doc-bar__langs" role="group" aria-label="Jazyková verze">
    <?php foreach (LANGS as $l):
        $has = in_array($l, doc_langs($slug), true); ?>
      <?php if ($has): ?>
        <a class="doc-bar__lang" href="<?= $url($slug, $l) ?>" lang="<?= $l ?>" <?= $l === $lang ? 'aria-current="true"' : '' ?>
           title="<?= h(doc_name($slug, $l)) ?>"><?= strtoupper($l) ?></a>
      <?php else: ?>
        <a class="doc-bar__lang doc-bar__lang--add" href="docs?doc=<?= h(rawurlencode($slug)) ?>&amp;lang=<?= $l ?>"
           title="Vytvořit jazykovou verzi <?= strtoupper($l) ?>">+ <?= strtoupper($l) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <span class="doc-bar__who <?= !empty(DOCS[$slug]['portal']) ? 'is-public' : '' ?>">
    <?= !empty(DOCS[$slug]['portal']) ? 'Vidí zákazníci v portálu' : 'Jen pro administrátory' ?>
  </span>

  <?php if ($doc && $doc['updated_at']): ?>
    <span class="doc-bar__meta">Upraveno <?= h(date('j. n. Y H:i', strtotime((string)$doc['updated_at']))) ?><?= $doc['updated_by'] !== '' ? ' · ' . h($doc['updated_by']) : ' · výchozí text' ?></span>
  <?php endif; ?>

  <div class="doc-bar__actions">
    <?php if ($barMode === 'view'): ?>
      <?php if (!empty(DOCS[$slug]['portal'])): ?>
        <a class="button button--ghost button--sm" href="../portal/help?doc=<?= h(rawurlencode($slug)) ?>&amp;lang=<?= h($lang) ?>" target="_blank" rel="noopener">Zobrazit v portálu ↗</a>
      <?php endif; ?>
      <a class="button button--primary button--sm" href="docs?doc=<?= h(rawurlencode($slug)) ?>&amp;lang=<?= h($lang) ?>"><?= $doc ? 'Upravit manuál' : 'Vytvořit' ?></a>
    <?php else: ?>
      <a class="button button--ghost button--sm" href="manual?doc=<?= h(rawurlencode($slug)) ?>&amp;lang=<?= h($lang) ?>">← Zpět na manuál</a>
    <?php endif; ?>
  </div>
</div>
