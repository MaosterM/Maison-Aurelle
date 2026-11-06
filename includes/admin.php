<?php
declare(strict_types=1);
// Staff sign-in state and the changes only staff can make. Needs reservation.php.

const ADMIN_IDLE_SECONDS = 1800;
const PAYMENT_STATUSES = ['unpaid', 'paid', 'refunded'];
// From each status, where staff may move a booking. Cancelling has its own form, because it charges a fee and emails the guest.
const STATUS_MOVES = ['pending' => ['confirmed'], 'confirmed' => ['completed', 'no_show']];

function admin_signed_in(): bool {
    if (isset($_SESSION['admin']) && time() - (int)$_SESSION['admin'] < ADMIN_IDLE_SECONDS) {
        $_SESSION['admin'] = time();
        return true;
    }
    unset($_SESSION['admin']);
    return false;
}

function is_local_request(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true); }

/** Link to phpMyAdmin, only for someone on the computer running the site. Empty otherwise. */
function phpmyadmin_link(): string {
    if (PHPMYADMIN_URL === '' || !is_local_request()) return '';
    return PHPMYADMIN_URL . (str_contains(PHPMYADMIN_URL, '?') ? '&' : '?') . http_build_query(['route' => '/database/structure', 'db' => DB_NAME]);
}

function admin_log(PDO $db, int $bookingId, array $what): void {
    $db->prepare("INSERT INTO booking_events (booking_id, event_type, payload) VALUES (?, 'modified', ?)")
       ->execute([$bookingId, json_encode(['by' => 'admin'] + $what)]);
}

/** Returns an error message, or '' when the status changed. */
function admin_set_status(PDO $db, array $b, string $to): string {
    if (!in_array($to, STATUS_MOVES[$b['status']] ?? [], true)) return 'A ' . str_replace('_', ' ', $b['status']) . ' booking cannot be changed to that.';
    if (in_array($to, ['completed', 'no_show'], true) && $b['check_in'] > date('Y-m-d')) return 'This stay has not started yet.';
    $db->beginTransaction();
    try {
        $u = $db->prepare('UPDATE bookings SET status = ? WHERE id = ? AND status = ?');
        $u->execute([$to, $b['id'], $b['status']]);
        if ($u->rowCount() === 1) admin_log($db, (int)$b['id'], ['status' => $to]);
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    return $u->rowCount() === 1 ? '' : 'Someone else has just changed this booking. Please check it and try again.';
}

function admin_set_payment(PDO $db, array $b, string $to): string {
    if (!in_array($to, PAYMENT_STATUSES, true)) return 'Please choose a payment status.';
    if ($to === $b['payment_status']) return '';
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE bookings SET payment_status = ? WHERE id = ?')->execute([$to, $b['id']]);
        admin_log($db, (int)$b['id'], ['payment' => $to]);
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    return '';
}

function valid_price(string $s): bool { return (bool)preg_match('/^\d{1,7}(\.\d{1,2})?$/', $s); }

function admin_save_room(PDO $db, int $id, string $price, string $units, bool $active): string {
    if (!valid_price($price) || (float)$price <= 0) return 'Please enter a nightly rate above zero, such as 280 or 280.50.';
    if (!preg_match('/^\d{1,4}$/', $units)) return 'Please enter the number of rooms as a whole number, from 0 to 9999.';
    $db->prepare('UPDATE room_types SET base_price = ?, total_units = ?, is_active = ? WHERE id = ?')->execute([$price, (int)$units, $active ? 1 : 0, $id]);
    return '';
}

function admin_save_addon(PDO $db, int $id, string $price, bool $active): string {
    if (!valid_price($price)) return 'Please enter a price, such as 90 or 90.50.';
    $db->prepare('UPDATE addons SET price = ?, is_active = ? WHERE id = ?')->execute([$price, $active ? 1 : 0, $id]);
    return '';
}
