<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/booking-engine.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    [$stay, $err] = parse_stay($_GET);
    $room = $stay ? get_room(db(), (int)($_GET['room'] ?? 0)) : null;
    if (!$stay || !$room) { http_response_code(422); echo json_encode(['error' => $err ?? 'Unknown room.']); exit; }
    echo json_encode(quote(db(), $room, $stay, array_map('intval', (array)($_GET['addons'] ?? []))));
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Quote unavailable.']);
}
