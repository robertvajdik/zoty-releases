<footer class="footer">
  <div class="container footer__inner">
    <img src="<?= $base ?? '' ?>assets/img/zoty-logo.png" width="96" alt="Zoty" class="footer__logo">
    <div class="footer__links">
      <a href="https://zoty.cz/" target="_blank" rel="noopener">zoty.cz</a>
      <a href="https://zoty.cz/kontakt/" target="_blank" rel="noopener"><?= h(tr('footer.contact')) ?></a>
      <?php if (!empty($portal_area)): ?><a href="<?= $base ?? '' ?>./"><?= h(tr('site.product')) ?></a><?php endif; ?>
      <?php if (empty($admin_area)): ?><a href="<?= $base ?? '' ?>admin/"><?= h(tr('footer.admin')) ?></a><?php endif; ?>
    </div>
    <p class="footer__copy"><?= h(tr('footer.rights', ['year' => date('Y')])) ?></p>
  </div>
</footer>
</body>
</html>
