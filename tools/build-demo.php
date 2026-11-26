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
$pages = ['index', 'about', 'services', 'request', 'track'];

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
    // page links: about.php -> about.html (keep ?query / #hash)
    $html = preg_replace('~\b(index|about|services|request|track)\.php\b~', '$1.html', $html);
    // forms cannot post anywhere on GitHub Pages
    $html = preg_replace('~action="contact\.php"~', 'action="#"', $html);
    $html = preg_replace('~<input type="hidden" name="csrf" value="[^"]*">~', '', $html);
    // demo banner + demo script
    $html = preg_replace('~(<body[^>]*>)~', '$1' . "\n" . $banner, $html, 1);
    $html = str_replace('</body>', '<script src="assets/js/demo.js"></script>' . "\n</body>", $html);
    file_put_contents("$out/$p.html", $html);
    echo "built docs/$p.html\n";
}

// 404 page -> home
copy("$out/index.html", "$out/404.html");
echo "done\n";
