<?php
declare(strict_types=1);

function rate_ok(PDO $db, string $key, int $max, int $window): bool {
    $key = substr($key, 0, 120);
    $db->prepare('INSERT INTO rate_limits (bucket_key, hits, window_start) VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE hits = IF(window_start < NOW() - INTERVAL ? SECOND, 1, hits + 1),
                                window_start = IF(window_start < NOW() - INTERVAL ? SECOND, NOW(), window_start)')
       ->execute([$key, $window, $window]);
    $s = $db->prepare('SELECT hits FROM rate_limits WHERE bucket_key = ?');
    $s->execute([$key]);
    return (int)$s->fetchColumn() <= $max;
}
function load_booking(PDO $db, int $id): ?array {
    $s = $db->prepare('SELECT b.*, g.first_name, g.last_name, g.email, r.name AS room_name FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN room_types r ON r.id = b.room_type_id WHERE b.id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function find_booking(PDO $db, string $ref, string $email): ?array {
    $s = $db->prepare('SELECT b.id FROM bookings b JOIN guests g ON g.id = b.guest_id WHERE b.booking_ref = ? AND LOWER(g.email) = LOWER(?)');
    $s->execute([strtoupper(trim($ref)), trim($email)]);
    $id = $s->fetchColumn();
    return $id ? load_booking($db, (int)$id) : null;
}
function booking_lines(PDO $db, int $id): array {
    $s = $db->prepare('SELECT a.name, ba.line_total FROM booking_addons ba JOIN addons a ON a.id = ba.addon_id WHERE ba.booking_id = ?');
    $s->execute([$id]);
    return $s->fetchAll();
}
function addon_ids(PDO $db, int $id): array {
    $s = $db->prepare('SELECT addon_id FROM booking_addons WHERE booking_id = ?');
    $s->execute([$id]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}
function can_change(array $b): bool {
    return in_array($b['status'], ['pending', 'confirmed'], true) && $b['check_in'] > date('Y-m-d');
}
function free_until(array $b): int { return strtotime($b['check_in'] . ' 15:00') - FREE_CANCEL_HOURS * 3600; }
function cancel_fee(array $b): float {
    return time() <= free_until($b) ? 0.0 : round((float)$b['subtotal'] / max(1, (int)$b['nights']), 2);   // late cancel: first night
}
function cancel_booking(PDO $db, array $b): float {
    $fee = cancel_fee($b);
    $db->beginTransaction();
    try {
        $u = $db->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancellation_fee = ? WHERE id = ? AND status IN ('pending','confirmed')");
        $u->execute([$fee, $b['id']]);
        if ($u->rowCount() === 1) {
            $db->prepare("INSERT INTO booking_events (booking_id, event_type, payload) VALUES (?, 'cancelled', ?)")->execute([$b['id'], json_encode(['fee' => $fee])]);
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    return $fee;
}
/** Price a date change without saving. Returns [quote|null, error|null]. */
function plan_change(PDO $db, array $b, array $s): array {
    $room = get_room($db, (int)$b['room_type_id']);
    if (!$room) return [null, 'This room type can no longer be changed online.'];
    if ((int)$room['total_units'] - booked_units($db, (int)$room['id'], $s['check_in'], $s['check_out'], (int)$b['id']) < 1) return [null, 'No room is free for those dates.'];
    return [quote($db, $room, $s, addon_ids($db, (int)$b['id'])), null];
}
function apply_dates(PDO $db, array $b, array $s): array {
    $room = get_room($db, (int)$b['room_type_id']);
    if (!$room) throw new RuntimeException('This room type can no longer be changed online.');
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM room_types WHERE id = ? FOR UPDATE');
        $lock->execute([$room['id']]);
        $lock->fetchAll();
        if ((int)$room['total_units'] - booked_units($db, (int)$room['id'], $s['check_in'], $s['check_out'], (int)$b['id']) < 1) throw new RuntimeException('No room is free for those dates.');
        $q = quote($db, $room, $s, addon_ids($db, (int)$b['id']));
        $db->prepare("UPDATE bookings SET check_in=?, check_out=?, nights=?, subtotal=?, addons_total=?, service_charge=?, tax=?, total=? WHERE id=? AND status IN ('pending','confirmed')")
           ->execute([$s['check_in'], $s['check_out'], $s['nights'], $q['room_total'], $q['addons_total'], $q['service'], $q['tax'], $q['total'], $b['id']]);
        $db->prepare('DELETE FROM booking_addons WHERE booking_id = ?')->execute([$b['id']]);
        $ins = $db->prepare('INSERT INTO booking_addons (booking_id, addon_id, quantity, unit_price, line_total) VALUES (?,?,?,?,?)');
        foreach ($q['addons'] as $a) $ins->execute([$b['id'], $a['id'], $a['qty'], $a['unit'], $a['amount']]);
        $db->prepare("INSERT INTO booking_events (booking_id, event_type, payload) VALUES (?, 'modified', ?)")
           ->execute([$b['id'], json_encode(['from' => [$b['check_in'], $b['check_out']], 'to' => [$s['check_in'], $s['check_out']], 'total' => $q['total']])]);
        $db->commit();
        return $q;
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
function submit_inquiry(PDO $db, array $b, string $topic, string $msg): void {
    $db->prepare('INSERT INTO inquiries (booking_id, name, email, topic, message) VALUES (?,?,?,?,?)')
       ->execute([$b['id'], $b['first_name'] . ' ' . $b['last_name'], $b['email'], $topic, $msg]);
    $db->prepare("INSERT INTO booking_events (booking_id, event_type, payload) VALUES (?, 'inquiry', ?)")->execute([$b['id'], json_encode(['topic' => $topic])]);
}
