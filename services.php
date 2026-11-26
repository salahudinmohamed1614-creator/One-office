<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/site.php';
db_or_fail();

$categories = site_categories();
$rows = db()->query('SELECT s.* FROM services s JOIN categories c ON c.id = s.category_id
                     WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY s.sort_order, s.id')->fetchAll();
$byCat = [];
foreach ($rows as $s) {
    $byCat[(int) $s['category_id']][] = $s;
}
$full = db()->query('SELECT id, description FROM categories WHERE is_active = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
$requestsOn = setting('requests_enabled', '1') === '1';

site_header('Services', 'services', 'All ONE OFFICE services: companies, education, civil documents, passport & travel, diaspora services and business advisory.');
?>
  <section class="page-hero">
    <div class="container">
      <span class="eyebrow">Services</span>
      <h1>All our services</h1>
      <p class="lead"><?= count($rows) ?> services in <?= count($categories) ?> categories – one office, one tracking number.</p>
      <div class="svc-search">
        <i class="bi bi-search"></i>
        <input type="search" id="svcSearch" placeholder="Search a service… (e.g. certificate, tax, visa)" aria-label="Search services">
      </div>
      <nav class="cat-pills" aria-label="Categories">
        <?php foreach ($categories as $c): ?>
          <a href="#cat-<?= (int) $c['id'] ?>"><i class="bi <?= e(safe_icon($c['icon'])) ?>"></i> <?= e($c['title']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </section>

  <div class="container svc-page">
    <p class="empty-search" id="svcEmpty" hidden><i class="bi bi-search"></i> No service found. Try another word or <a href="index.php#contact">contact us</a>.</p>
    <?php foreach ($categories as $c): $list = $byCat[(int) $c['id']] ?? []; if (!$list) continue; ?>
      <section class="svc-cat" id="cat-<?= (int) $c['id'] ?>" data-cat>
        <header class="svc-cat-head reveal">
          <div class="card-icon"><i class="bi <?= e(safe_icon($c['icon'])) ?>"></i></div>
          <div>
            <h2><?= e($c['title']) ?></h2>
            <?php if ($c['subtitle']): ?><p class="sub"><?= e($c['subtitle']) ?></p><?php endif; ?>
            <?php if (!empty($full[$c['id']])): ?><p class="muted"><?= e($full[$c['id']]) ?></p><?php endif; ?>
          </div>
        </header>
        <div class="svc-grid">
          <?php foreach ($list as $s): ?>
            <article class="svc reveal" data-svc data-text="<?= e(mb_strtolower($s['title'] . ' ' . $s['description'] . ' ' . $s['items'] . ' ' . $c['title'])) ?>">
              <div class="svc-head"><i class="bi <?= e(safe_icon($s['icon'])) ?>"></i><h3><?= e($s['title']) ?></h3></div>
              <?php if ($s['description']): ?><p class="muted small"><?= e($s['description']) ?></p><?php endif; ?>
              <?php if ($s['items']): ?>
                <ul class="svc-items"><?php foreach (preg_split('/\r?\n/', (string) $s['items']) as $it): if (trim($it) === '') continue; ?><li><i class="bi bi-check2"></i> <?= e(trim($it)) ?></li><?php endforeach; ?></ul>
              <?php endif; ?>
              <?php if ($requestsOn): ?>
                <a class="svc-btn" href="request.php?service=<?= (int) $s['id'] ?>">Request this service <i class="bi bi-arrow-right"></i></a>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="cta-band reveal">
      <div>
        <h2>Can’t find your service?</h2>
        <p>If it isn’t on the list, contact us – we’ll tell you how we can help.</p>
      </div>
      <div class="cta-actions">
        <?php if ($requestsOn): ?><a href="request.php" class="btn btn-white">Request a service <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        <a href="index.php#contact" class="btn btn-outline-white">Contact us</a>
      </div>
    </div>
  </div>
<?php site_footer();
