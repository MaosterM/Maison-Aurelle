<?php
declare(strict_types=1);

function valid_date(string $d): ?DateTimeImmutable {
    $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return ($x && $x->format('Y-m-d') === $d) ? $x : null;
}

/** Validate dates and guests. Returns [stay|null, error|null]. */
function parse_stay(array $in): array {
    $a = valid_date((string)($in['check_in'] ?? ''));
    $b = valid_date((string)($in['check_out'] ?? ''));
    $adults = (int)($in['adults'] ?? 2);
    if (!$a || !$b) return [null, 'Please choose valid dates.'];
    if ($a < new DateTimeImmutable('today')) return [null, 'Check-in cannot be in the past.'];
    $n = (int)$a->diff($b)->days;
    if ($b <= $a || $n > MAX_NIGHTS) return [null, 'Stays can be 1 to ' . MAX_NIGHTS . ' nights.'];
    if ($adults < 1 || $adults > 4) return [null, 'Choose 1 to 4 adults.'];
    return [['check_in' => $a->format('Y-m-d'), 'check_out' => $b->format('Y-m-d'), 'nights' => $n, 'adults' => $adults], null];
}

function get_room(PDO $db, int $id): ?array {
    $s = $db->prepare('SELECT * FROM room_types WHERE id = ? AND is_active = 1');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Active bookings overlapping [in, out) for a room type. */
function booked_units(PDO $db, int $roomId, string $in, string $out, int $excludeId = 0): int {
    $s = $db->prepare("SELECT COUNT(*) FROM bookings WHERE room_type_id = ? AND status IN ('pending','confirmed') AND check_in < ? AND check_out > ? AND id <> ?");
    $s->execute([$roomId, $out, $in, $excludeId]);
    return (int)$s->fetchColumn();
}

function available_rooms(PDO $db, array $stay): array {
    $rooms = $db->query('SELECT * FROM room_types WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
    foreach ($rooms as &$r) {
        $r['left'] = (int)$r['total_units'] - booked_units($db, (int)$r['id'], $stay['check_in'], $stay['check_out']);
    }
    unset($r);
    return array_values(array_filter($rooms, fn($r) => $r['left'] > 0 && (int)$r['max_adults'] >= $stay['adults']));
}

/** Server-authoritative price. The browser only previews this. */
function quote(PDO $db, array $room, array $stay, array $addonIds): array {
    $n = (int)$stay['nights'];
    $g = (int)$stay['adults'];
    $roomTotal = round((float)$room['base_price'] * $n, 2);
    $lines = [];
    $addTotal = 0.0;
    $ids = array_values(array_unique(array_map('intval', $addonIds)));
    if ($ids) {
        $q = $db->prepare('SELECT id, name, price, pricing_type FROM addons WHERE is_active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $q->execute($ids);
        foreach ($q->fetchAll() as $a) {
            $m = ['per_stay' => 1, 'per_night' => $n, 'per_person' => $g, 'per_person_per_night' => $g * $n][$a['pricing_type']];
            $amt = round((float)$a['price'] * $m, 2);
            $addTotal += $amt;
            $lines[] = ['id' => (int)$a['id'], 'name' => $a['name'], 'qty' => $m, 'unit' => (float)$a['price'], 'amount' => $amt];
        }
    }
    $sub = round($roomTotal + $addTotal, 2);
    $svc = round($sub * SERVICE_RATE, 2);
    $tax = round(($sub + $svc) * TAX_RATE, 2);
    return ['nights' => $n, 'rate' => (float)$room['base_price'], 'room_total' => $roomTotal, 'addons' => $lines,
            'addons_total' => round($addTotal, 2), 'subtotal' => $sub, 'service' => $svc, 'tax' => $tax, 'total' => round($sub + $svc + $tax, 2)];
}

function make_ref(): string {
    $al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0, O, 1, I
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $al[random_int(0, 31)];
    return 'MA-' . $s;
}

/** Locks the room type, re-checks availability, recomputes the price, then inserts. Returns the booking ref. */
function create_booking(PDO $db, array $room, array $stay, array $addonIds, array $g): string {
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM room_types WHERE id = ? FOR UPDATE');
        $lock->execute([$room['id']]);
        $lock->fetchAll();
        if ((int)$room['total_units'] - booked_units($db, (int)$room['id'], $stay['check_in'], $stay['check_out']) < 1) {
            throw new RuntimeException('That room was just taken. Please choose another.');
        }
        $q = quote($db, $room, $stay, $addonIds);
        $db->prepare('INSERT INTO guests (first_name, last_name, email, phone) VALUES (?,?,?,?)')
           ->execute([$g['first'], $g['last'], $g['email'], $g['phone']]);
        $gid = (int)$db->lastInsertId();
        $ins = $db->prepare("INSERT INTO bookings (booking_ref, guest_id, room_type_id, check_in, check_out, nights, adults, status, payment_status, subtotal, addons_total, service_charge, tax, total, currency, special_requests) VALUES (?,?,?,?,?,?,?,'confirmed','unpaid',?,?,?,?,?,?,?)");
        for ($try = 0;; $try++) {
            $ref = make_ref();
            try {
                $ins->execute([$ref, $gid, $room['id'], $stay['check_in'], $stay['check_out'], $stay['nights'], $stay['adults'],
                               $q['room_total'], $q['addons_total'], $q['service'], $q['tax'], $q['total'], CURRENCY, $g['req']]);
                break;
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1062 || $try > 5) throw $e;   // retry only on duplicate ref
            }
        }
        $bid = (int)$db->lastInsertId();
        $al = $db->prepare('INSERT INTO booking_addons (booking_id, addon_id, quantity, unit_price, line_total) VALUES (?,?,?,?,?)');
        foreach ($q['addons'] as $a) $al->execute([$bid, $a['id'], $a['qty'], $a['unit'], $a['amount']]);
        $db->prepare("INSERT INTO booking_events (booking_id, event_type, payload) VALUES (?, 'created', ?)")
           ->execute([$bid, json_encode(['ref' => $ref, 'total' => $q['total']])]);
        $db->commit();
        return $ref;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
