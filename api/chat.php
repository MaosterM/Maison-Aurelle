<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/booking-engine.php';
require __DIR__ . '/../includes/reservation.php';
require __DIR__ . '/../includes/hotel-info.php';
require __DIR__ . '/../includes/concierge.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chat_out(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') chat_out(405, ['error' => 'Method not allowed.']);
if (!csrf_check()) chat_out(400, ['error' => 'Please refresh the page and try again.']);

if (($_POST['do'] ?? '') === 'reset') {
    unset($_SESSION['chat']);
    chat_out(200, ['ok' => true]);
}

$message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
$message = (string)preg_replace('/[^\P{C}\n\t]/u', '', $message);   // strip control characters
if ($message === '') chat_out(422, ['error' => 'Please type a message.']);
if (mb_strlen($message) > CHAT_MAX_LENGTH) chat_out(422, ['error' => 'Please keep messages under ' . CHAT_MAX_LENGTH . ' characters.']);

// The rate limit needs the database. If it is down, the LLM stays off so its cost can't run unchecked; the FAQ needs neither.
$db = null;
try {
    $db = db();
    if (!rate_ok($db, 'chat:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 20, 600)) {
        chat_out(429, ['error' => 'That is a lot of messages. Please wait a few minutes, or call the front desk on ' . HOTEL_PHONE . '.']);
    }
} catch (PDOException $x) {
    $db = null;
}

// History lives on the server as plain text, so a guest cannot forge what the concierge said.
$history = $_SESSION['chat'] ?? [];
$history[] = ['role' => 'user', 'content' => $message];

session_write_close();   // release the session while waiting, so other requests aren't blocked by its lock

$out = ['cards' => [], 'wish' => []];
$reply = null;
$source = 'faq';
if (LLM_API_KEY !== '' && $db) {
    $reply = concierge_llm_reply($db, $history, $out);
    if ($reply !== null) $source = 'llm';
    else $out = ['cards' => [], 'wish' => []];
}
if ($reply === null) $reply = concierge_faq_reply($db, $message, $out);

session_start();
$history[] = ['role' => 'assistant', 'content' => $reply];
$_SESSION['chat'] = array_slice($history, -CHAT_HISTORY);
if ($out['wish']) {
    $_SESSION['wish'] = array_values(array_unique(array_merge($_SESSION['wish'] ?? [], $out['wish'])));
}

$chips = [];
if (isset($out['cards']['rooms'])) $chips = ['What extras can I add?', 'How do I cancel?'];
elseif (isset($out['cards']['quote'])) $chips = ['What is included in the price?', 'How do I cancel?'];
elseif (!$out['cards']) $chips = ['Show me your rooms', 'Spa and gym hours', 'How do I cancel?'];

chat_out(200, ['reply' => $reply, 'cards' => array_values($out['cards']), 'chips' => $chips, 'source' => $source]);
