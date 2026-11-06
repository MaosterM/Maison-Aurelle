<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');   // ignore session ids this server didn't issue, so nobody can plant one in a guest's browser
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): bool {
    $token = $_SESSION['csrf'] ?? '';   // a session with no token must never match an empty post
    return $token !== '' && is_string($_POST['csrf'] ?? null) && hash_equals($token, $_POST['csrf']);
}
function start_booking(): void {
    if (empty($_SESSION['booking'])) {
        // Not right after a booking: a double-click's later requests still carry the old id, and must keep their token.
        if (empty($_SESSION['last_booking'])) session_regenerate_id(true);
        $_SESSION['booking'] =['stay' => [], 'addons' => [], 'guest' => [], 'started_at' => time()];
    }
}
