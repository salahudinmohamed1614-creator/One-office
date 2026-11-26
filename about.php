<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/site.php';
db_or_fail();

$values = [
    ['bi-lightning', 'Simple', 'A clear, easy process – one office, one point of contact.'],
    ['bi-speedometer2', 'Fast', 'We actively follow up your request so it never gets stuck.'],
    ['bi-shield-check', 'Reliable', 'Transparent fees, clear receipts and safe handling of documents.'],
    ['bi-heart', 'People-Centered', 'The customer is at the heart of everything we do.'],
];
$pillars = [
    ['bi-people', 'Supporting Citizens', 'Helping citizens access their documents and government services easily.'],
    ['bi-briefcase', 'Enabling Businesses', 'Letting businesses spend their time on business, not in queues.'],
    ['bi-bank', 'Strengthening Government Services', 'Complete, well-organized applications that make institutions’ work easier.'],
    ['bi-bar-chart-line', 'Driving Development', 'Supporting the economic and social development of Somalia.'],
];
$audiences = [
    ['bi-buildings', 'Companies & Institutions', 'Companies, NGOs, institutions and traders.'],
    ['bi-person', 'The Public', 'Education, civil documents, passport and travel.'],
    ['bi-globe-americas', 'Somalis Abroad', 'Services inside Somalia while you live abroad.'],
];

site_header('About us', 'about', setting('about_text'));
?>
  <section class="page-hero">
    <div class="container">
      <span class="eyebrow">About us</span>
      <h1><?= e(setting('site_name', 'ONE OFFICE')) ?></h1>
      <p class="lead"><?= e(setting('hero_kicker', 'One Stop Government Services Facilitation Center')) ?> · <?= e(setting('slogan')) ?></p>
    </div>
  </section>

  <section class="section">
    <div class="container about-grid">
      <div class="reveal">
        <span class="eyebrow">Introduction</span>
        <h2>Who we are</h2>
        <p class="muted big"><?= nl2br(e(setting('about_text'))) ?></p>
        <?php if (setting('disclaimer') !== ''): ?>
          <div class="note"><i class="bi bi-info-circle-fill"></i><p><?= e(setting('disclaimer')) ?></p></div>
        <?php endif; ?>
      </div>
      <div class="reveal">
        <div class="do-dont">
          <div class="do">
            <h3><i class="bi bi-check-circle-fill"></i> What we do</h3>
            <ul>
              <li>Prepare your documents and applications</li>
              <li>Submit them to the relevant government institution</li>
              <li>Follow up your request until it is completed</li>
              <li>Collect and deliver your documents</li>
            </ul>
          </div>
          <div class="dont">
            <h3><i class="bi bi-x-circle-fill"></i> What we don’t do</h3>
            <ul>
              <li>We do not replace government institutions</li>
              <li>We do not make government decisions</li>
              <li>We never mix government fees with our own fees</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section alt">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Our values</span>
        <h2>Simple · Fast · Reliable · People-Centered</h2>
      </div>
      <div class="grid four">
        <?php foreach ($values as [$icon, $t, $d]): ?>
          <article class="card reveal center-text">
            <div class="card-icon mx"><i class="bi <?= e($icon) ?>"></i></div>
            <h3><?= e($t) ?></h3>
            <p class="muted"><?= e($d) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Our mission</span>
        <h2>The four pillars we stand on</h2>
      </div>
      <div class="grid four">
        <?php foreach ($pillars as $i => [$icon, $t, $d]): ?>
          <article class="pillar p<?= $i + 1 ?> reveal">
            <i class="bi <?= e($icon) ?>"></i>
            <h3><?= e($t) ?></h3>
            <p><?= e($d) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section alt">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Who we serve</span>
        <h2>Anyone who needs a government service</h2>
      </div>
      <div class="grid cards">
        <?php foreach ($audiences as [$icon, $t, $d]): ?>
          <article class="card reveal">
            <div class="card-icon"><i class="bi <?= e($icon) ?>"></i></div>
            <h3><?= e($t) ?></h3>
            <p class="muted"><?= e($d) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="center reveal" style="margin-top:2rem">
        <a href="services.php" class="btn btn-primary">View all services <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
  </section>
<?php site_footer();
