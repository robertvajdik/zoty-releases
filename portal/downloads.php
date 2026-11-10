<?php
// Download center – only for signed-in customers (admins may preview it).
require __DIR__ . '/../lib.php';
if (!is_admin()) require_portal_user();

const DOCKER_HUB_URL = 'https://hub.docker.com/r/idsacz/zoty';

$base_path = '../';
$portal_area = true;
$pageTitle = tr('downloads.title');
$headExtra = '<meta name="robots" content="noindex">';
require __DIR__ . '/../partials/header.php';
?>
<main class="container portal">
  <div class="portal__head">
    <div>
      <h1><?= h(tr('downloads.title')) ?></h1>
      <p><?= h(tr('downloads.lead')) ?></p>
    </div>
  </div>

  <?php if ($docs = fetch_downloads(is_admin() && !portal_user() ? null : lang())): ?>
  <section class="card download">
    <div class="download__head">
      <h2><?= h(tr('downloads.docs.title')) ?></h2>
    </div>
    <ul class="download__files">
      <?php foreach ($docs as $d): ?>
        <li>
          <a href="document?id=<?= (int)$d['id'] ?>"><?= h($d['title']) ?></a>
          <span class="download__meta">PDF · <?= h(fmt_bytes((int)$d['size'])) ?> · <?= fmt_date(substr((string)$d['created_at'], 0, 10)) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="card download">
    <div class="download__head">
      <h2><?= h(tr('downloads.docker.title')) ?></h2>
      <a class="button button--primary button--sm" href="<?= h(DOCKER_HUB_URL) ?>" target="_blank" rel="noopener"><?= h(tr('downloads.docker.hub')) ?> ↗</a>
    </div>
    <p><?= h(tr('downloads.docker.text')) ?></p>
    <pre class="download__cmd"><code>docker pull idsacz/zoty:tagname</code></pre>
    <p class="download__note"><?= h(tr('downloads.docker.tags')) ?></p>
  </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
