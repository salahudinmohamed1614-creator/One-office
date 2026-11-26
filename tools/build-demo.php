<?php
/**
 * Builds a static HTML demo of the public pages into docs/ (for GitHub Pages).
 * GitHub Pages cannot run PHP, so forms, tracking and the admin panel are disabled in the demo.
 *
 * Usage (with the site running locally, e.g. XAMPP):
 *   php tools/build-demo.php http://localhost/oneoffice
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = rtrim($argv[1] ?? 'http://localhost/oneoffice', '/');
$root = dirname(__DIR__);
$out  = $root . '/docs';
$pages = ['index', 'about', 'services', 'request', 'track', 'contact'];

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function copy_dir(string $from, string $to): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
        $target = $to . '/' . substr($f->getPathname(), strlen($from) + 1);
        $f->isDir() ? @mkdir($target, 0755, true) : copy($f->getPathname(), $target);
    }
}

rrmdir($out);
mkdir($out, 0755, true);
copy_dir($root . '/assets', $out . '/assets');
file_put_contents($out . '/.nojekyll', '');

$banner = '<div class="demo-banner" role="note"><i class="bi bi-info-circle"></i> Demo preview – forms, request tracking and the admin panel work only in the full PHP version.</div>';

foreach ($pages as $p) {
    $html = @file_get_contents("$base/$p.php");
    if ($html === false) {
        fwrite(STDERR, "Could not fetch $base/$p.php – is the site running?\n");
        exit(1);
    }
    // forms cannot post anywhere on GitHub Pages (do this before the link rewrite below)
    $html = preg_replace('~action="(contact|request)\.php"~', 'action="#"', $html);
    // page links: about.php -> about.html (keep ?query / #hash)
    $html = preg_replace('~\b(index|about|services|request|track|contact)\.php\b~', '$1.html', $html);
    $html = preg_replace('~<input type="hidden" name="csrf" value="[^"]*">~', '', $html);
    // demo banner + demo script
    $html = preg_replace('~(<body[^>]*>)~', '$1' . "\n" . $banner, $html, 1);
    $html = str_replace('</body>', '<script src="assets/js/demo.js"></script>' . "\n</body>", $html);
    file_put_contents("$out/$p.html", $html);
    echo "built docs/$p.html\n";
}

// The admin panel (backend) needs PHP + MySQL, so the demo gets an information page instead.
$repo = 'https://github.com/salahudinmohamed1614-creator/One-office';
$adminMain = <<<HTML
<main>
  <section class="page-hero">
    <div class="container">
      <span class="eyebrow">Backend</span>
      <h1>Admin panel</h1>
      <p class="lead">The admin panel is where the ONE OFFICE team manages customer requests, services and the website. It runs on the full PHP + MySQL version, so it is not available in this static demo.</p>
    </div>
  </section>
  <section class="section tight">
    <div class="container">
      <div class="grid cards">
        <article class="card"><div class="card-icon"><i class="bi bi-speedometer2"></i></div><h3>Dashboard</h3><p class="muted">Requests by status, 14-day chart, billed / paid / outstanding fees and the most requested services.</p></article>
        <article class="card"><div class="card-icon"><i class="bi bi-clipboard-check"></i></div><h3>Service requests</h3><p class="muted">Search requests, update the status with a note for the customer, record fees and payments, print, and register walk-in requests.</p></article>
        <article class="card"><div class="card-icon"><i class="bi bi-grid"></i></div><h3>Website content</h3><p class="muted">Manage service categories, services, FAQ and testimonials – changes appear on the website instantly.</p></article>
        <article class="card"><div class="card-icon"><i class="bi bi-inbox"></i></div><h3>Messages</h3><p class="muted">Read, search and reply to messages sent from the contact page.</p></article>
        <article class="card"><div class="card-icon"><i class="bi bi-sliders"></i></div><h3>Site settings</h3><p class="muted">Texts, contact details, WhatsApp number and switching online requests on or off.</p></article>
        <article class="card"><div class="card-icon"><i class="bi bi-shield-lock"></i></div><h3>Security</h3><p class="muted">Password hashing, CSRF protection, login lockout after failed attempts and session timeout.</p></article>
      </div>
      <div class="cta-band" style="margin-top:3rem">
        <div>
          <h2>See the backend code</h2>
          <p>The full source code of the admin panel (PHP) is on GitHub.</p>
        </div>
        <div class="cta-actions">
          <a href="$repo/tree/main/admin" class="btn btn-white" target="_blank" rel="noopener"><i class="bi bi-github"></i> Admin code</a>
          <a href="$repo" class="btn btn-outline-white" target="_blank" rel="noopener">Whole project</a>
        </div>
      </div>
    </div>
  </section>
</main>
HTML;
$home = (string) file_get_contents("$out/index.html");
$adminPage = preg_replace('~<main>.*</main>~s', $adminMain, $home, 1);
$adminPage = preg_replace('~<title>[^<]*</title>~', '<title>Admin panel · ONE OFFICE (demo)</title>', $adminPage, 1);
$adminPage = str_replace(' class="active" aria-current="page"', ' class=""', $adminPage);
file_put_contents("$out/admin.html", $adminPage);
echo "built docs/admin.html\n";

// Every page: the footer "Admin login" link goes to the info page
foreach (glob("$out/*.html") as $file) {
    file_put_contents($file, str_replace('href="admin/"', 'href="admin.html"', (string) file_get_contents($file)));
}

// 404 page -> home
copy("$out/index.html", "$out/404.html");
echo "done\n";
