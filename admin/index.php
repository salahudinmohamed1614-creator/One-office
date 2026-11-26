<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_login();

$pdo = db();
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();

// Requests by status
$byStatus = $pdo->query('SELECT status, COUNT(*) c FROM requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses = request_statuses();
$totalReq = array_sum($byStatus);
$inProgress = (int) ($byStatus['preparing'] ?? 0) + (int) ($byStatus['needs_info'] ?? 0) + (int) ($byStatus['submitted'] ?? 0);

$cards = [
    ['Total requests', $totalReq, 'bi-clipboard-data', 'requests.php', 'c1'],
    ['New (to review)', (int) ($byStatus['received'] ?? 0), 'bi-inbox', 'requests.php?status=received', 'c2'],
    ['In progress', $inProgress, 'bi-hourglass-split', 'requests.php?status=active', 'c3'],
    ['Ready', (int) ($byStatus['ready'] ?? 0), 'bi-box-seam', 'requests.php?status=ready', 'c5'],
    ['Delivered', (int) ($byStatus['delivered'] ?? 0), 'bi-check2-circle', 'requests.php?status=delivered', 'c4'],
    ['Unread messages', (int) $one('SELECT COUNT(*) FROM messages WHERE is_read = 0'), 'bi-envelope-exclamation', 'messages.php?filter=unread', 'c6'],
];

// Money (cancelled requests excluded)
$m = $pdo->query("SELECT COALESCE(SUM(service_fee),0) svc, COALESCE(SUM(gov_fee),0) gov,
                         COALESCE(SUM(service_fee + gov_fee + delivery_fee + processing_fee),0) billed,
                         COALESCE(SUM(amount_paid),0) paid
                  FROM requests WHERE status <> 'cancelled'")->fetch();
$outstanding = max(0, (float) $m['billed'] - (float) $m['paid']);

// Requests over the last 14 days
$rows = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM requests
                     WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)")->fetchAll();
$byDay = array_column($rows, 'c', 'd');
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $days[] = ['label' => date('j M', strtotime($d)), 'short' => date('D', strtotime($d)), 'value' => (int) ($byDay[$d] ?? 0)];
}
$max = max(1, max(array_column($days, 'value')));

$topServices = $pdo->query('SELECT service_title, COUNT(*) c FROM requests GROUP BY service_title ORDER BY c DESC LIMIT 6')->fetchAll();
$topMax = max(1, (int) ($topServices[0]['c'] ?? 1));
$byCategory = $pdo->query("SELECT COALESCE(category_title, '—') t, COUNT(*) c FROM requests GROUP BY category_title ORDER BY c DESC")->fetchAll();

$latest = $pdo->query('SELECT id, tracking_no, full_name, phone, service_title, status, created_at FROM requests ORDER BY created_at DESC, id DESC LIMIT 8')->fetchAll();
$messages = $pdo->query('SELECT id, name, email, subject, is_read, created_at FROM messages ORDER BY created_at DESC LIMIT 5')->fetchAll();

$content = [
    ['Categories', (int) $one('SELECT COUNT(*) FROM categories'), 'bi-collection', 'manage.php?t=categories'],
    ['Services', (int) $one('SELECT COUNT(*) FROM services'), 'bi-grid', 'manage.php?t=services'],
    ['FAQ', (int) $one('SELECT COUNT(*) FROM faqs'), 'bi-question-circle', 'manage.php?t=faqs'],
    ['Testimonials', (int) $one('SELECT COUNT(*) FROM testimonials'), 'bi-chat-quote', 'manage.php?t=testimonials'],
];

$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

admin_header('Dashboard', 'dashboard');
?>
<section class="welcome">
  <div>
    <p class="welcome-date"><i class="bi bi-calendar3"></i> <?= e(date('l, j F Y')) ?></p>
    <h2><?= e($greet) ?>, <?= e($_SESSION['admin_name']) ?> 👋</h2>
    <p>Manage customer requests, services and the <?= e(setting('site_name', 'ONE OFFICE')) ?> website from here.</p>
  </div>
  <div class="welcome-actions">
    <a href="requests.php?action=new" class="btn btn-accent"><i class="bi bi-plus-lg"></i> New request</a>
    <a href="requests.php?status=received" class="btn btn-glass"><i class="bi bi-inbox"></i> Review new</a>
  </div>
</section>

<div class="stat-grid">
  <?php foreach ($cards as [$label, $value, $icon, $href, $cls]): ?>
    <a class="stat-card <?= $cls ?>" href="<?= e($href) ?>">
      <i class="bi <?= e($icon) ?>"></i>
      <div><strong><?= (int) $value ?></strong><span><?= e($label) ?></span></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="money-grid">
  <div class="money"><span>Total billed</span><strong><?= e(money($m['billed'])) ?></strong><small>All fees, excl. cancelled</small></div>
  <div class="money ok"><span>Received (paid)</span><strong><?= e(money($m['paid'])) ?></strong><small>Payments recorded</small></div>
  <div class="money warn"><span>Outstanding</span><strong><?= e(money($outstanding)) ?></strong><small>Billed – paid</small></div>
  <div class="money brand"><span>ONE OFFICE service fees</span><strong><?= e(money($m['svc'])) ?></strong><small>Gov. fees kept separate: <?= e(money($m['gov'])) ?></small></div>
</div>

<div class="two-col wide-left">
  <section class="panel">
    <div class="panel-head"><h2>Requests – last 14 days</h2><span class="muted small"><?= array_sum(array_column($days, 'value')) ?> total</span></div>
    <div class="bars" role="img" aria-label="Requests per day for the last 14 days">
      <?php foreach ($days as $d): ?>
        <div class="bar-col" title="<?= e($d['label']) ?>: <?= $d['value'] ?>">
          <span class="bar-val"><?= $d['value'] ?: '' ?></span>
          <div class="bar" style="height: <?= max(3, (int) round($d['value'] / $max * 100)) ?>%"></div>
          <small><?= e($d['short']) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Requests by status</h2></div>
    <?php if (!$totalReq): ?>
      <p class="empty"><i class="bi bi-clipboard"></i> No requests yet.</p>
    <?php else: ?>
      <ul class="hbars">
        <?php foreach ($statuses as $key => $s): $c = (int) ($byStatus[$key] ?? 0); ?>
          <li>
            <a href="requests.php?status=<?= e($key) ?>"><?= status_badge($key, true) ?></a>
            <div class="hbar"><span class="hb-<?= e($key) ?>" style="width: <?= $totalReq ? round($c / $totalReq * 100) : 0 ?>%"></span></div>
            <b><?= $c ?></b>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <div class="panel-head"><h2>Latest requests</h2><a href="requests.php" class="link">View all <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$latest): ?>
    <p class="empty"><i class="bi bi-clipboard"></i> No requests yet. Requests from the website (or ones you add for walk-in customers) appear here.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tracking no.</th><th>Customer</th><th>Service</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($latest as $r): ?>
            <tr class="row-link" data-href="requests.php?id=<?= (int) $r['id'] ?>">
              <td data-label="Tracking"><a class="mono strong" href="requests.php?id=<?= (int) $r['id'] ?>"><?= e($r['tracking_no']) ?></a></td>
              <td data-label="Customer"><b><?= e($r['full_name']) ?></b><br><small class="muted"><?= e($r['phone']) ?></small></td>
              <td data-label="Service"><?= e(mb_strimwidth($r['service_title'], 0, 48, '…')) ?></td>
              <td data-label="Status"><?= status_badge($r['status'], true) ?></td>
              <td data-label="Date"><?= e(date('M j, H:i', strtotime($r['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<div class="two-col">
  <section class="panel">
    <div class="panel-head"><h2>Most requested services</h2></div>
    <?php if (!$topServices): ?>
      <p class="empty"><i class="bi bi-bar-chart"></i> No data yet.</p>
    <?php else: ?>
      <ul class="hbars plain-bars">
        <?php foreach ($topServices as $t): ?>
          <li><span class="hb-label"><?= e(mb_strimwidth($t['service_title'], 0, 40, '…')) ?></span>
            <div class="hbar"><span style="width: <?= round((int) $t['c'] / $topMax * 100) ?>%"></span></div><b><?= (int) $t['c'] ?></b></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($byCategory): ?>
        <div class="chips-row">
          <?php foreach ($byCategory as $bc): ?><span class="chip"><?= e($bc['t']) ?> · <b><?= (int) $bc['c'] ?></b></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Latest messages</h2><a href="messages.php" class="link">View all <i class="bi bi-arrow-right"></i></a></div>
    <?php if (!$messages): ?>
      <p class="empty"><i class="bi bi-inbox"></i> No messages yet.</p>
    <?php else: ?>
      <ul class="msg-list">
        <?php foreach ($messages as $msg): ?>
          <li class="<?= $msg['is_read'] ? '' : 'unread' ?>">
            <a href="messages.php?id=<?= (int) $msg['id'] ?>">
              <div class="avatar-sm"><?= e(initials($msg['name'])) ?></div>
              <div class="grow"><b><?= e($msg['name']) ?></b><small><?= e($msg['subject'] ?: $msg['email']) ?></small></div>
              <time><?= e(date('M j', strtotime($msg['created_at']))) ?></time>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <div class="panel-head"><h2>Website content</h2><a href="../index.php" target="_blank" class="link">Open website <i class="bi bi-box-arrow-up-right"></i></a></div>
  <div class="mini-grid">
    <?php foreach ($content as [$label, $count, $icon, $href]): ?>
      <a class="mini" href="<?= e($href) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><b><?= (int) $count ?></b></a>
    <?php endforeach; ?>
    <a class="mini" href="settings.php"><i class="bi bi-sliders"></i><span>Site settings</span><b><i class="bi bi-arrow-right"></i></b></a>
  </div>
</section>
<?php admin_footer();
