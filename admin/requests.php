<?php
/**
 * Service requests: list, view, update status / fees, add walk-in requests.
 */
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_login();
require_csrf();

$pdo      = db();
$statuses = request_statuses();
$types    = customer_types();
$sources  = ['website' => 'Website', 'walk_in' => 'Walk-in (office)', 'phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];
$feeCols  = ['service_fee' => 'Service fee (ONE OFFICE)', 'gov_fee' => 'Government fee', 'processing_fee' => 'Document processing fee', 'delivery_fee' => 'Delivery fee', 'amount_paid' => 'Amount paid'];

function find_request(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('SELECT * FROM requests WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Parse a money field; returns null when invalid */
function parse_money($raw): ?float
{
    $v = str_replace([',', ' '], '', trim((string) $raw));
    if ($v === '') {
        return 0.0;
    }
    if (!is_numeric($v) || (float) $v < 0 || (float) $v > 99999999) {
        return null;
    }
    return round((float) $v, 2);
}

function service_options(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SELECT s.id, s.title, c.title cat FROM services s JOIN categories c ON c.id = s.category_id ORDER BY c.sort_order, c.id, s.sort_order, s.id') as $r) {
        $out[$r['cat']][(int) $r['id']] = $r['title'];
    }
    return $out;
}

$errors = [];
$form = null;

/* ================= POST actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $admin = (string) ($_SESSION['admin_name'] ?? 'Admin');

    if ($action === 'status' && ($req = find_request($pdo, $id))) {
        $new = (string) ($_POST['status'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!isset($statuses[$new])) {
            flash('error', 'Choose a valid status.');
        } elseif (mb_strlen($note) > 2000) {
            flash('error', 'The note is too long (max 2000 characters).');
        } elseif ($new === $req['status'] && $note === '') {
            flash('error', 'Nothing changed. Pick a new status or write a note for the customer.');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE requests SET status = ? WHERE id = ?')->execute([$new, $id]);
            $pdo->prepare('INSERT INTO request_updates (request_id, status, note, admin_name) VALUES (?, ?, ?, ?)')
                ->execute([$id, $new, $note !== '' ? $note : null, $admin]);
            $pdo->commit();
            flash('success', 'Status updated to “' . status_label($new, true) . '”. The customer sees it on the tracking page.');
        }
        redirect('requests.php?id=' . $id);
    }

    if ($action === 'fees' && ($req = find_request($pdo, $id))) {
        $vals = [];
        foreach ($feeCols as $col => $label) {
            $v = parse_money($_POST[$col] ?? '');
            if ($v === null) {
                $errors[] = "$label must be a positive number.";
            }
            $vals[$col] = $v ?? 0.0;
        }
        $note = trim((string) ($_POST['internal_note'] ?? ''));
        if (mb_strlen($note) > 5000) {
            $errors[] = 'Internal note is too long.';
        }
        if ($errors) {
            foreach ($errors as $er) {
                flash('error', $er);
            }
        } else {
            $pdo->prepare('UPDATE requests SET service_fee = ?, gov_fee = ?, processing_fee = ?, delivery_fee = ?, amount_paid = ?, internal_note = ? WHERE id = ?')
                ->execute([$vals['service_fee'], $vals['gov_fee'], $vals['processing_fee'], $vals['delivery_fee'], $vals['amount_paid'], $note !== '' ? $note : null, $id]);
            flash('success', 'Fees and notes saved.');
        }
        redirect('requests.php?id=' . $id);
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM requests WHERE id = ?')->execute([$id]);
        flash('success', 'Request deleted.');
        redirect('requests.php');
    }

    if ($action === 'create') {
        $form = [];
        foreach (['full_name', 'phone', 'email', 'location', 'details', 'customer_type', 'source'] as $k) {
            $form[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        $form['service_id'] = (int) ($_POST['service_id'] ?? 0);
        $svc = null;
        if ($form['service_id'] > 0) {
            $s = $pdo->prepare('SELECT s.title, c.title cat FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id = ?');
            $s->execute([$form['service_id']]);
            $svc = $s->fetch() ?: null;
        }
        if (!$svc) {
            $errors[] = 'Choose a service.';
        }
        if (mb_strlen($form['full_name']) < 2 || mb_strlen($form['full_name']) > 100) {
            $errors[] = 'Customer name is required.';
        }
        if (strlen(phone_digits($form['phone'])) < 7 || mb_strlen($form['phone']) > 40) {
            $errors[] = 'A valid phone number is required (the customer needs it to track the request).';
        }
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is not valid.';
        }
        if (mb_strlen($form['location']) > 120 || mb_strlen($form['details']) > 3000) {
            $errors[] = 'Location or details are too long.';
        }
        $form['customer_type'] = array_key_exists($form['customer_type'], $types) ? $form['customer_type'] : 'individual';
        $form['source'] = array_key_exists($form['source'], $sources) ? $form['source'] : 'walk_in';
        $fees = [];
        foreach ($feeCols as $col => $label) {
            $v = parse_money($_POST[$col] ?? '');
            if ($v === null) {
                $errors[] = "$label must be a positive number.";
            }
            $fees[$col] = $v ?? 0.0;
            $form[$col] = $_POST[$col] ?? '';
        }

        if (!$errors) {
            $pdo->beginTransaction();
            $no = new_tracking_no($pdo);
            $pdo->prepare('INSERT INTO requests (tracking_no, service_id, service_title, category_title, customer_type, full_name, phone, email, location, details,
                                                 service_fee, gov_fee, processing_fee, delivery_fee, amount_paid, source)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$no, $form['service_id'], $svc['title'], $svc['cat'], $form['customer_type'], $form['full_name'], $form['phone'],
                           $form['email'] ?: null, $form['location'] ?: null, $form['details'] ?: null,
                           $fees['service_fee'], $fees['gov_fee'], $fees['processing_fee'], $fees['delivery_fee'], $fees['amount_paid'], $form['source']]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO request_updates (request_id, status, note, admin_name) VALUES (?, ?, ?, ?)')
                ->execute([$newId, 'received', 'Your request has been registered.', $admin]);
            $pdo->commit();
            flash('success', "Request created. Give the customer this tracking number: $no");
            redirect('requests.php?id=' . $newId);
        }
    }
}

/* ================= New request form ================= */
if (($_GET['action'] ?? '') === 'new' || $form !== null) {
    $form = $form ?? ['service_id' => 0, 'customer_type' => 'individual', 'source' => 'walk_in', 'full_name' => '', 'phone' => '', 'email' => '', 'location' => '', 'details' => ''];
    $svcOpts = service_options($pdo);
    admin_header('New request', 'requests');
    ?>
    <div class="panel form-panel">
      <div class="panel-head"><h2><i class="bi bi-person-plus"></i> Register a request (walk-in / phone)</h2><a href="requests.php" class="link"><i class="bi bi-arrow-left"></i> Back</a></div>
      <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
      <form method="post" class="form" action="requests.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="form-grid">
          <label class="full">Service *
            <select name="service_id" required>
              <option value="">— Choose a service —</option>
              <?php foreach ($svcOpts as $cat => $opts): ?>
                <optgroup label="<?= e($cat) ?>">
                  <?php foreach ($opts as $sid => $st): ?><option value="<?= $sid ?>" <?= (int) $form['service_id'] === $sid ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Customer name *<input type="text" name="full_name" required maxlength="100" value="<?= e($form['full_name']) ?>"></label>
          <label>Phone *<input type="text" name="phone" required maxlength="40" value="<?= e($form['phone']) ?>"></label>
          <label>Email<input type="email" name="email" maxlength="150" value="<?= e($form['email']) ?>"></label>
          <label>City / country<input type="text" name="location" maxlength="120" value="<?= e($form['location']) ?>"></label>
          <label>Customer type
            <select name="customer_type"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $form['customer_type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          </label>
          <label>Source
            <select name="source"><?php foreach ($sources as $k => $l): ?><option value="<?= e($k) ?>" <?= $form['source'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          </label>
          <label class="full">Details<textarea name="details" rows="4" maxlength="3000"><?= e($form['details']) ?></textarea></label>
        </div>
        <fieldset class="fees-box">
          <legend>Fees (optional – can be added later)</legend>
          <div class="form-grid three">
            <?php foreach ($feeCols as $col => $label): ?>
              <label><?= e($label) ?><input type="number" step="0.01" min="0" name="<?= e($col) ?>" value="<?= e($form[$col] ?? '') ?>" placeholder="0"></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Create & get tracking number</button>
          <a href="requests.php" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
    </div>
    <?php
    admin_footer();
    exit;
}

/* ================= Single request ================= */
if (isset($_GET['id'])) {
    $r = find_request($pdo, (int) $_GET['id']);
    if (!$r) {
        flash('error', 'Request not found.');
        redirect('requests.php');
    }
    $u = $pdo->prepare('SELECT * FROM request_updates WHERE request_id = ? ORDER BY created_at DESC, id DESC');
    $u->execute([$r['id']]);
    $history = $u->fetchAll();
    $total = (float) $r['service_fee'] + (float) $r['gov_fee'] + (float) $r['processing_fee'] + (float) $r['delivery_fee'];
    $balance = $total - (float) $r['amount_paid'];
    $wa = phone_digits($r['phone']);

    admin_header('Request ' . $r['tracking_no'], 'requests');
    ?>
    <div class="req-top">
      <a href="requests.php" class="link"><i class="bi bi-arrow-left"></i> All requests</a>
      <div class="actions">
        <button type="button" class="btn btn-ghost btn-sm" data-print><i class="bi bi-printer"></i> Print</button>
        <form method="post" class="inline" data-confirm="Delete request <?= e($r['tracking_no']) ?> and its history? This cannot be undone.">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit"><i class="bi bi-trash"></i> Delete</button>
        </form>
      </div>
    </div>

    <section class="panel req-hero">
      <div>
        <small class="muted">Tracking number</small>
        <div class="track-no"><?= e($r['tracking_no']) ?></div>
        <p class="muted small">Created <?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?> · via <?= e($sources[$r['source']] ?? $r['source']) ?> · updated <?= e(date('M j, H:i', strtotime($r['updated_at']))) ?></p>
      </div>
      <?= status_badge($r['status'], true) ?>
    </section>

    <div class="two-col wide-left">
      <div>
        <section class="panel">
          <div class="panel-head"><h2><i class="bi bi-file-earmark-text"></i> Request</h2></div>
          <dl class="details">
            <div><dt>Service</dt><dd><b><?= e($r['service_title']) ?></b></dd></div>
            <div><dt>Category</dt><dd><?= e($r['category_title'] ?? '—') ?></dd></div>
            <div><dt>Customer type</dt><dd><?= e($types[$r['customer_type']] ?? $r['customer_type']) ?></dd></div>
            <div class="full"><dt>Customer’s details</dt><dd><?= $r['details'] ? nl2br(e($r['details'])) : '<span class="muted">—</span>' ?></dd></div>
          </dl>
        </section>

        <section class="panel">
          <div class="panel-head"><h2><i class="bi bi-arrow-repeat"></i> Update status</h2></div>
          <form method="post" class="form" action="requests.php?id=<?= (int) $r['id'] ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="status-pick">
              <?php foreach ($statuses as $key => $s): ?>
                <label><input type="radio" name="status" value="<?= e($key) ?>" <?= $r['status'] === $key ? 'checked' : '' ?>><span class="st st-<?= e($key) ?>"><i class="bi <?= e($s[2]) ?>"></i> <?= e($s[1]) ?></span></label>
              <?php endforeach; ?>
            </div>
            <label>Note for the customer (shown on the tracking page, optional)
              <textarea name="note" rows="3" maxlength="2000" placeholder="e.g. Your documents have been submitted to the Ministry…"></textarea>
            </label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Save status</button></div>
          </form>
        </section>

        <section class="panel">
          <div class="panel-head"><h2><i class="bi bi-clock-history"></i> History</h2></div>
          <ul class="history">
            <?php foreach ($history as $h): ?>
              <li>
                <?= status_badge($h['status'], true) ?>
                <time><?= e(date('M j, Y H:i', strtotime($h['created_at']))) ?><?= $h['admin_name'] ? ' · ' . e($h['admin_name']) : '' ?></time>
                <?php if ($h['note']): ?><p><?= nl2br(e($h['note'])) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      </div>

      <div>
        <section class="panel">
          <div class="panel-head"><h2><i class="bi bi-person"></i> Customer</h2></div>
          <div class="cust">
            <div class="avatar-sm lg"><?= e(initials($r['full_name'])) ?></div>
            <div><b><?= e($r['full_name']) ?></b><small class="muted"><?= e($r['location'] ?? '') ?></small></div>
          </div>
          <ul class="contact-actions">
            <li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a></li>
            <?php if ($wa !== ''): ?><li><i class="bi bi-whatsapp"></i><a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('ONE OFFICE – ' . $r['tracking_no'] . ': ') ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
            <?php if ($r['email']): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('ONE OFFICE – ' . $r['tracking_no']) ?>"><?= e($r['email']) ?></a></li><?php endif; ?>
          </ul>
        </section>

        <section class="panel">
          <div class="panel-head"><h2><i class="bi bi-cash-stack"></i> Fees & payment</h2></div>
          <div class="fee-summary">
            <div><span>Total</span><b><?= e(money($total)) ?></b></div>
            <div><span>Paid</span><b class="ok-text"><?= e(money($r['amount_paid'])) ?></b></div>
            <div><span>Balance</span><b class="<?= $balance > 0.004 ? 'warn-text' : 'ok-text' ?>"><?= e(money(max(0, $balance))) ?></b></div>
          </div>
          <form method="post" class="form" action="requests.php?id=<?= (int) $r['id'] ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="fees"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <?php foreach ($feeCols as $col => $label): ?>
              <label><?= e($label) ?><input type="number" step="0.01" min="0" name="<?= e($col) ?>" value="<?= e((float) $r[$col] ? $r[$col] : '') ?>" placeholder="0"></label>
            <?php endforeach; ?>
            <label>Internal note (staff only)<textarea name="internal_note" rows="3" maxlength="5000"><?= e($r['internal_note'] ?? '') ?></textarea></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Save fees</button>
          </form>
        </section>
      </div>
    </div>
    <?php
    admin_footer();
    exit;
}

/* ================= List ================= */
$status = (string) ($_GET['status'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($status === 'active') {
    $where[] = "status IN ('preparing','needs_info','submitted')";
} elseif (isset($statuses[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(tracking_no LIKE ? OR full_name LIKE ? OR phone LIKE ? OR service_title LIKE ? OR email LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$c = $pdo->prepare("SELECT COUNT(*) FROM requests $whereSql");
$c->execute($params);
$total = (int) $c->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$offset = ($page - 1) * $per;

$s = $pdo->prepare("SELECT id, tracking_no, full_name, phone, service_title, category_title, status, source,
                           (service_fee + gov_fee + processing_fee + delivery_fee) total, amount_paid, created_at
                    FROM requests $whereSql ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $offset");
$s->execute($params);
$rows = $s->fetchAll();

$counts = $pdo->query('SELECT status, COUNT(*) FROM requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$all = array_sum($counts);
$qs = fn(array $extra) => 'requests.php?' . http_build_query(array_filter(array_merge(['status' => $status ?: null, 'q' => $q ?: null], $extra)));

admin_header('Service requests', 'requests');
?>
<div class="panel">
  <div class="panel-head wrap">
    <div class="tabs scroll">
      <a href="requests.php<?= $q !== '' ? '?q=' . e(rawurlencode($q)) : '' ?>" class="<?= $status === '' ? 'active' : '' ?>">All <b class="num"><?= $all ?></b></a>
      <?php foreach ($statuses as $key => $st): ?>
        <a href="<?= e('requests.php?' . http_build_query(array_filter(['status' => $key, 'q' => $q ?: null]))) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($st[1]) ?> <b class="num"><?= (int) ($counts[$key] ?? 0) ?></b></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel-head wrap">
    <form method="get" class="search">
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Tracking no., name, phone, service…">
    </form>
    <a href="requests.php?action=new" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New request</a>
  </div>

  <?php if (!$rows): ?>
    <p class="empty"><i class="bi bi-clipboard"></i> No requests found.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tracking no.</th><th>Customer</th><th>Service</th><th>Status</th><th class="right">Total / paid</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="row-link" data-href="requests.php?id=<?= (int) $r['id'] ?>">
              <td data-label="Tracking"><a class="mono strong" href="requests.php?id=<?= (int) $r['id'] ?>"><?= e($r['tracking_no']) ?></a></td>
              <td data-label="Customer"><b><?= e($r['full_name']) ?></b><br><small class="muted"><?= e($r['phone']) ?></small></td>
              <td data-label="Service"><?= e(mb_strimwidth($r['service_title'], 0, 44, '…')) ?><br><small class="muted"><?= e($r['category_title'] ?? '') ?></small></td>
              <td data-label="Status"><?= status_badge($r['status'], true) ?></td>
              <td data-label="Total / paid" class="right"><?= (float) $r['total'] > 0 ? e(money($r['total'])) . '<br><small class="muted">' . e(money($r['amount_paid'])) . ' paid</small>' : '<span class="muted">—</span>' ?></td>
              <td data-label="Date"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
              <td class="right actions"><a href="requests.php?id=<?= (int) $r['id'] ?>" class="icon-btn" title="Open"><i class="bi bi-chevron-right"></i></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="<?= e($qs(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_footer();
