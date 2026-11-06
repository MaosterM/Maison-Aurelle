<?php
// Two read-only questions:
//   ?month=YYYY-MM&adults=2[&room=3]                 which nights in this month have nothing free?
//   ?check_in=...&check_out=...&adults=2[&room=3]    what is free for this stay?
// The server re-checks availability when a booking is made, so this only guides the date picker.
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/booking-engine.php';
require __DIR__ . '/../includes/reservation.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// On the Manage page, a guest's own booking must not count against their new dates.
$exclude = (!empty($_GET['exclude']) && isset($_SESSION['manage']) && time() - $_SESSION['manage']['at'] < 1800) ? (int)$_SESSION['manage']['id'] : 0;
session_write_close();

$adults = (int)($_GET['adults'] ?? 2);
if ($adults < 1 || $adults > 4) reply(422, ['error' => 'Choose 1 to 4 adults.']);
$roomId = (int)($_GET['room'] ?? 0);

try {
    $db = db();
    if (!rate_ok($db, 'av:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 120, 600)) reply(429, ['error' => 'Too many requests.']);

    $sql = 'SELECT id, name, base_price, total_units, max_adults FROM room_types WHERE is_active = 1 AND max_adults >= ?' . ($roomId ? ' AND id = ?' : '') . ' ORDER BY sort_order';
    $q = $db->prepare($sql);
    $q->execute($roomId ? [$adults, $roomId] : [$adults]);
    $rooms = $q->fetchAll();

    if (is_string($_GET['month'] ?? null)) {
        $start = DateTimeImmutable::createFromFormat('!Y-m', $_GET['month']);
        $today = new DateTimeImmutable('first day of this month midnight');
        if (!$start || $start->format('Y-m') !== $_GET['month'] || $start < $today || $start > $today->modify('+24 months')) reply(422, ['error' => 'Please choose a month from now on.']);
        $next = $start->modify('+1 month');

        $b = $db->prepare("SELECT room_type_id, check_in, check_out FROM bookings WHERE status IN ('pending','confirmed') AND check_in < ? AND check_out > ? AND id <> ?");
        $b->execute([$next->format('Y-m-d'), $start->format('Y-m-d'), $exclude]);
        $used = [];   // $used[date][room] = rooms taken that night
        foreach ($b->fetchAll() as $row) {
            $d = max($row['check_in'], $start->format('Y-m-d'));
            $end = min($row['check_out'], $next->format('Y-m-d'));
            while ($d < $end) {
                $used[$d][$row['room_type_id']] = ($used[$d][$row['room_type_id']] ?? 0) + 1;
                $d = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }
        $soldOut = [];
        for ($day = $start; $day < $next; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $free = false;
            foreach ($rooms as $r) {
                if ((int)$r['total_units'] - ($used[$key][$r['id']] ?? 0) > 0) { $free = true; break; }
            }
            if (!$free) $soldOut[] = $key;
        }
        reply(200, ['month' => $_GET['month'], 'sold_out' => $soldOut]);
    }

    [$stay, $err] = parse_stay(['check_in' => $_GET['check_in'] ?? '', 'check_out' => $_GET['check_out'] ?? '', 'adults' => $adults]);
    if (!$stay) reply(422, ['error' => $err]);
    $free = [];
    foreach ($rooms as $r) {
        $left = (int)$r['total_units'] - booked_units($db, (int)$r['id'], $stay['check_in'], $stay['check_out'], $exclude);
        if ($left > 0) $free[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'rate' => (float)$r['base_price'], 'left' => $left];
    }
    reply(200, ['nights' => $stay['nights'], 'rooms' => $free, 'from' => $free ? money(min(array_column($free, 'rate'))) : null]);
} catch (PDOException $x) {
    error_log('Availability lookup failed.');
    reply(503, ['error' => 'Availability is unavailable right now.']);
}
