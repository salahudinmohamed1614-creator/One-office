<?php
declare(strict_types=1);

function new_requests_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM requests WHERE status = 'received'")->fetchColumn();
}

function admin_asset(string $path): string
{
    return admin_asset_url($path);
}

function admin_header(string $title, string $active = ''): void
{
    $entities = require __DIR__ . '/entities.php';
    $unread   = unread_count();
    $newReq   = new_requests_count();
    $siteName = setting('site_name', 'ONE OFFICE');
    $groups = [
        'Overview' => [
            ['index.php', 'dashboard', 'bi-speedometer2', 'Dashboard', 0],
            ['requests.php', 'requests', 'bi-clipboard-check', 'Service requests', $newReq],
            ['messages.php', 'messages', 'bi-inbox', 'Messages', $unread],
        ],
        'Website content' => [],
        'System' => [
            ['settings.php', 'settings', 'bi-sliders', 'Site settings', 0],
            ['password.php', 'password', 'bi-shield-lock', 'Change password', 0],
        ],
    ];
    foreach ($entities as $key => $ent) {
        $groups['Website content'][] = ['manage.php?t=' . $key, $key, $ent['icon'], $ent['title'], 0];
    }
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($siteName) ?> Admin</title>
<link rel="icon" type="image/png" href="<?= e(admin_asset('img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(admin_asset('css/admin.css')) ?>">
</head>
<body class="admin">
<aside class="sidebar" id="sidebar">
  <a href="index.php" class="brand">
    <img src="<?= e(admin_asset('img/logo-mark.png')) ?>" alt="" width="40" height="40">
    <span><b><?= e($siteName) ?></b><small>Admin panel</small></span>
  </a>
  <nav>
    <?php foreach ($groups as $label => $items): if (!$items) continue; ?>
      <p class="nav-label"><?= e($label) ?></p>
      <?php foreach ($items as [$href, $key, $icon, $text, $badge]): ?>
        <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>">
          <i class="bi <?= e($icon) ?>"></i><span><?= e($text) ?></span>
          <?php if ($badge > 0): ?><b class="count"><?= (int) $badge ?></b><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a href="../index.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i><span>View website</span></a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit"><i class="bi bi-box-arrow-left"></i><span>Log out</span></button></form>
  </div>
</aside>
<div class="backdrop" id="backdrop"></div>
<div class="main">
  <header class="topbar">
    <button class="icon-btn" id="sideToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
    <h1><?= e($title) ?></h1>
    <a href="requests.php?action=new" class="btn btn-accent btn-sm hide-xs"><i class="bi bi-plus-lg"></i> New request</a>
    <div class="user"><div class="avatar-sm"><?= e(initials((string) ($_SESSION['admin_name'] ?? 'A'))) ?></div><span><?= e($_SESSION['admin_name'] ?? '') ?></span></div>
  </header>
  <div class="content">
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</div>
<script src="<?= e(admin_asset('js/admin.js')) ?>"></script>
</body>
</html>
<?php
}
