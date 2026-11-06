<?php
// Front desk: staff manage reservations, inquiries, rooms and add-ons. Sign in at login.php.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';
require __DIR__ . '/includes/booking-engine.php';
require __DIR__ . '/includes/reservation.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/admin.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

const ADMIN_PAGE_SIZE = 25;
const BOOKING_STATUSES = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'];
const INQUIRY_STATUSES = ['new', 'read', 'replied'];
const ACTIVE_STATUSES = ['pending', 'confirmed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) { http_response_code(400); exit('Invalid request. Go back and try again.'); }
if (!admin_signed_in()) { header('Location: login.php'); exit; }

try { $db = db(); $db->query('SELECT 1'); }
catch (Throwable $x) { http_response_code(503); exit('The front desk view is temporarily unavailable.'); }

/** Saves a one-time message and returns to a page, so a reload can't repeat the action. */
function done_to(string $kind, string $message, string $to): void {
    $_SESSION['adm_flash'] = [$kind, $message];
    header('Location: ' . $to);
    exit;
}

$flash = $_SESSION['adm_flash'] ?? null;
unset($_SESSION['adm_flash']);
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = field($_POST, 'do');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($do === 'logout') {
            unset($_SESSION['admin']);
            session_regenerate_id(true);
            header('Location: login.php'); exit;
        } elseif ($do === 'inquiry_status') {
            $status = field($_POST, 'status');
            if (in_array($status, INQUIRY_STATUSES, true)) $db->prepare('UPDATE inquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
            done_to('ok', 'Inquiry updated.', 'admin.php?view=inquiries');
        } elseif ($do === 'room') {
            $err = admin_save_room($db, $id, field($_POST, 'price'), field($_POST, 'units'), !empty($_POST['active']));
            done_to($err ? 'err' : 'ok', $err ?: 'Room saved. New bookings use it now. Existing bookings keep their price unless their dates change.', 'admin.php?view=rooms');
        } elseif ($do === 'addon') {
            $err = admin_save_addon($db, $id, field($_POST, 'price'), !empty($_POST['active']));
            done_to($err ? 'err' : 'ok', $err ?: 'Add-on saved. New bookings use it now. Existing bookings keep their price unless their dates change.', 'admin.php?view=addons');
        } elseif (in_array($do, ['modify', 'cancel', 'status', 'payment'], true)) {
            $b = load_booking($db, $id);
            $to = 'admin.php?view=bookings&id=' . $id;
            if (!$b) done_to('err', 'That booking no longer exists.', 'admin.php?view=bookings');
            $active = in_array($b['status'], ACTIVE_STATUSES, true);
            if ($do === 'status') {
                $err = admin_set_status($db, $b, field($_POST, 'status'));
                done_to($err ? 'err' : 'ok', $err ?: 'Status updated.', $to);
            } elseif ($do === 'payment') {
                $err = admin_set_payment($db, $b, field($_POST, 'payment'));
                done_to($err ? 'err' : 'ok', $err ?: 'Payment status updated.', $to);
            } elseif ($do === 'cancel') {
                if (!$active) done_to('err', 'This booking is not active, so it cannot be cancelled.', $to);
                if (empty($_POST['sure'])) done_to('err', 'Please tick the box to confirm the cancellation.', $to);
                $fee = cancel_booking($db, $b, !empty($_POST['waive']) ? 0.0 : null, 'admin');
                mail_booking($db, $id, 'cancelled');
                done_to('ok', 'Booking cancelled. ' . ($fee > 0 ? 'A fee of ' . money2($fee) . ' applies.' : 'No fee applies.') . ' The guest has been told by email.', $to);
            } else {
                [$s, $e1] = parse_stay(['check_in' => field($_POST, 'new_in'), 'check_out' => field($_POST, 'new_out'), 'adults' => $b['adults']]);
                if (!$active) done_to('err', 'This booking is not active, so its dates cannot be changed.', $to);
                if ($e1) done_to('err', $e1, $to);
                [$q, $e2] = plan_change($db, $b, $s);
                if ($e2) done_to('err', $e2, $to);
                if (empty($_POST['confirm'])) {
                    $preview = ['s' => $s, 'q' => $q];   // show the new total first; nothing is saved yet
                } else {
                    $q = apply_dates($db, $b, $s, 'admin');
                    mail_booking($db, $id, 'changed');
                    done_to('ok', 'Dates updated. New total: ' . money2($q['total']) . '. The guest has been told by email.', $to);
                }
            }
        }
    } catch (PDOException $ex) {   // first: it is also a RuntimeException, and its message must never reach the page
        error_log('Front desk: a change could not be saved.');
        done_to('err', 'That could not be saved. Please try again.', 'admin.php');
    } catch (RuntimeException $ex) {
        done_to('err', $ex->getMessage(), 'admin.php?view=bookings&id=' . $id);
    }
}

$views = ['bookings' => 'Bookings', 'inquiries' => 'Inquiries', 'rooms' => 'Rooms', 'addons' => 'Add-ons', 'database' => 'Database'];
$view = isset($views[field($_GET, 'view')]) ? field($_GET, 'view') : 'bookings';
$q = trim(mb_substr(field($_GET, 'q'), 0, 100));
$statusFilter = field($_GET, 'status');
$pageNo = max(1, (int)($_GET['page'] ?? 1));
$offset = ($pageNo - 1) * ADMIN_PAGE_SIZE;
$like = '%' . addcslashes($q, '%_\\') . '%';

/** Runs a list query. LIMIT and OFFSET must be bound as integers. */
function fetch_page(PDO $db, string $sql, array $params, int $offset): array {
    $s = $db->prepare($sql . ' LIMIT ? OFFSET ?');
    $n = 1;
    foreach ($params as $p) $s->bindValue($n++, $p);
    $s->bindValue($n++, ADMIN_PAGE_SIZE + 1, PDO::PARAM_INT);   // one extra row shows whether there is a next page
    $s->bindValue($n, $offset, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll();
}
function page_link(array $over): string {
    return 'admin.php?' . http_build_query(array_filter($over + ['view' => $_GET['view'] ?? null, 'q' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null], fn($v) => is_scalar($v) && $v !== ''));
}
/** A history line: what happened, and whether staff did it. */
function event_line(array $h): string {
    $p = json_decode((string)$h['payload'], true) ?: [];
    $what = ucfirst($h['event_type']);
    if (isset($p['status'])) $what = 'Status set to ' . str_replace('_', ' ', $p['status']);
    elseif (isset($p['payment'])) $what = 'Payment set to ' . $p['payment'];
    return $what . (($p['by'] ?? '') === 'admin' ? ' by staff' : '') . ', ' . $h['created_at'];
}

$detail = null;
if ($view === 'bookings' && !empty($_GET['id'])) {
    $s = $db->prepare('SELECT b.*, g.first_name, g.last_name, g.email, g.phone, r.name AS room_name FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN room_types r ON r.id = b.room_type_id WHERE b.id = ?');
    $s->execute([(int)$_GET['id']]);
    $detail = $s->fetch() ?: null;
}

$stats = [
    'Arrivals in the next 7 days' => (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status IN ('pending','confirmed') AND check_in BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY")->fetchColumn(),
    'Active reservations' => (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status IN ('pending','confirmed') AND check_out >= CURDATE()")->fetchColumn(),
    'New inquiries' => (int)$db->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn(),
];

$rows = [];
if (!$detail) {
    if ($view === 'bookings') {
        $where = [];
        $params = [];
        if (in_array($statusFilter, BOOKING_STATUSES, true)) { $where[] = 'b.status = ?'; $params[] = $statusFilter; }
        if ($q !== '') { $where[] = "(b.booking_ref LIKE ? OR g.email LIKE ? OR CONCAT(g.first_name, ' ', g.last_name) LIKE ?)"; array_push($params, $like, $like, $like); }
        $rows = fetch_page($db, 'SELECT b.id, b.booking_ref, b.check_in, b.check_out, b.nights, b.adults, b.status, b.total, g.first_name, g.last_name, g.email, r.name AS room_name
            FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN room_types r ON r.id = b.room_type_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY b.check_in DESC, b.id DESC', $params, $offset);
    } elseif ($view === 'inquiries') {
        $where = [];
        $params = [];
        if (in_array($statusFilter, INQUIRY_STATUSES, true)) { $where[] = 'i.status = ?'; $params[] = $statusFilter; }
        if ($q !== '') { $where[] = '(i.name LIKE ? OR i.email LIKE ? OR i.message LIKE ?)'; array_push($params, $like, $like, $like); }
        $rows = fetch_page($db, 'SELECT i.*, b.booking_ref, b.id AS bid FROM inquiries i LEFT JOIN bookings b ON b.id = i.booking_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.created_at DESC, i.id DESC', $params, $offset);
    } elseif ($view === 'rooms') {
        $rows = $db->query('SELECT id, name, tier, base_price, total_units, is_active FROM room_types ORDER BY sort_order')->fetchAll();
    } elseif ($view === 'addons') {
        $rows = $db->query('SELECT id, name, category, price, pricing_type, is_active FROM addons ORDER BY sort_order')->fetchAll();
    }
}
$paged = in_array($view, ['bookings', 'inquiries'], true);
$hasNext = $paged && count($rows) > ADMIN_PAGE_SIZE;
if ($paged) $rows = array_slice($rows, 0, ADMIN_PAGE_SIZE);

$title = 'Front desk';
$page = 'admin.php';
$css = ['assets/css/booking.css', 'assets/css/admin.css'];
$hideChat = true;
$noindex = true;
include __DIR__ . '/includes/header.php';
?>
<div class="adm">
  <div class="adm-top">
    <h1>Front desk</h1>
    <div class="adm-top-acts">
      <a class="btn btn--sm" href="booking.php">New reservation</a>
      <a class="btn btn--sm" href="index.php">View site</a>
      <form method="post" action="admin.php"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button class="btn btn--sm" type="submit">Sign out</button></form>
    </div>
  </div>

  <?php if ($flash): ?><p class="alert" role="<?= $flash[0] === 'err' ? 'alert' : 'status' ?>"><?= e($flash[1]) ?></p><?php endif; ?>

  <dl class="adm-stats">
    <?php foreach ($stats as $label => $n): ?><div><dt><?= e($label) ?></dt><dd><?= (int)$n ?></dd></div><?php endforeach; ?>
  </dl>

  <nav class="adm-tabs" aria-label="Front desk sections">
    <?php foreach ($views as $key => $label): ?><a href="admin.php?view=<?= e($key) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </nav>

  <?php if ($detail): $isActive = in_array($detail['status'], ACTIVE_STATUSES, true); $moves = STATUS_MOVES[$detail['status']] ?? []; $detailId = (int)$detail['id']; $formUrl = 'admin.php?view=bookings&id=' . $detailId; ?>
    <p><a href="admin.php?view=bookings">Back to bookings</a></p>
    <h2><?= e($detail['booking_ref']) ?></h2>
    <dl class="kv">
      <div><dt>Status</dt><dd><?= e(str_replace('_', ' ', $detail['status'])) ?></dd></div>
      <div><dt>Payment</dt><dd><?= e($detail['payment_status']) ?></dd></div>
      <div><dt>Guest</dt><dd><?= e($detail['first_name'] . ' ' . $detail['last_name']) ?></dd></div>
      <div><dt>Email</dt><dd><a href="mailto:<?= e($detail['email']) ?>"><?= e($detail['email']) ?></a></dd></div>
      <div><dt>Phone</dt><dd><?= e($detail['phone'] ?: 'Not given') ?></dd></div>
      <div><dt>Room</dt><dd><?= e($detail['room_name']) ?></dd></div>
      <div><dt>Check in</dt><dd><?= e(nice_date($detail['check_in'])) ?></dd></div>
      <div><dt>Check out</dt><dd><?= e(nice_date($detail['check_out'])) ?></dd></div>
      <div><dt>Guests</dt><dd><?= (int)$detail['adults'] ?> adult<?= $detail['adults'] > 1 ? 's' : '' ?></dd></div>
      <div><dt>Total</dt><dd><?= money2($detail['total']) ?></dd></div>
      <?php if ((float)$detail['cancellation_fee'] > 0): ?><div><dt>Cancellation fee</dt><dd><?= money2($detail['cancellation_fee']) ?></dd></div><?php endif; ?>
    </dl>

    <h3>Manage this booking</h3>
    <div class="adm-cards">
      <?php if ($isActive): ?>
        <div class="adm-card">
          <h4>Change dates</h4>
          <?php if ($preview): $diff = round($preview['q']['total'] - (float)$detail['total'], 2); ?>
            <p class="alert" role="status"><?= e(nice_date($preview['s']['check_in'], 'j M')) ?> to <?= e(nice_date($preview['s']['check_out'], 'j M Y')) ?>, <?= (int)$preview['s']['nights'] ?> night<?= $preview['s']['nights'] > 1 ? 's' : '' ?>. New total <b><?= money2($preview['q']['total']) ?></b><?= $diff == 0 ? ', the same as now' : ', ' . ($diff > 0 ? 'an increase' : 'a decrease') . ' of ' . money2(abs($diff)) ?>. Nothing is saved until you confirm.</p>
            <form method="post" action="<?= e($formUrl) ?>" class="adm-inline">
              <?= csrf_field() ?><input type="hidden" name="do" value="modify"><input type="hidden" name="id" value="<?= $detailId ?>"><input type="hidden" name="confirm" value="1">
              <input type="hidden" name="new_in" value="<?= e($preview['s']['check_in']) ?>"><input type="hidden" name="new_out" value="<?= e($preview['s']['check_out']) ?>">
              <button class="btn btn--sm" type="submit">Confirm new dates</button>
              <a href="<?= e($formUrl) ?>">Keep current dates</a>
            </form>
          <?php else: ?>
            <form method="post" action="<?= e($formUrl) ?>">
              <?= csrf_field() ?><input type="hidden" name="do" value="modify"><input type="hidden" name="id" value="<?= $detailId ?>">
              <div class="row">
                <label class="fld">New check in<input type="date" name="new_in" min="<?= e(date('Y-m-d')) ?>" value="<?= e($detail['check_in']) ?>" required></label>
                <label class="fld">New check out<input type="date" name="new_out" value="<?= e($detail['check_out']) ?>" required></label>
              </div>
              <button class="btn btn--sm" type="submit">See the new total</button>
            </form>
          <?php endif; ?>
        </div>
        <?php if ($moves): ?>
          <div class="adm-card">
            <h4>Status</h4>
            <form method="post" action="<?= e($formUrl) ?>" class="adm-inline">
              <?= csrf_field() ?><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= $detailId ?>">
              <label class="fld">Set status to
                <select name="status"><?php foreach ($moves as $m): ?><option value="<?= e($m) ?>"><?= e(ucfirst(str_replace('_', ' ', $m))) ?></option><?php endforeach; ?></select>
              </label>
              <button class="btn btn--sm" type="submit">Save</button>
            </form>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="adm-card">
        <h4>Payment</h4>
        <form method="post" action="<?= e($formUrl) ?>" class="adm-inline">
          <?= csrf_field() ?><input type="hidden" name="do" value="payment"><input type="hidden" name="id" value="<?= $detailId ?>">
          <label class="fld">Payment status
            <select name="payment"><?php foreach (PAYMENT_STATUSES as $p): ?><option value="<?= e($p) ?>"<?= $detail['payment_status'] === $p ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option><?php endforeach; ?></select>
          </label>
          <button class="btn btn--sm" type="submit">Save</button>
        </form>
      </div>
      <?php if ($isActive): $fee = cancel_fee($detail); ?>
        <div class="adm-card">
          <h4>Cancel</h4>
          <p><?= $fee > 0 ? 'The free cancellation period has ended. The fee is ' . money2($fee) . ', the first night.' : 'This is still free to cancel.' ?> The guest is told by email.</p>
          <form method="post" action="<?= e($formUrl) ?>">
            <?= csrf_field() ?><input type="hidden" name="do" value="cancel"><input type="hidden" name="id" value="<?= $detailId ?>">
            <?php if ($fee > 0): ?><label class="fld fld--check"><input type="checkbox" name="waive" value="1"> Waive the fee</label><?php endif; ?>
            <label class="fld fld--check"><input type="checkbox" name="sure" value="1" required> I understand this cannot be undone.</label>
            <button class="btn btn--sm" type="submit">Cancel this booking</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <h3>Requests</h3>
    <p class="adm-pre"><?= e($detail['special_requests'] ?: 'None') ?></p>
    <h3>Extras</h3>
    <?php $lines = booking_lines($db, $detailId); ?>
    <?php if ($lines): ?><ul class="adm-list"><?php foreach ($lines as $l): ?><li><?= e($l['name']) ?>, <?= money2($l['line_total']) ?></li><?php endforeach; ?></ul><?php else: ?><p>None</p><?php endif; ?>
    <h3>History</h3>
    <?php $ev = $db->prepare('SELECT event_type, payload, created_at FROM booking_events WHERE booking_id = ? ORDER BY id'); $ev->execute([$detailId]); ?>
    <ul class="adm-list"><?php foreach ($ev->fetchAll() as $h): ?><li><?= e(event_line($h)) ?></li><?php endforeach; ?></ul>

  <?php elseif ($view === 'rooms'): ?>
    <h2>Rooms</h2>
    <p>Changes apply to new bookings straight away. Fewer rooms, or switching one off, only stops new bookings: existing stays are kept.</p>
    <ul class="adm-edit">
      <?php foreach ($rows as $r): ?>
        <li>
          <form method="post" action="admin.php?view=rooms">
            <?= csrf_field() ?><input type="hidden" name="do" value="room"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <b><?= e($r['name']) ?></b>
            <label class="fld">Rate per night<input name="price" inputmode="decimal" required pattern="\d{1,7}(\.\d{1,2})?" value="<?= e($r['base_price']) ?>"></label>
            <label class="fld">Rooms<input type="number" name="units" min="0" max="9999" required value="<?= (int)$r['total_units'] ?>"></label>
            <label class="fld fld--check"><input type="checkbox" name="active" value="1"<?= $r['is_active'] ? ' checked' : '' ?>> On sale</label>
            <button class="btn btn--sm" type="submit">Save<span class="sr-only"> <?= e($r['name']) ?></span></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>

  <?php elseif ($view === 'addons'): ?>
    <h2>Add-ons</h2>
    <p>Changes apply to new bookings straight away. Existing bookings keep their price unless their dates change.</p>
    <ul class="adm-edit">
      <?php foreach ($rows as $r): ?>
        <li>
          <form method="post" action="admin.php?view=addons">
            <?= csrf_field() ?><input type="hidden" name="do" value="addon"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <b><?= e($r['name']) ?><small><?= e(ucfirst($r['category'])) ?>, <?= e(str_replace('_', ' ', $r['pricing_type'])) ?></small></b>
            <label class="fld">Price<input name="price" inputmode="decimal" required pattern="\d{1,7}(\.\d{1,2})?" value="<?= e($r['price']) ?>"></label>
            <label class="fld fld--check"><input type="checkbox" name="active" value="1"<?= $r['is_active'] ? ' checked' : '' ?>> On sale</label>
            <button class="btn btn--sm" type="submit">Save<span class="sr-only"> <?= e($r['name']) ?></span></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>

  <?php elseif ($view === 'database'): ?>
    <h2>Database</h2>
    <?php $counts = [
        'Bookings' => 'SELECT COUNT(*) FROM bookings', 'Guests' => 'SELECT COUNT(*) FROM guests', 'Inquiries' => 'SELECT COUNT(*) FROM inquiries',
        'Rooms' => 'SELECT COUNT(*) FROM room_types', 'Add-ons' => 'SELECT COUNT(*) FROM addons', 'Booking history' => 'SELECT COUNT(*) FROM booking_events',
    ]; ?>
    <dl class="kv">
      <?php foreach ($counts as $label => $sql): ?><div><dt><?= e($label) ?></dt><dd><?= (int)$db->query($sql)->fetchColumn() ?></dd></div><?php endforeach; ?>
    </dl>
    <?php $pma = phpmyadmin_link(); if ($pma): ?>
      <p><a class="btn" href="<?= e($pma) ?>" target="_blank" rel="noopener">Open phpMyAdmin<span class="sr-only"> (opens in a new tab)</span></a></p>
      <p class="alert">phpMyAdmin edits the tables directly and skips this site's checks, including double-booking protection and price rules. Use the other tabs for everyday changes. It needs Apache running in the XAMPP Control Panel.</p>
    <?php else: ?>
      <p class="alert">phpMyAdmin is only offered on the computer running the site. Use the other tabs here.</p>
    <?php endif; ?>

  <?php else: ?>
    <form class="adm-filter" method="get" action="admin.php">
      <input type="hidden" name="view" value="<?= e($view) ?>">
      <label class="fld">Search<input type="search" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="<?= $view === 'bookings' ? 'Booking ID, name or email' : 'Name, email or message' ?>"></label>
      <label class="fld">Status
        <select name="status">
          <option value="">All</option>
          <?php foreach ($view === 'bookings' ? BOOKING_STATUSES : INQUIRY_STATUSES as $st): ?><option value="<?= e($st) ?>"<?= $statusFilter === $st ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $st))) ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn--sm" type="submit">Filter</button>
    </form>

    <?php if (!$rows): ?>
      <p class="alert">Nothing to show.</p>
    <?php elseif ($view === 'bookings'): ?>
      <div class="adm-table" role="region" aria-label="Bookings table, scrolls sideways" tabindex="0">
        <table>
          <thead><tr><th scope="col">Booking ID</th><th scope="col">Guest</th><th scope="col">Room</th><th scope="col">Check in</th><th scope="col">Nights</th><th scope="col">Status</th><th scope="col" class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <th scope="row"><a href="admin.php?view=bookings&amp;id=<?= (int)$r['id'] ?>"><?= e($r['booking_ref']) ?></a></th>
                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?><small><?= e($r['email']) ?></small></td>
                <td><?= e($r['room_name']) ?></td>
                <td><?= e(nice_date($r['check_in'], 'j M Y')) ?></td>
                <td><?= (int)$r['nights'] ?></td>
                <td><span class="tag tag--<?= e($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
                <td class="num"><?= money2($r['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <ul class="adm-inq">
        <?php foreach ($rows as $r): ?>
          <li>
            <div class="adm-inq-head">
              <b><?= e($r['name']) ?></b>
              <span class="tag tag--<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
              <small><?= e($r['topic']) ?>, <?= e($r['created_at']) ?><?php if ($r['booking_ref']): ?>, <a href="admin.php?view=bookings&amp;id=<?= (int)$r['bid'] ?>"><?= e($r['booking_ref']) ?></a><?php endif; ?></small>
            </div>
            <p class="adm-pre"><?= e($r['message']) ?></p>
            <div class="adm-inq-acts">
              <a href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Your inquiry to ' . HOTEL_NAME) ?>">Reply to <?= e($r['email']) ?></a>
              <form method="post" action="admin.php">
                <?= csrf_field() ?><input type="hidden" name="do" value="inquiry_status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <label class="fld">Status
                  <select name="status"><?php foreach (INQUIRY_STATUSES as $st): ?><option value="<?= e($st) ?>"<?= $r['status'] === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?></select>
                </label>
                <button class="btn btn--sm" type="submit">Save</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($pageNo > 1 || $hasNext): ?>
      <nav class="adm-pages" aria-label="Pages">
        <?php if ($pageNo > 1): ?><a href="<?= e(page_link(['page' => $pageNo - 1])) ?>">Newer</a><?php endif; ?>
        <span>Page <?= (int)$pageNo ?></span>
        <?php if ($hasNext): ?><a href="<?= e(page_link(['page' => $pageNo + 1])) ?>">Older</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
