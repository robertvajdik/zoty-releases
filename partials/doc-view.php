<?php
/**
 * A rendered manual with a table of contents.
 * @var array $doc  row from changelog_docs   @var string $docTools  extra HTML for the toolbar (buttons)
 * @var string $tocLabel   @var string $updatedLabel
 */
$docHtml = doc_render($doc['body'], $toc);
?>
<div class="doc">
  <?php if (count($toc) > 1): ?>
    <nav class="doc__toc" aria-label="<?= h($tocLabel) ?>">
      <p class="doc__toc-h"><?= h($tocLabel) ?></p>
      <ol>
        <?php foreach ($toc as $t): ?><li><a href="#<?= h($t['id']) ?>"><?= h($t['text']) ?></a></li><?php endforeach; ?>
      </ol>
    </nav>
  <?php endif; ?>
  <article class="doc__body md-doc">
    <?php if (!empty($docTools)): ?><div class="doc__tools"><?= $docTools ?></div><?php endif; ?>
    <?= $docHtml ?>
    <p class="doc__updated"><?= h($updatedLabel) ?> <?= fmt_date((string)$doc['updated_at']) ?></p>
  </article>
</div>
<script>
  // highlight the chapter in view
  (function () {
    var links = document.querySelectorAll('.doc__toc a');
    if (!links.length || !('IntersectionObserver' in window)) return;
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting || !map[e.target.id]) return;
        links.forEach(function (a) { a.removeAttribute('aria-current'); });
        map[e.target.id].setAttribute('aria-current', 'true');
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    document.querySelectorAll('.doc__body h2[id]').forEach(function (h) { io.observe(h); });
  })();
</script>
<?php if (str_contains($docHtml, 'doc-fig')): ?>
<link rel="stylesheet" href="<?= h($base_path ?? '') ?>assets/vendor/glightbox/glightbox.min.css?v=3.3.0">
<script src="<?= h($base_path ?? '') ?>assets/vendor/glightbox/glightbox.min.js?v=3.3.0"></script>
<script>
  // picture viewer (GLightbox): click a picture of the manual -> full size with zoom, swipe, arrows / Esc, caption
  (function () {
    if (!window.GLightbox) return;   // without the library the link still opens the picture in a new tab
    var links = Array.prototype.slice.call(document.querySelectorAll('.doc__body .doc-fig > a'));
    if (!links.length) return;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    links.forEach(function (a) {
      var cap = a.parentNode.querySelector('figcaption');
      a.setAttribute('data-type', 'image');
      a.setAttribute('data-gallery', 'manual');
      if (cap) a.setAttribute('data-description', esc(cap.textContent.trim()));   // shown as text, never as HTML
    });
    var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    GLightbox({
      selector: '.doc__body .doc-fig > a',
      touchNavigation: true, loop: false, zoomable: true, draggable: true, descPosition: 'bottom',
      openEffect: still ? 'none' : 'zoom', closeEffect: still ? 'none' : 'fade', slideEffect: still ? 'none' : 'slide'
    });
  })();
</script>
<?php endif; ?>
