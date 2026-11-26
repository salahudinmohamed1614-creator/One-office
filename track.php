<?php
/**
 * Public tracking page: tracking number + phone number -> status timeline.
 * The phone check stops strangers from looking up someone else's request.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/site.php';
db_or_fail();

const TRACK_MAX_FAILS = 15;   // failed lookups per IP ...
const TRACK_WINDOW_MIN = 15;  // ... in this many minutes

$pdo = db();
$no = strtoupper(trim((string) ($_POST['no'] ?? $_GET['no'] ?? '')));
$no = preg_replace('/[^A-Z0-9-]/', '', $no);
$phone = trim((string) ($_POST['phone'] ?? ''));
$error = '';
$req = null;
$updates = [];
$key = 'trk:' . client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . TRACK_WINDOW_MIN . ' MINUTE)');
    $c->execute([$key]);
    if ((int) $c->fetchColumn() >= TRACK_MAX_FAILS) {
        $error = 'Too many failed attempts. Please wait ' . TRACK_WINDOW_MIN . ' minutes.';
    } elseif ($no === '' || strlen(phone_digits($phone)) < 6) {
        $error = 'Please enter the Tracking Number and your phone number.';
    } else {
        $s = $pdo->prepare('SELECT * FROM requests WHERE tracking_no = ?');
        $s->execute([$no]);
        $row = $s->fetch();
        $match = false;
        if ($row) {
            $a = substr(phone_digits($row['phone']), -7);
            $b = substr(phone_digits($phone), -7);
            $match = $a !== '' && hash_equals($a, $b);
        }
        if ($match) {
            $req = $row;
            $u = $pdo->prepare('SELECT status, note, created_at FROM request_updates WHERE request_id = ? ORDER BY created_at DESC, id DESC');
            $u->execute([$req['id']]);
            $updates = $u->fetchAll();
        } else {
            $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())')->execute([$key]);
            $error = 'Request not found. Please check the Tracking Number and phone number.';
        }
    }
}

$statuses = request_statuses();
$timeline = [
    1 => ['bi-inbox', 'Received'],
    2 => ['bi-folder2-open', 'Preparing'],
    3 => ['bi-bank', 'Institution'],
    4 => ['bi-box-seam', 'Ready'],
    5 => ['bi-truck', 'Delivered'],
];

site_header('Track Request', 'track', 'Follow the status of your request with your Tracking Number.');
?>
  <section class="page-hero small">
    <div class="container">
      <span class="eyebrow">One Tracking Number</span>
      <h1>Track your request</h1>
      <p class="lead">Enter the Tracking Number you received and the phone number you used when submitting the request.</p>
    </div>
  </section>

  <section class="section tight">
    <div class="container track-wrap">
      <form class="contact-form track-form" method="post" action="track.php">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <div class="row-2">
          <label>Tracking Number *<input type="text" name="no" required maxlength="20" value="<?= e($no) ?>" placeholder="OO-261126-AB12" autocomplete="off" class="mono"></label>
          <label>Phone number *<input type="tel" name="phone" required maxlength="40" value="<?= e($phone) ?>" placeholder="+252 …" autocomplete="tel"></label>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><i class="bi bi-search"></i> Track</button>
      </form>

      <?php if ($req):
          $cur = $statuses[$req['status']][3] ?? 0;
          $cancelled = $req['status'] === 'cancelled';
          $total = (float) $req['service_fee'] + (float) $req['gov_fee'] + (float) $req['delivery_fee'] + (float) $req['processing_fee'];
          $first = explode(' ', trim($req['full_name']))[0];
      ?>
      <article class="track-result reveal">
        <header class="tr-head">
          <div>
            <small class="muted">Tracking Number</small>
            <div class="tracking-no sm"><?= e($req['tracking_no']) ?></div>
          </div>
          <?= status_badge($req['status']) ?>
        </header>
        <dl class="tr-meta">
          <div><dt>Service</dt><dd><?= e($req['service_title']) ?></dd></div>
          <div><dt>Category</dt><dd><?= e($req['category_title'] ?? '—') ?></dd></div>
          <div><dt>Applicant</dt><dd><?= e($first) ?></dd></div>
          <div><dt>Submitted</dt><dd><?= e(date('d M Y', strtotime($req['created_at']))) ?></dd></div>
          <div><dt>Last update</dt><dd><?= e(date('d M Y, H:i', strtotime($req['updated_at']))) ?></dd></div>
        </dl>

        <?php if ($cancelled): ?>
          <div class="alert alert-error">This request has been cancelled. Please contact us if you have any questions.</div>
        <?php else: ?>
          <ol class="timeline" aria-label="Request progress">
            <?php foreach ($timeline as $n => [$icon, $label]): ?>
              <li class="<?= $n < $cur ? 'done' : ($n === $cur ? 'current' : '') ?>">
                <span><i class="bi <?= $n < $cur ? 'bi-check-lg' : e($icon) ?>"></i></span><small><?= e($label) ?></small>
              </li>
            <?php endforeach; ?>
          </ol>
          <?php if ($req['status'] === 'needs_info'): ?>
            <div class="alert alert-warn"><i class="bi bi-exclamation-circle"></i> We need more information or documents from you. Please contact us.</div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($total > 0): ?>
          <h3 class="tr-sub">Fees</h3>
          <table class="fee-table">
            <?php foreach (['service_fee' => 'Service Fee', 'gov_fee' => 'Government Fee', 'processing_fee' => 'Document Processing Fee', 'delivery_fee' => 'Delivery Fee'] as $col => $label): if ((float) $req[$col] <= 0) continue; ?>
              <tr><td><?= e($label) ?></td><td><?= e(money($req[$col])) ?></td></tr>
            <?php endforeach; ?>
            <tr class="total"><td>Total</td><td><?= e(money($total)) ?></td></tr>
            <tr><td>Paid</td><td><?= e(money($req['amount_paid'])) ?></td></tr>
            <?php $bal = $total - (float) $req['amount_paid']; if ($bal > 0.004): ?>
              <tr class="due"><td>Balance due</td><td><?= e(money($bal)) ?></td></tr>
            <?php endif; ?>
          </table>
        <?php endif; ?>

        <?php if ($updates): ?>
          <h3 class="tr-sub">Request history</h3>
          <ul class="history">
            <?php foreach ($updates as $u): ?>
              <li>
                <?= status_badge($u['status']) ?>
                <time><?= e(date('d M Y, H:i', strtotime($u['created_at']))) ?></time>
                <?php if ($u['note']): ?><p><?= nl2br(e($u['note'])) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>
      <?php else: ?>
      <div class="track-help">
        <div class="side-card">
          <h3><i class="bi bi-question-circle"></i> Don’t have a Tracking Number?</h3>
          <p class="muted small">You receive a Tracking Number when you submit a request. If you lost it, contact us with your name and phone number.</p>
          <?php if (setting('requests_enabled', '1') === '1'): ?><a href="request.php" class="link-arrow">Request a new service <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
<?php site_footer();
