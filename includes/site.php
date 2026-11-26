<?php
/**
 * Shared header / footer for the public pages.
 */
declare(strict_types=1);

function site_socials(): array
{
    $out = [];
    foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $s) {
        $url = setting($s);
        if ($url !== '' && preg_match('~^https?://~i', $url)) {
            $out[$s] = $url;
        }
    }
    return $out;
}

function site_categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = db()->query('SELECT id, title, subtitle, icon FROM categories WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    }
    return $cats;
}

function site_header(string $title, string $active = '', string $description = ''): void
{
    $siteName = setting('site_name', 'ONE OFFICE');
    $nav = [
        'home'     => ['index.php', 'Home'],
        'about'    => ['about.php', 'About'],
        'services' => ['services.php', 'Services'],
        'track'    => ['track.php', 'Track Request'],
        'contact'  => ['index.php#contact', 'Contact'],
    ];
    $requestsOn = setting('requests_enabled', '1') === '1';
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title === '' ? $siteName . ' — ' . setting('slogan') : $title . ' · ' . $siteName) ?></title>
<meta name="description" content="<?= e($description !== '' ? $description : setting('hero_text')) ?>">
<meta name="theme-color" content="#0f5b61">
<meta property="og:title" content="<?= e($siteName . ' — ' . setting('hero_kicker')) ?>">
<meta property="og:description" content="<?= e($description !== '' ? $description : setting('hero_text')) ?>">
<meta property="og:image" content="assets/img/logo.png">
<link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script>
  document.documentElement.classList.add('js');
  try { var t = localStorage.getItem('oo-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
</script>
</head>
<body class="page-<?= e($active ?: 'other') ?>">
<div class="topstrip">
  <div class="container topstrip-inner">
    <span><i class="bi bi-stars"></i> <?= e(setting('slogan', 'One Office – Many Services')) ?></span>
    <span class="hide-sm">
      <?php if (setting('phone') !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><i class="bi bi-telephone"></i> <?= e(setting('phone')) ?></a><?php endif; ?>
      <?php if (setting('hours') !== ''): ?><span><i class="bi bi-clock"></i> <?= e(setting('hours')) ?></span><?php endif; ?>
    </span>
  </div>
</div>
<header class="nav" id="top">
  <div class="container nav-inner">
    <a href="index.php" class="logo" aria-label="<?= e($siteName) ?> – home">
      <img src="<?= e(asset('img/logo.png')) ?>" alt="<?= e($siteName) ?>" width="886" height="256">
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($requestsOn): ?>
        <a href="request.php" class="btn btn-accent btn-sm nav-cta"><i class="bi bi-send"></i> Request a Service</a>
      <?php endif; ?>
    </nav>
    <div class="nav-actions">
      <button class="icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="bi bi-moon-stars"></i></button>
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks"><i class="bi bi-list"></i></button>
    </div>
  </div>
</header>
<main>
<?php
}

function site_footer(): void
{
    $siteName = setting('site_name', 'ONE OFFICE');
    $socials  = site_socials();
    $wa       = phone_digits(setting('whatsapp'));
    ?>
</main>

<section class="values-band" aria-label="Our mission">
  <div class="container values-inner">
    <div><i class="bi bi-people"></i> Supporting Citizens</div>
    <div><i class="bi bi-briefcase"></i> Enabling Businesses</div>
    <div><i class="bi bi-bank"></i> Strengthening Government Services</div>
    <div><i class="bi bi-bar-chart-line"></i> Driving Development</div>
  </div>
</section>

<footer class="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a href="index.php" class="footer-logo"><img src="<?= e(asset('img/logo.png')) ?>" alt="<?= e($siteName) ?>" loading="lazy"></a>
      <p class="muted"><?= e(setting('footer_text')) ?></p>
      <p class="footer-quote">“One Office, Always at Your Service.”</p>
      <?php if ($socials): ?>
      <div class="socials">
        <?php foreach ($socials as $net => $url): ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e($net) ?>"><i class="bi bi-<?= e($net === 'twitter' ? 'twitter-x' : $net) ?>"></i></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div>
      <h4>Services</h4>
      <?php foreach (site_categories() as $c): ?>
        <a href="services.php#cat-<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Quick links</h4>
      <a href="about.php">About us</a>
      <a href="services.php">All services</a>
      <?php if (setting('requests_enabled', '1') === '1'): ?><a href="request.php">Request a service</a><?php endif; ?>
      <a href="track.php">Track a request</a>
      <a href="index.php#faq">FAQ</a>
    </div>
    <div>
      <h4>Contact us</h4>
      <ul class="footer-contact">
        <?php if (setting('phone') !== ''): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
        <?php if (setting('email') !== ''): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
        <?php if (setting('address') !== ''): ?><li><i class="bi bi-geo-alt"></i><span><?= e(setting('address')) ?></span></li><?php endif; ?>
        <?php if (setting('hours') !== ''): ?><li><i class="bi bi-clock"></i><span><?= e(setting('hours')) ?></span></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="container copyright">© <?= date('Y') ?> <?= e($siteName) ?> · <?= e(setting('slogan', 'One Office – Many Services')) ?></div>
</footer>

<?php if ($wa !== ''): ?>
  <a href="https://wa.me/<?= e($wa) ?>" class="wa-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="bi bi-whatsapp"></i></a>
<?php endif; ?>
<a href="#top" class="to-top" id="toTop" aria-label="Back to top"><i class="bi bi-arrow-up"></i></a>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
<?php
}
