<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/site.php';
db_or_fail();

$pdo = db();
$categories = site_categories();
$services = $pdo->query('SELECT s.id, s.category_id, s.title, s.icon, s.description, s.items FROM services s
                         JOIN categories c ON c.id = s.category_id
                         WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY s.sort_order, s.id')->fetchAll();
$byCat = [];
foreach ($services as $s) {
    $byCat[(int) $s['category_id']][] = $s;
}
$advisory = [];
$advisoryId = 0;
foreach ($categories as $c) {
    if (stripos($c['title'], 'advisory') !== false) {
        $advisory = $byCat[(int) $c['id']] ?? [];
        $advisoryId = (int) $c['id'];
    }
}
$diasporaId = 0;
foreach ($categories as $c) {
    if (stripos($c['title'], 'diaspora') !== false) {
        $diasporaId = (int) $c['id'];
    }
}
$testimonials = $pdo->query('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$faqs = $pdo->query('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$requestsOn = setting('requests_enabled', '1') === '1';


$steps = [
    ['bi-person', 'Customer', 'Your request', 'You bring us your need and your documents.'],
    ['bi-folder2-open', 'ONE OFFICE', 'Receive & Prepare', 'We receive your request and prepare the documents.'],
    ['bi-bank', 'Government Institution', 'Submit & Follow up', 'We submit to the right institution and follow it up.'],
    ['bi-box-seam', 'ONE OFFICE', 'Collection', 'Once it is ready, we collect it from the institution.'],
    ['bi-truck', 'Customer', 'Delivery', 'We deliver your completed document to you.'],
];
$fees = [
    ['bi-briefcase', 'Service Fee', 'ONE OFFICE’s fee for the service.'],
    ['bi-bank', 'Government Fee', 'The official government fee.'],
    ['bi-truck', 'Delivery Fee', 'Delivering the document to your location.'],
    ['bi-file-earmark-text', 'Document Processing Fees', 'The cost of preparing your documents.'],
];
$roadColors = ['teal', 'orange', 'blue', 'green', 'orange', 'blue', 'green', 'orange', 'teal', 'purple'];

site_header('', 'home');
?>
  <!-- HERO -->
  <section class="hero">
    <div class="hero-bg" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="container hero-grid">
      <div class="hero-copy reveal">
        <span class="pill"><i class="bi bi-patch-check-fill"></i> <?= e(setting('hero_kicker', 'One Stop Government Services Facilitation Center')) ?></span>
        <h1><?= e(setting('hero_title', 'Your Government Services Made Simple')) ?></h1>
        <p class="slogan"><?= e(setting('slogan', 'One Office – Many Services')) ?></p>
        <p class="lead"><?= e(setting('hero_text')) ?></p>
        <div class="hero-cta">
          <?php if ($requestsOn): ?><a href="request.php" class="btn btn-accent">Request a service <i class="bi bi-arrow-right"></i></a><?php endif; ?>
          <a href="services.php" class="btn btn-ghost"><i class="bi bi-grid"></i> View services</a>
        </div>
        <ul class="values-line" aria-label="Our values">
          <li>Simple</li><li>Fast</li><li>Reliable</li><li>People-Centered</li>
        </ul>
      </div>
      <div class="hero-visual reveal" aria-hidden="true">
        <div class="hero-mark"><img src="<?= e(asset('img/logo-mark.png')) ?>" alt=""></div>
        <div class="float-card fc-1"><i class="bi bi-bank"></i><div><b>Government Services</b><small>All in one place</small></div></div>
        <div class="float-card fc-2"><i class="bi bi-upc-scan"></i><div><b>One Tracking Number</b><small>Follow your request</small></div></div>
        <div class="float-card fc-3"><i class="bi bi-headset"></i><div><b>One Customer Service</b><small>One point of contact</small></div></div>
      </div>
    </div>

    <div class="container">
      <form class="track-bar reveal" action="track.php" method="get">
        <div class="track-bar-label"><i class="bi bi-search"></i><div><b>Track your request</b><small>Enter your Tracking Number</small></div></div>
        <input type="text" name="no" placeholder="e.g. OO-261126-AB12" maxlength="20" aria-label="Tracking number" autocomplete="off">
        <button class="btn btn-primary" type="submit">Track <i class="bi bi-arrow-right"></i></button>
      </form>
    </div>
  </section>

  <!-- STATS -->
  <section class="container stats reveal" aria-label="Key figures">
    <div class="stat"><strong data-count="<?= count($services) ?>"><?= count($services) ?></strong><span>+</span><p>Services offered</p></div>
    <div class="stat"><strong data-count="<?= count($categories) ?>"><?= count($categories) ?></strong><p>Service categories</p></div>
    <div class="stat"><strong data-count="5">5</strong><p>Simple steps</p></div>
    <div class="stat"><strong data-count="1">1</strong><p>Tracking Number</p></div>
  </section>

  <!-- ABOUT -->
  <section class="section" id="about">
    <div class="container about-grid">
      <div class="reveal">
        <span class="eyebrow">Introduction</span>
        <h2><?= e(setting('about_title', 'One Office – Many Services')) ?></h2>
        <p class="muted big"><?= nl2br(e(setting('about_text'))) ?></p>
        <?php if (setting('disclaimer') !== ''): ?>
          <div class="note"><i class="bi bi-info-circle-fill"></i><p><?= e(setting('disclaimer')) ?></p></div>
        <?php endif; ?>
        <a href="about.php" class="link-arrow">Learn more about us <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="about-visual reveal" aria-hidden="true">
        <div class="tile t1"><i class="bi bi-people"></i><span>Citizens</span><small>Education, civil, passport</small></div>
        <div class="tile t2"><i class="bi bi-buildings"></i><span>Businesses</span><small>Registration, licenses, tax</small></div>
        <div class="tile t3"><i class="bi bi-globe-americas"></i><span>Diaspora</span><small>Served from anywhere</small></div>
        <div class="tile t4"><i class="bi bi-briefcase"></i><span>Advisory</span><small>Business support</small></div>
      </div>
    </div>
  </section>

  <!-- CATEGORIES -->
  <section class="section alt" id="services">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Our services</span>
        <h2>One office – many services</h2>
        <p class="muted">Choose the category that fits you. For every service we prepare, submit, follow up and collect on your behalf.</p>
      </div>
      <div class="grid cat-grid">
        <?php foreach ($categories as $c): $list = $byCat[(int) $c['id']] ?? []; ?>
          <a class="cat-card reveal" href="services.php#cat-<?= (int) $c['id'] ?>">
            <div class="cat-top">
              <div class="card-icon"><i class="bi <?= e(safe_icon($c['icon'])) ?>"></i></div>
              <span class="count-chip"><?= count($list) ?> services</span>
            </div>
            <h3><?= e($c['title']) ?></h3>
            <?php if ($c['subtitle']): ?><p class="sub"><?= e($c['subtitle']) ?></p><?php endif; ?>
            <ul>
              <?php foreach (array_slice($list, 0, 4) as $s): ?><li><i class="bi bi-check2"></i> <?= e($s['title']) ?></li><?php endforeach; ?>
              <?php if (count($list) > 4): ?><li class="more">+<?= count($list) - 4 ?> more</li><?php endif; ?>
            </ul>
            <span class="link-arrow">View all <i class="bi bi-arrow-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- PROCESS -->
  <section class="section" id="process">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">How it works</span>
        <h2>5 simple steps</h2>
        <p class="muted">You bring it to us – we take care of the rest.</p>
      </div>
      <ol class="steps">
        <?php foreach ($steps as $i => [$icon, $who, $what, $text]): ?>
          <li class="step reveal">
            <span class="step-no">STEP <?= $i + 1 ?></span>
            <div class="step-icon"><i class="bi <?= e($icon) ?>"></i></div>
            <h3><?= e($who) ?></h3>
            <b><?= e($what) ?></b>
            <p class="muted small"><?= e($text) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>

      <div class="benefits reveal">
        <h3>Every customer gets:</h3>
        <ul>
          <li><i class="bi bi-check-circle-fill"></i> One Office</li>
          <li><i class="bi bi-check-circle-fill"></i> One Customer Service</li>
          <li><i class="bi bi-check-circle-fill"></i> One Tracking Number</li>
          <li><i class="bi bi-check-circle-fill"></i> Multiple Government Services</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- ADVISORY ROADMAP -->
  <?php if ($advisory): ?>
  <section class="section alt" id="advisory">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Business Support & Advisory</span>
        <h2>From idea to official launch</h2>
        <p class="muted">Starting a business, organization or new project? We walk with you through every step.</p>
      </div>
      <div class="roadmap">
        <?php foreach ($advisory as $i => $s): ?>
          <article class="road-card rc-<?= e($roadColors[$i % count($roadColors)]) ?> reveal">
            <span class="road-no"><?= $i + 1 ?></span>
            <div class="road-head"><i class="bi <?= e(safe_icon($s['icon'])) ?>"></i><h3><?= e($s['title']) ?></h3></div>
            <?php if ($s['items']): ?>
              <ul><?php foreach (preg_split('/\r?\n/', (string) $s['items']) as $it): if (trim($it) === '') continue; ?><li><?= e(trim($it)) ?></li><?php endforeach; ?></ul>
            <?php else: ?>
              <p class="muted small"><?= e($s['description']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="center reveal"><a href="services.php#cat-<?= $advisoryId ?>" class="btn btn-ghost">More details <i class="bi bi-arrow-right"></i></a></div>
    </div>
  </section>
  <?php endif; ?>

  <!-- FEES -->
  <section class="section" id="fees">
    <div class="container fees-grid">
      <div class="reveal">
        <span class="eyebrow">Our business model</span>
        <h2>Clear and transparent fees</h2>
        <p class="muted">Government fees and ONE OFFICE fees are always kept separate, and every customer receives proof of all payments made.</p>
        <div class="note ok"><i class="bi bi-receipt"></i><p>You receive a receipt and proof for every payment you make.</p></div>
      </div>
      <div class="fee-cards">
        <?php foreach ($fees as [$icon, $name, $text]): ?>
          <div class="fee reveal"><i class="bi <?= e($icon) ?>"></i><div><b><?= e($name) ?></b><small><?= e($text) ?></small></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- DIASPORA CTA -->
  <section class="container">
    <div class="cta-band reveal">
      <div>
        <span class="eyebrow light">Diaspora Services</span>
        <h2>Living abroad? We’ve got you covered.</h2>
        <p>Certificates, court and civil documents, business registration, Somali Visa and document delivery – without having to travel.</p>
      </div>
      <div class="cta-actions">
        <?php if ($requestsOn): ?><a href="request.php?type=diaspora" class="btn btn-white">Request now <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        <a href="services.php#cat-<?= $diasporaId ?>" class="btn btn-outline-white">Diaspora services</a>
      </div>
    </div>
  </section>

  <!-- TESTIMONIALS (only shown when real ones are added in the admin) -->
  <?php if ($testimonials): ?>
  <section class="section" id="testimonials">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Testimonials</span>
        <h2>What our customers say</h2>
      </div>
      <div class="slider reveal" id="slider">
        <?php foreach ($testimonials as $t): ?>
          <figure class="quote">
            <div class="stars" aria-label="<?= (int) $t['rating'] ?> out of 5">
              <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= (int) $t['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?>
            </div>
            <blockquote>“<?= e($t['quote']) ?>”</blockquote>
            <figcaption><div class="avatar sm"><?= e(initials($t['name'])) ?></div><div><b><?= e($t['name']) ?></b><small><?= e($t['company']) ?></small></div></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="slider-nav">
        <button class="icon-btn" data-slide="-1" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
        <button class="icon-btn" data-slide="1" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- FAQ -->
  <?php if ($faqs): ?>
  <section class="section <?= $testimonials ? 'alt' : '' ?>" id="faq">
    <div class="container faq-wrap">
      <div class="section-head reveal">
        <span class="eyebrow">FAQ</span>
        <h2>Frequently asked questions</h2>
      </div>
      <div class="faq reveal">
        <?php foreach ($faqs as $i => $f): ?>
          <details<?= $i === 0 ? ' open' : '' ?>>
            <summary><?= e($f['question']) ?><i class="bi bi-plus-lg"></i></summary>
            <div class="faq-a"><?= nl2br(e($f['answer'])) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- CONTACT CTA -->
  <section class="section alt" id="contact">
    <div class="container">
      <div class="contact-cta reveal">
        <div>
          <span class="eyebrow">Contact</span>
          <h2>Have a question? Talk to us.</h2>
          <p class="muted">Call us, visit our office or send a message – One Office, One Customer Service.</p>
        </div>
        <div class="contact-cta-actions">
          <?php if (setting('phone') !== ''): ?><a class="btn btn-ghost" href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><i class="bi bi-telephone"></i> <?= e(setting('phone')) ?></a><?php endif; ?>
          <a class="btn btn-primary" href="contact.php">Contact us <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </section>
<?php site_footer();
