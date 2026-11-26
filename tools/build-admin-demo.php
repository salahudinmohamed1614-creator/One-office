<?php
/**
 * Builds a static HTML preview of the ADMIN PANEL into docs/admin/ (for GitHub Pages).
 *
 * It uses a separate demo database filled with made-up sample data, so no real customer
 * data ever ends up on GitHub. Steps:
 *   1. Start a local PHP server that uses the demo database:
 *        set DB_NAME=one_office_demo  (PowerShell: $env:DB_NAME='one_office_demo')
 *        php -S 127.0.0.1:8089
 *   2. In another terminal (same DB_NAME):
 *        php tools/build-admin-demo.php http://127.0.0.1:8089
 *
 * WARNING: the demo database is emptied and refilled every time this script runs.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=one_office_demo');
}
require dirname(__DIR__) . '/config.php';

if (!preg_match('/demo/i', DB_NAME)) {
    fwrite(STDERR, "Refusing to run: DB_NAME must be a demo database (its name must contain 'demo'), got '" . DB_NAME . "'.\n");
    exit(1);
}

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8089', '/');
$root = dirname(__DIR__);
$out  = $root . '/docs/admin';
date_default_timezone_set('Africa/Mogadishu');

/* ------------------------------------------------------------------ 1. demo database */
function run_sql(PDO $pdo, string $file): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dbName = str_replace('`', '', DB_NAME);
$server->exec("DROP DATABASE IF EXISTS `$dbName`");
$server->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
run_sql($pdo, "$root/database/schema.sql");
run_sql($pdo, "$root/database/seed.sql");

// Placeholder contact details (the real ones are set in the live admin panel)
$set = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
foreach (['phone' => '+252 61 000 0000', 'email' => 'info@oneoffice.example', 'whatsapp' => ''] as $k => $v) {
    $set->execute([$k, $v]);
}

// Demo admin with a random password that is never saved anywhere
$adminPass = bin2hex(random_bytes(12));
$pdo->prepare('INSERT INTO admins (username, name, password_hash) VALUES (?, ?, ?)')
    ->execute(['demo', 'Demo Admin', password_hash($adminPass, PASSWORD_DEFAULT)]);

// Sample requests – all names, numbers and emails are made up
$services = $pdo->query('SELECT s.id, s.title, c.title AS cat FROM services s JOIN categories c ON c.id = s.category_id ORDER BY s.id')->fetchAll();
$people = [
    ['Ahmed Hassan Ali', 'individual', 'Hodan, Mogadishu'], ['Fadumo Abdi Nur', 'individual', 'Wadajir, Mogadishu'],
    ['Barwaaqo Trading Co.', 'company', 'KM4, Mogadishu'], ['Mohamed Yusuf Omar', 'diaspora', 'Minneapolis, USA'],
    ['Hawa Ismail Farah', 'individual', 'Hamarweyne, Mogadishu'], ['Hiil Relief Organization', 'organization', 'Hodan, Mogadishu'],
    ['Abdirahman Said Warsame', 'diaspora', 'London, UK'], ['Sahan Logistics Ltd', 'company', 'Afgooye Road'],
    ['Asha Mohamud Jama', 'individual', 'Karaan, Mogadishu'], ['Ilays Tech Solutions', 'company', 'Taleex, Mogadishu'],
    ['Yasin Abdullahi Gedi', 'individual', 'Dharkenley, Mogadishu'], ['Nimco Ali Sheikh', 'diaspora', 'Oslo, Norway'],
];
$plan = [ // [days ago, status, paid share]
    [13, 'delivered', 1], [12, 'delivered', 1], [12, 'delivered', 1], [11, 'cancelled', 0], [10, 'delivered', 1],
    [9, 'delivered', 1], [9, 'ready', .5], [8, 'ready', 1], [7, 'submitted', .5], [7, 'delivered', 1],
    [6, 'submitted', .5], [5, 'needs_info', 0], [5, 'submitted', .5], [4, 'preparing', .5], [3, 'preparing', 0],
    [3, 'ready', 1], [2, 'needs_info', 0], [2, 'preparing', .5], [1, 'received', 0], [1, 'received', 0],
    [0, 'received', 0], [0, 'received', 0],
];
$notes = [
    'received'   => 'Your request has been received. Our team will contact you shortly.',
    'preparing'  => 'We are preparing your documents.',
    'needs_info' => 'Please send a copy of your ID card on WhatsApp.',
    'submitted'  => 'Documents submitted to the institution. Waiting for approval.',
    'ready'      => 'Your document is ready. You can collect it from our office.',
    'delivered'  => 'Delivered to the customer. Thank you for using ONE OFFICE.',
    'cancelled'  => 'Cancelled at the customer\'s request.',
];
$flow = ['received', 'preparing', 'submitted', 'ready', 'delivered'];
$insReq = $pdo->prepare('INSERT INTO requests (tracking_no, service_id, service_title, category_title, customer_type, full_name, phone, email, location, details, status,
    service_fee, gov_fee, delivery_fee, processing_fee, amount_paid, source, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$insUpd = $pdo->prepare('INSERT INTO request_updates (request_id, status, note, admin_name, created_at) VALUES (?,?,?,?,?)');
$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
foreach ($plan as $i => [$ago, $status, $paidShare]) {
    $svc = $services[($i * 7 + 3) % count($services)];
    [$name, $type, $loc] = $people[$i % count($people)];
    $created = (new DateTimeImmutable("-$ago days"))->setTime(8 + ($i % 8), ($i * 13) % 60);
    $fees = [25 + ($i % 4) * 10, ($i % 3) * 15, $i % 2 ? 5 : 0, $i % 5 === 0 ? 10 : 0];
    $total = array_sum($fees);
    $code = '';
    for ($c = 0; $c < 4; $c++) {
        $code .= $alphabet[($i * 11 + $c * 7 + 5) % strlen($alphabet)];
    }
    $insReq->execute([
        'OO-' . $created->format('ymd') . '-' . $code, $svc['id'], $svc['title'], $svc['cat'], $type, $name,
        sprintf('+252 61 000 %04d', 100 + $i), $i % 3 === 0 ? 'customer' . ($i + 1) . '@example.com' : null, $loc,
        'Sample request for the demo preview.', $status, $fees[0], $fees[1], $fees[2], $fees[3], round($total * $paidShare, 2),
        $i % 4 === 1 ? 'walk-in' : 'website', $created->format('Y-m-d H:i:s'), $created->format('Y-m-d H:i:s'),
    ]);
    $rid = (int) $pdo->lastInsertId();
    $steps = $status === 'cancelled' ? ['received', 'cancelled']
        : ($status === 'needs_info' ? ['received', 'preparing', 'needs_info'] : array_slice($flow, 0, array_search($status, $flow, true) + 1));
    foreach ($steps as $n => $st) {
        $when = $created->modify('+' . ($n * 9) . ' hours');
        if ($when > new DateTimeImmutable()) {
            $when = new DateTimeImmutable('-' . (count($steps) - $n) . ' minutes');
        }
        $insUpd->execute([$rid, $st, $notes[$st], $n === 0 ? null : 'Demo Admin', $when->format('Y-m-d H:i:s')]);
    }
}

$insMsg = $pdo->prepare('INSERT INTO messages (name, email, phone, subject, message, is_read, created_at) VALUES (?,?,?,?,?,?,?)');
$insMsg->execute(['Khadija Omar', 'khadija@example.com', '+252 61 000 0201', 'Passport renewal', 'How long does a passport renewal take, and which documents do I need?', 0, date('Y-m-d H:i:s', strtotime('-3 hours'))]);
$insMsg->execute(['Daryeel Clinic', 'admin@daryeel.example', '+252 61 000 0202', 'Business license', 'We want to register a new clinic. Can you help with the license and tax registration?', 0, date('Y-m-d H:i:s', strtotime('-1 day'))]);
$insMsg->execute(['Omar Dahir', 'omar@example.com', null, 'Thank you', 'I received my birth certificate today. Great service!', 1, date('Y-m-d H:i:s', strtotime('-4 days'))]);

$insT = $pdo->prepare('INSERT INTO testimonials (name, company, quote, rating, sort_order) VALUES (?,?,?,?,?)');
$insT->execute(['Ahmed H.', 'Customer', 'They handled my documents from start to finish. I only came to the office once.', 5, 1]);
$insT->execute(['Barwaaqo Trading', 'Company', 'Company registration and tax number were ready faster than we expected.', 5, 2]);
echo "demo database '$dbName' ready\n";

/* ------------------------------------------------------------------ 2. crawl the admin panel */
$jar = tempnam(sys_get_temp_dir(), 'oo');

function fetch(string $url, ?array $post = null): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return [$code, $body];
}

function csrf_of(string $html): string
{
    return preg_match('~name="csrf" value="([^"]+)"~', $html, $m) ? $m[1] : '';
}

/** "requests.php?status=ready" -> "requests-status-ready.html" */
function page_file(string $path, string $query): string
{
    $name = basename($path, '.php');
    if ($query === '') {
        return "$name.html";
    }
    parse_str(html_entity_decode($query), $q);
    ksort($q);
    $slug = preg_replace('~[^a-z0-9]+~i', '-', http_build_query($q));
    return $name . '-' . trim(strtolower($slug), '-') . '.html';
}

function skip_link(string $path, string $query): bool
{
    parse_str(html_entity_decode($query), $q);
    return $path === 'logout.php' || isset($q['q']) || isset($q['page']);
}

[$code, $loginHtml] = fetch("$base/admin/login.php");
if ($code !== 200) {
    fwrite(STDERR, "Could not open $base/admin/login.php (HTTP $code) – is the demo server running?\n");
    exit(1);
}
[$code] = fetch("$base/admin/login.php", ['csrf' => csrf_of($loginHtml), 'username' => 'demo', 'password' => $adminPass]);
if ($code !== 302) {
    fwrite(STDERR, "Login failed (HTTP $code). Make sure the server also uses DB_NAME=$dbName.\n");
    exit(1);
}

$linkRe = '~\b(href|data-href)="([a-z]+\.php)(\?[^"#]*)?(#[^"]*)?"~';
$queue = ['index.php' => ['index.php', '']];
$pages = [];
while ($queue && count($pages) < 160) {
    $key = array_key_first($queue);
    [$path, $query] = $queue[$key];
    unset($queue[$key]);
    $url = "$base/admin/$path" . ($query !== '' ? '?' . html_entity_decode($query) : '');
    [$code, $html] = fetch($url);
    if ($code !== 200) {
        fwrite(STDERR, "skip $url (HTTP $code)\n");
        continue;
    }
    $file = page_file($path, $query);
    $pages[$file] = $html;
    preg_match_all($linkRe, $html, $m, PREG_SET_ORDER);
    foreach ($m as $l) {
        $q = ltrim($l[3] ?? '', '?');
        $f = page_file($l[2], $q);
        if (!isset($pages[$f]) && !isset($queue[$f]) && !skip_link($l[2], $q)) {
            $queue[$f] = [$l[2], $q];
        }
    }
}
$pages['login.html'] = $loginHtml;

/* ------------------------------------------------------------------ 3. rewrite + save */
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
rrmdir($out);
mkdir($out, 0755, true);

$banner = '<div class="demo-banner" role="note"><i class="bi bi-info-circle"></i> Admin panel demo with sample data – saving changes works only in the full PHP version.</div>';
foreach ($pages as $file => $html) {
    // admin links -> static files (links that were not crawled fall back to the main page)
    $html = preg_replace_callback($linkRe, function ($l) use ($pages) {
        $q = ltrim($l[3] ?? '', '?');
        $f = page_file($l[2], $q);
        if (!isset($pages[$f])) {
            $f = page_file($l[2], '');
        }
        return $l[1] . '="' . $f . ($l[4] ?? '') . '"';
    }, $html);
    // links to the public website
    $html = preg_replace('~href="\.\./(index|about|services|request|track|contact)\.php~', 'href="../$1.html', $html);
    $html = preg_replace('~<input type="hidden" name="csrf" value="[^"]*">~', '', $html);
    $html = preg_replace('~\baction="[^"]*\.php[^"]*"~', 'action="#"', $html);

    if ($file === 'login.html') {
        // Demo login: any click on "Sign in" opens the dashboard
        $html = str_replace('<form method="post" class="form">', '<form method="post" class="form" data-demo-go="index.html">', $html);
        $html = str_replace('name="username" required', 'name="username" required value="demo"', $html);
        $html = str_replace(' value="demo" autofocus autocomplete="username" value=""', ' value="demo" autocomplete="username"', $html);
        $html = str_replace('<input type="password" name="password" required', '<input type="password" name="password" value="demo-preview"', $html);
        $html = str_replace('<p class="muted">Sign in to manage', '<div class="alert alert-info">Demo: just click <b>Sign in</b> – no password needed.</div><p class="muted">Sign in to manage', $html);
    }
    $html = preg_replace('~(<body[^>]*>)~', '$1' . "\n" . $banner, $html, 1);
    $html = str_replace('</body>', '<script src="../assets/js/demo.js"></script>' . "\n</body>", $html);
    file_put_contents("$out/$file", $html);
}
@unlink($jar);
echo 'built ' . count($pages) . " pages in docs/admin/\n";
