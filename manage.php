<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';
require __DIR__ . '/includes/booking-engine.php';
require __DIR__ . '/includes/reservation.php';
if (!function_exists('money2')) { function money2($n): string { return (CURRENCY === 'USD' ? '$' : CURRENCY . ' ') . number_format((float)$n, 2); } }

try { $db = db(); $db->query('SELECT 1'); }
catch (Throwable $x) { http_response_code(503); exit('Reservations are temporarily unavailable.'); }

$err = $ok = '';
$preview = null;
$mid = (isset($_SESSION['manage']) && time() - $_SESSION['manage']['at'] < 1800) ? (int)$_SESSION['manage']['id'] : 0;
$b = $mid ? load_booking($db, $mid) : null;
if (!$b) unset($_SESSION['manage']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { http_response_code(400); exit('Invalid request. Go back and try again.'); }
    $do = $_POST['do'] ?? '';
    if ($do === 'lookup') {
        if (!rate_ok($db, 'lk:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 5, 900)) $err = 'Too many attempts. Please wait a few minutes and try again.';
        else {
            $f = find_booking($db, (string)($_POST['ref'] ?? ''), (string)($_POST['email'] ?? ''));
            if ($f) { session_regenerate_id(true); $_SESSION['manage'] = ['id' => (int)$f['id'], 'at' => time()]; header('Location: manage.php'); exit; }
            $err = "We couldn't find a reservation matching those details.";
        }
    } elseif ($b) {
        if ($do === 'logout') { unset($_SESSION['manage']); header('Location: manage.php'); exit; }
        elseif ($do === 'cancel' && !empty($_POST['sure'])) {
            if (can_change($b)) {
                $fee = cancel_booking($db, $b);
                $ok = 'Your reservation is cancelled. ' . ($fee > 0 ? 'A cancellation fee of ' . money2($fee) . ' applies.' : 'No fee applies.');
                $b = load_booking($db, (int)$b['id']);
            } else $err = 'This reservation can no longer be cancelled online.';
        } elseif ($do === 'modify') {
            [$s, $e1] = parse_stay(['check_in' => $_POST['check_in'] ?? '', 'check_out' => $_POST['check_out'] ?? '', 'adults' => $b['adults']]);
            if (!can_change($b)) $err = 'This reservation can no longer be changed online.';
            elseif ($e1) $err = $e1;
            else {
                [$q, $e2] = plan_change($db, $b, $s);
                if ($e2) $err = $e2;
                elseif (empty($_POST['confirm'])) $preview = ['s' => $s, 'q' => $q];
                else {
                    try { $q = apply_dates($db, $b, $s); $ok = 'Your dates are updated. New total: ' . money2($q['total']) . '.'; $b = load_booking($db, (int)$b['id']); }
                    catch (RuntimeException $ex) { $err = $ex->getMessage(); }
                    catch (Throwable $ex) { error_log($ex->getMessage()); $err = 'Something went wrong. Please try again.'; }
                }
            }
        } elseif ($do === 'inquiry') {
            $msg = trim((string)($_POST['message'] ?? ''));
            $topic = in_array($_POST['topic'] ?? '', ['Room request', 'Arrival and transfer', 'Dining and spa', 'Billing', 'Other'], true) ? $_POST['topic'] : 'Other';
            if (mb_strlen($msg) < 5 || mb_strlen($msg) > 1000) $err = 'Please write a message of 5 to 1000 characters.';
            else { submit_inquiry($db, $b, $topic, $msg); $ok = 'Thank you. Your message is with our front desk and we will reply by email.'; }
        }
    }
}

$title = 'Manage Reservation';
$page = 'manage.php';
$css = ['assets/css/booking.css', 'assets/css/manage.css'];
$tomorrow = date('Y-m-d', strtotime('+1 day'));
include __DIR__ . '/includes/header.php';
?>
<div class="book">
  <section>
  <h1>Manage reservation</h1>
  <?php if ($ok): ?><p class="alert" role="status"><?= e($ok) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="alert" role="alert"><?= e($err) ?></p><?php endif; ?>

  <?php if (!$b): ?>
    <p>Enter your booking ID and the email you booked with.</p>
    <form method="post" action="manage.php">
      <?= csrf_field() ?><input type="hidden" name="do" value="lookup">
      <div class="row">
        <label class="fld">Booking ID<input name="ref" maxlength="11" required placeholder="MA-XXXXXXXX" autocomplete="off" style="text-transform:uppercase"></label>
        <label class="fld">Email<input type="email" name="email" maxlength="190" required autocomplete="email"></label>
      </div>
      <button class="btn" type="submit">Find my reservation</button>
    </form>
  <?php else:
      $lines = booking_lines($db, (int)$b['id']);
      $changeable = can_change($b);
      $fee = cancel_fee($b); ?>
    <div class="keycard">
      <div class="top">
        <div><span class="label">Booking ID</span><p class="ref"><?= e($b['booking_ref']) ?></p></div>
        <span class="stamp<?= $b['status'] === 'cancelled' ? ' off' : '' ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span>
      </div>
      <dl class="kv">
        <div><dt>Guest</dt><dd><?= e($b['first_name'] . ' ' . $b['last_name']) ?></dd></div>
        <div><dt>Room</dt><dd><?= e($b['room_name']) ?></dd></div>
        <div><dt>Check in</dt><dd><?= e($b['check_in']) ?></dd></div>
        <div><dt>Check out</dt><dd><?= e($b['check_out']) ?></dd></div>
        <div><dt>Guests</dt><dd><?= (int)$b['adults'] ?> adult<?= $b['adults'] > 1 ? 's' : '' ?></dd></div>
      </dl>
      <ul class="lines">
        <li><span>Room, <?= (int)$b['nights'] ?> night<?= $b['nights'] > 1 ? 's' : '' ?></span><b><?= money2($b['subtotal']) ?></b></li>
        <?php foreach ($lines as $l): ?><li><span><?= e($l['name']) ?></span><b><?= money2($l['line_total']) ?></b></li><?php endforeach; ?>
        <li><span>Service charge</span><b><?= money2($b['service_charge']) ?></b></li>
        <li><span>Tax</span><b><?= money2($b['tax']) ?></b></li>
        <?php if ((float)$b['cancellation_fee'] > 0): ?><li><span>Cancellation fee</span><b><?= money2($b['cancellation_fee']) ?></b></li><?php endif; ?>
        <li class="tot"><span>Total</span><b><?= money2($b['total']) ?></b></li>
      </ul>
    </div>

    <?php if ($preview): ?>
      <div class="panel">
        <h2>Confirm new dates</h2>
        <p><?= e($preview['s']['check_in']) ?> to <?= e($preview['s']['check_out']) ?>, <?= (int)$preview['s']['nights'] ?> night<?= $preview['s']['nights'] > 1 ? 's' : '' ?>.
           New total <b><?= money2($preview['q']['total']) ?></b>, <?= $preview['q']['total'] >= $b['total'] ? 'an increase' : 'a decrease' ?> of <?= money2(abs($preview['q']['total'] - $b['total'])) ?>.</p>
        <form method="post" action="manage.php">
          <?= csrf_field() ?><input type="hidden" name="do" value="modify"><input type="hidden" name="confirm" value="1">
          <input type="hidden" name="check_in" value="<?= e($preview['s']['check_in']) ?>"><input type="hidden" name="check_out" value="<?= e($preview['s']['check_out']) ?>">
          <button class="btn" type="submit">Confirm change</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($changeable): ?>
      <div class="panel">
        <h2>Change dates</h2>
        <form method="post" action="manage.php">
          <?= csrf_field() ?><input type="hidden" name="do" value="modify">
          <div class="row">
            <label class="fld">New check in<input type="date" name="check_in" min="<?= $tomorrow ?>" value="<?= e($b['check_in']) ?>" required></label>
            <label class="fld">New check out<input type="date" name="check_out" min="<?= $tomorrow ?>" value="<?= e($b['check_out']) ?>" required></label>
          </div>
          <button class="btn" type="submit">See new price</button>
        </form>
      </div>
      <div class="panel">
        <h2>Cancel reservation</h2>
        <p><?= $fee > 0 ? 'Free cancellation has ended. A fee of ' . money2($fee) . ' (the first night) applies.' : 'Free cancellation until ' . e(date('j M Y, H:i', free_until($b))) . '.' ?></p>
        <form method="post" action="manage.php">
          <?= csrf_field() ?><input type="hidden" name="do" value="cancel">
          <label class="fld" style="grid-auto-flow:column;justify-content:start;align-items:center;gap:.8rem;text-transform:none;letter-spacing:0;margin-top:1rem"><input type="checkbox" name="sure" value="1" required> I understand this cannot be undone.</label>
          <button class="btn" type="submit">Cancel my reservation</button>
        </form>
      </div>
    <?php else: ?>
      <p class="alert">This reservation can't be changed online. Send a message below and we'll help.</p>
    <?php endif; ?>

    <div class="panel">
      <h2>Send an inquiry</h2>
      <form method="post" action="manage.php">
        <?= csrf_field() ?><input type="hidden" name="do" value="inquiry">
        <label class="fld">Topic<select name="topic"><?php foreach (['Room request', 'Arrival and transfer', 'Dining and spa', 'Billing', 'Other'] as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
        <label class="fld" style="margin-top:1rem">Message<textarea name="message" rows="4" maxlength="1000" required></textarea></label>
        <button class="btn" type="submit">Send message</button>
      </form>
    </div>
    <form method="post" action="manage.php" style="margin-top:2rem"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button class="linkbtn" type="submit">Look up a different reservation</button></form>
  <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
