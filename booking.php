<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';
require __DIR__ . '/includes/booking-engine.php';

function money2($n): string { return (CURRENCY === 'USD' ? '$' : CURRENCY . ' ') . number_format((float)$n, 2); }
function summary(array $room, array $stay, array $q): void { ?>
  <aside class="sum" id="sum" aria-live="polite" data-room="<?= (int)$room['id'] ?>" data-in="<?= e($stay['check_in']) ?>" data-out="<?= e($stay['check_out']) ?>"
         data-adults="<?= (int)$stay['adults'] ?>" data-nights="<?= (int)$stay['nights'] ?>" data-room-total="<?= $q['room_total'] ?>"
         data-svc="<?= SERVICE_RATE ?>" data-tax="<?= TAX_RATE ?>" data-sym="<?= e(CURRENCY === 'USD' ? '$' : CURRENCY . ' ') ?>">
    <span class="label">Your stay</span>
    <h3><?= e($room['name']) ?></h3>
    <p><?= e($stay['check_in']) ?> to <?= e($stay['check_out']) ?>, <?= (int)$stay['adults'] ?> adult<?= $stay['adults'] > 1 ? 's' : '' ?></p>
    <ul id="sum-body">
      <li><span>Room, <?= (int)$q['nights'] ?> night<?= $q['nights'] > 1 ? 's' : '' ?></span><b><?= money2($q['room_total']) ?></b></li>
      <?php foreach ($q['addons'] as $a): ?><li><span><?= e($a['name']) ?></span><b><?= money2($a['amount']) ?></b></li><?php endforeach; ?>
      <li><span>Service charge</span><b><?= money2($q['service']) ?></b></li>
      <li><span>Tax</span><b><?= money2($q['tax']) ?></b></li>
      <li class="tot"><span>Total</span><b><?= money2($q['total']) ?></b></li>
    </ul>
  </aside>
<?php }

start_booking();
try { $db = db(); $db->query('SELECT 1'); }
catch (Throwable $x) { http_response_code(503); exit('Booking is temporarily unavailable.'); }

$err = '';
$step = (string)($_GET['step'] ?? '1');
if (!in_array($step, ['1', '2', '3', 'done'], true)) $step = '1';

// Soft hold: selections expire after HOLD_MINUTES.
if (!empty($_SESSION['booking']['stay']) && time() - ($_SESSION['booking']['started_at'] ?? 0) > HOLD_MINUTES * 60) {
    unset($_SESSION['booking']);
    start_booking();
    $err = 'Your selection timed out. Please choose again.';
    $step = '1';
}
$stay = $_SESSION['booking']['stay'] ?? [];
$room = $stay ? get_room($db, (int)$stay['room_id']) : null;
if ($stay && !$room) { $stay = []; $_SESSION['booking']['stay'] = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { http_response_code(400); exit('Invalid request. Go back and try again.'); }
    $do = $_POST['do'] ?? '';
    if ($do === 'room') {
        [$s, $e1] = parse_stay($_POST);
        $r = $s ? get_room($db, (int)($_POST['room'] ?? 0)) : null;
        if ($e1) $err = $e1;
        elseif (!$r) $err = 'Please choose a room.';
        elseif ((int)$r['total_units'] - booked_units($db, (int)$r['id'], $s['check_in'], $s['check_out']) < 1 || (int)$r['max_adults'] < $s['adults']) $err = 'That room is no longer available for those dates.';
        else {
            $s['room_id'] = (int)$r['id'];
            $_SESSION['booking'] = ['stay' => $s, 'addons' => [], 'guest' => [], 'started_at' => time()];
            header('Location: booking.php?step=2'); exit;
        }
        $step = '1';
    } elseif ($do === 'addons' && $stay) {
        $_SESSION['booking']['addons'] = array_map('intval', (array)($_POST['addons'] ?? []));
        $_SESSION['booking']['addons_done'] = true;
        header('Location: booking.php?step=3'); exit;
    } elseif ($do === 'confirm' && $stay && $room) {
        $g = ['first' => trim((string)($_POST['first'] ?? '')), 'last' => trim((string)($_POST['last'] ?? '')),
              'email' => trim((string)($_POST['email'] ?? '')), 'phone' => trim((string)($_POST['phone'] ?? '')),
              'arrive' => trim((string)($_POST['arrive'] ?? '')), 'req' => trim((string)($_POST['req'] ?? ''))];
        [$s, $e1] = parse_stay($stay);
        if ($e1) $err = $e1;
        elseif ($g['first'] === '' || $g['last'] === '' || mb_strlen($g['first']) > 80 || mb_strlen($g['last']) > 80) $err = 'Please enter your first and last name.';
        elseif (!filter_var($g['email'], FILTER_VALIDATE_EMAIL) || strlen($g['email']) > 190) $err = 'Please enter a valid email address.';
        elseif (!preg_match('/^[0-9+()\- ]{0,40}$/', $g['phone'])) $err = 'Phone numbers can only contain digits, spaces and + ( ) -.';
        elseif (empty($_POST['terms'])) $err = 'Please accept the booking terms.';
        else {
            $g['req'] = mb_substr(trim(($g['arrive'] !== '' ? 'Arrival: ' . mb_substr($g['arrive'], 0, 20) . '. ' : '') . $g['req']), 0, 1000);
            try {
                $ref = create_booking($db, $room, $stay, $_SESSION['booking']['addons'] ?? [], $g);
                $_SESSION['flash_ref'] = $ref;
                unset($_SESSION['booking']);
                header('Location: booking.php?step=done'); exit;
            } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
            catch (Throwable $ex) { error_log($ex->getMessage()); $err = 'Something went wrong. Please try again.'; }
        }
        $step = '3';
    }
}

// Step guards
if (in_array($step, ['2', '3'], true) && !$stay) { header('Location: booking.php?step=1'); exit; }
if ($step === '3' && empty($_SESSION['booking']['addons_done'])) { header('Location: booking.php?step=2'); exit; }

$title = 'Reserve';
$page = 'booking.php';
$css = 'assets/css/booking.css';
$js = 'assets/js/booking.js';
$cur = $step === 'done' ? 4 : (int)$step;
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
include __DIR__ . '/includes/header.php';
?>
<div class="book">
  <?php if ($cur <= 3): ?>
  <ol class="steps" aria-label="Booking progress">
    <?php foreach (['Stay', 'Extras', 'Checkout'] as $i => $l): $n = $i + 1; ?>
      <li class="<?= $n < $cur ? 'done' : ($n === $cur ? 'now' : '') ?>"<?= $n === $cur ? ' aria-current="step"' : '' ?>><span><?= e($l) ?></span></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <section>
  <?php if ($err): ?><p class="alert" role="alert"><?= e($err) ?></p><?php endif; ?>

  <?php if ($step === '1'):
      $in = $_POST ?: ($stay ?: $_GET);
      [$s, $e2] = parse_stay($in);
      $rooms = $s ? available_rooms($db, $s) : [];
      $vin = e($in['check_in'] ?? $today); $vout = e($in['check_out'] ?? $tomorrow); $vad = (int)($in['adults'] ?? 2); ?>
    <h1>Choose your stay</h1>
    <form method="get" action="booking.php" class="row">
      <input type="hidden" name="step" value="1">
      <label class="fld">Check in<input type="date" name="check_in" min="<?= $today ?>" value="<?= $vin ?>" required></label>
      <label class="fld">Check out<input type="date" name="check_out" min="<?= $tomorrow ?>" value="<?= $vout ?>" required></label>
      <label class="fld">Adults<select name="adults"><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>"<?= $i === $vad ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
      <button class="btn" type="submit">Show rooms</button>
    </form>
    <?php if ($e2 && ($_GET || $_POST) && !$err): ?><p class="alert" role="alert"><?= e($e2) ?></p><?php endif; ?>
    <?php if ($s): ?>
      <?php if (!$rooms): ?><p class="alert">No rooms are free for those dates. Try different dates.</p>
      <?php else: ?>
      <form method="post" action="booking.php?step=1">
        <?= csrf_field() ?><input type="hidden" name="do" value="room">
        <input type="hidden" name="check_in" value="<?= e($s['check_in']) ?>"><input type="hidden" name="check_out" value="<?= e($s['check_out']) ?>"><input type="hidden" name="adults" value="<?= (int)$s['adults'] ?>">
        <p class="label" style="margin:1.5rem 0 1rem"><?= (int)$s['nights'] ?> night<?= $s['nights'] > 1 ? 's' : '' ?>, <?= count($rooms) ?> room type<?= count($rooms) > 1 ? 's' : '' ?> available</p>
        <?php foreach ($rooms as $r): ?>
          <label class="opt"><input type="radio" name="room" value="<?= (int)$r['id'] ?>" required<?= (int)($stay['room_id'] ?? 0) === (int)$r['id'] ? ' checked' : '' ?>>
            <div><h3><?= e($r['name']) ?> <?php if ($r['left'] <= 2): ?><span class="badge"><?= (int)$r['left'] ?> left</span><?php endif; ?></h3>
              <p><?= e($r['size_sqm']) ?> m², <?= e($r['bed_config']) ?>, <?= e($r['view']) ?></p>
              <p><span class="price"><?= money($r['base_price']) ?></span> a night, <?= money($r['base_price'] * $s['nights']) ?> for the stay</p></div></label>
        <?php endforeach; ?>
        <button class="btn" type="submit">Continue to extras</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($step === '2'):
      $selected = $_SESSION['booking']['addons'] ?? [];
      $cats = ['bundle' => 'Set deals', 'dining' => 'Dining', 'spa' => 'Spa', 'experience' => 'Experiences', 'transport' => 'Transport'];
      $all = $db->query("SELECT * FROM addons WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
      $rule = ['per_stay' => 'per stay', 'per_night' => 'per night', 'per_person' => 'per person', 'per_person_per_night' => 'per person, per night'];
      $q = quote($db, $room, $stay, $selected); ?>
    <h1>Make it yours</h1>
    <form method="post" action="booking.php?step=2" id="addons-form">
      <?= csrf_field() ?><input type="hidden" name="do" value="addons">
      <?php foreach ($cats as $key => $name): $items = array_filter($all, fn($a) => $a['category'] === $key); if (!$items) continue; ?>
        <h2 class="label cat"><?= e($name) ?></h2>
        <?php foreach ($items as $a): ?>
          <label class="opt"><input type="checkbox" name="addons[]" value="<?= (int)$a['id'] ?>" data-price="<?= e($a['price']) ?>" data-type="<?= e($a['pricing_type']) ?>" data-name="<?= e($a['name']) ?>"<?= in_array((int)$a['id'], $selected, true) ? ' checked' : '' ?>>
            <div><h3><?= e($a['name']) ?> <?php if ($a['savings_note']): ?><span class="badge">Best value, <?= e($a['savings_note']) ?></span><?php endif; ?></h3>
              <p><?= e($a['description']) ?></p>
              <p><span class="price"><?= money($a['price']) ?></span> <?= e($rule[$a['pricing_type']]) ?></p></div></label>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <p><a href="booking.php?step=1">Back</a></p>
      <button class="btn" type="submit">Continue to checkout</button>
    </form>

  <?php elseif ($step === '3'): $q = quote($db, $room, $stay, $_SESSION['booking']['addons'] ?? []); ?>
    <h1>Checkout</h1>
    <form method="post" action="booking.php?step=3">
      <?= csrf_field() ?><input type="hidden" name="do" value="confirm">
      <div class="row">
        <label class="fld">First name<input name="first" maxlength="80" required value="<?= e($_POST['first'] ?? '') ?>" autocomplete="given-name"></label>
        <label class="fld">Last name<input name="last" maxlength="80" required value="<?= e($_POST['last'] ?? '') ?>" autocomplete="family-name"></label>
      </div>
      <div class="row">
        <label class="fld">Email<input type="email" name="email" maxlength="190" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email"></label>
        <label class="fld">Phone<input type="tel" name="phone" maxlength="40" value="<?= e($_POST['phone'] ?? '') ?>" autocomplete="tel"></label>
        <label class="fld">Arrival time<input type="time" name="arrive" value="<?= e($_POST['arrive'] ?? '') ?>"></label>
      </div>
      <label class="fld">Special requests<textarea name="req" rows="3" maxlength="900"><?= e($_POST['req'] ?? '') ?></textarea></label>
      <p class="alert">Payment is collected at the hotel. Free cancellation until <?= (int)FREE_CANCEL_HOURS ?> hours before check-in. No card details are taken here.</p>
      <label class="fld" style="grid-auto-flow:column;justify-content:start;align-items:center;gap:.8rem;text-transform:none;letter-spacing:0;margin:0 0 1.5rem"><input type="checkbox" name="terms" value="1" required> I accept the booking terms and cancellation policy.</label>
      <p><a href="booking.php?step=2">Back</a></p>
      <button class="btn" type="submit">Confirm reservation</button>
    </form>

  <?php else: $ref = $_SESSION['flash_ref'] ?? null; unset($_SESSION['flash_ref']); ?>
    <?php if ($ref): ?>
      <span class="label">Reservation confirmed</span>
      <p class="ref"><?= e($ref) ?></p>
      <p>Keep this booking ID. You'll need it, with your email, to view or change your stay.</p>
      <p><a class="btn" href="index.php">Back to the lobby</a></p>
    <?php else: ?>
      <h1>Nothing to show</h1><p><a class="btn" href="booking.php">Start a reservation</a></p>
    <?php endif; ?>
  <?php endif; ?>
  </section>

  <?php if (in_array($step, ['2', '3'], true)) summary($room, $stay, $q); ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
