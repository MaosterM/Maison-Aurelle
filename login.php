<?php
// Staff sign-in. Guests never need an account: they book and manage a stay with a booking ID and email.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';
require __DIR__ . '/includes/reservation.php';
require __DIR__ . '/includes/admin.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (admin_signed_in()) { header('Location: admin.php'); exit; }

$ready = ADMIN_PASSWORD_HASH !== '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { http_response_code(400); exit('Invalid request. Go back and try again.'); }
    if ($ready) {
        try {
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (!rate_ok(db(), 'adm:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 5, 900)) {
                http_response_code(429);
                $error = 'Too many attempts. Please wait a few minutes and try again.';
            } else {
                // Check both every time, and give one message, so a wrong try can't reveal which part was wrong.
                $nameOk = hash_equals(ADMIN_USERNAME, field($_POST, 'username'));
                $passOk = strlen($password) <= 200 && password_verify($password, ADMIN_PASSWORD_HASH);
                if ($nameOk && $passOk) {
                    session_regenerate_id(true);
                    $_SESSION['admin'] = time();
                    header('Location: admin.php'); exit;
                }
                $error = 'That username or password is not right.';
            }
        } catch (PDOException $x) {
            http_response_code(503);
            $error = 'Sign-in is unavailable right now. Please try again shortly.';
        }
    }
}

$title = 'Staff sign in';
$page = 'login.php';
$css = ['assets/css/booking.css', 'assets/css/admin.css'];
$hideChat = true;
$noindex = true;
include __DIR__ . '/includes/header.php';
?>
<section class="adm adm-login">
  <span class="label">Staff only</span>
  <h1>Staff sign in</h1>
  <?php if (!$ready): ?>
    <p class="alert">Staff sign-in hasn't been set up yet.</p>
    <?php if (is_local_request()): ?><p>To set it up, follow "Staff sign-in" in the README.</p><?php endif; ?>
  <?php else: ?>
    <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="login.php">
      <?= csrf_field() ?>
      <label class="fld">Username<input name="username" required maxlength="80" autocomplete="username" value="<?= e(field($_POST, 'username')) ?>"></label>
      <label class="fld">Password<input type="password" name="password" required maxlength="200" autocomplete="current-password"></label>
      <button class="btn" type="submit">Sign in</button>
    </form>
  <?php endif; ?>
  <p class="adm-guest">Here for your stay? <a href="manage.php">Manage your reservation</a> with your booking ID and email.</p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
