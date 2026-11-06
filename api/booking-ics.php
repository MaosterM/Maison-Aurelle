<?php
// Downloads a calendar file for the booking this visitor has just made, or has looked up on the Manage page.
// The booking comes only from the session, so changing a URL can never reveal someone else's stay.
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/booking-engine.php';
require __DIR__ . '/../includes/reservation.php';
require __DIR__ . '/../includes/mailer.php';

$id = 0;
if (!empty($_GET['latest']) && !empty($_SESSION['last_booking'])) $id = (int)$_SESSION['last_booking'];   // from the confirmation page
elseif (isset($_SESSION['manage']) && time() - $_SESSION['manage']['at'] < 1800) $id = (int)$_SESSION['manage']['id'];

try {
    $db = db();
    $b = $id ? load_booking($db, $id) : null;
    if (!$b) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('Not found'); }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $b['booking_ref'] . '.ics"');
    header('Cache-Control: no-store');
    echo booking_ics($b, booking_sequence($db, $id));
} catch (PDOException $x) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Please try again shortly.');
}
