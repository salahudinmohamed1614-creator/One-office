<?php
/**
 * Handles the public contact form.
 * Works with AJAX (returns JSON) and without JavaScript (redirects back).
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function respond(bool $ok, string $message, bool $isAjax, array $old = []): never
{
    if ($isAjax) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    flash($ok ? 'success' : 'error', $message);
    if (!$ok) {
        $_SESSION['contact_old'] = $old;
    }
    redirect('index.php#contact');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#contact');
}

$data = [
    'name'    => trim((string) ($_POST['name'] ?? '')),
    'email'   => trim((string) ($_POST['email'] ?? '')),
    'phone'   => trim((string) ($_POST['phone'] ?? '')),
    'subject' => trim((string) ($_POST['subject'] ?? '')),
    'message' => trim((string) ($_POST['message'] ?? '')),
];

if (!csrf_check()) {
    respond(false, 'Your session expired. Please refresh the page and try again.', $isAjax, $data);
}

// Honeypot: bots fill hidden fields. Pretend success.
if (!empty($_POST['website'])) {
    respond(true, 'Thank you! Your message has been sent.', $isAjax);
}

// Simple throttle: one message per 30 seconds per session
if (isset($_SESSION['last_contact']) && time() - (int) $_SESSION['last_contact'] < 30) {
    respond(false, 'Please wait a few seconds before sending another message.', $isAjax, $data);
}

$errors = [];
if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 100) {
    $errors[] = 'Please enter your name.';
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150) {
    $errors[] = 'Please enter a valid email address.';
}
if ($data['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{6,40}$/', $data['phone'])) {
    $errors[] = 'Please enter a valid phone number.';
}
if (mb_strlen($data['message']) < 10 || mb_strlen($data['message']) > 5000) {
    $errors[] = 'Your message must be between 10 and 5000 characters.';
}
$data['subject'] = mb_substr($data['subject'], 0, 150);

if ($errors) {
    respond(false, implode(' ', $errors), $isAjax, $data);
}

try {
    $stmt = db()->prepare('INSERT INTO messages (name, email, phone, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$data['name'], $data['email'], $data['phone'] ?: null, $data['subject'] ?: null, $data['message'], client_ip()]);
} catch (Throwable $ex) {
    error_log('Contact save failed: ' . $ex->getMessage());
    respond(false, 'Sorry, something went wrong. Please try again later.', $isAjax, $data);
}

$_SESSION['last_contact'] = time();
respond(true, 'Thank you! Your message has been received. We will contact you soon.', $isAjax);
