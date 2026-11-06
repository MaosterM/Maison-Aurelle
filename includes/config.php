<?php
declare(strict_types=1);
const HOTEL_NAME = 'Maison Aurelle', CURRENCY = 'USD', SERVICE_RATE = 0.10, TAX_RATE = 0.08,
      FREE_CANCEL_HOURS = 48, HOLD_MINUTES = 15, MAX_NIGHTS = 30, HOTEL_TZ = 'Asia/Singapore';
date_default_timezone_set(HOTEL_TZ);   // "today" and the 15:00 cancellation cut-off follow the hotel's clock, not php.ini
if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');   // another site can't frame the cancel form and trick a guest into clicking it
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'");
}
// Placeholder contact details: the hotel is fictional. 555-01xx and .example are reserved, so they can never reach a real person.
const HOTEL_ADDRESS = '18 Aurelle Quay, Marina Bay, Singapore', HOTEL_PHONE = '+1 202 555 0142',
      HOTEL_EMAIL = 'concierge@maison-aurelle.example', HOTEL_LAT = 1.2826, HOTEL_LNG = 103.8585;
$__env = is_file(__DIR__ . '/../.env') ? (parse_ini_file(__DIR__ . '/../.env') ?: []) : [];
// Real environment variables win over .env.
$__get = fn(string $k, string $default = ''): string => (string)(getenv($k) ?: ($__env[$k] ?? $default));
define('DB_NAME', $__get('DB_NAME', 'maison_aurelle'));
define('DB_DSN', 'mysql:host=' . $__get('DB_HOST', '127.0.0.1') . ';dbname=' . DB_NAME . ';charset=utf8mb4');
define('DB_USER', $__get('DB_USER', 'root'));
define('DB_PASS', $__get('DB_PASS'));
// AI concierge. With no key it answers from the keyword FAQ instead.
define('LLM_API_URL', $__get('LLM_API_URL', 'https://api.anthropic.com/v1/messages'));
define('LLM_API_KEY', $__get('LLM_API_KEY'));
define('LLM_MODEL', $__get('LLM_MODEL', 'claude-opus-5-5'));
// Email. "file" saves each message to storage/mail for development; "smtp" really sends it.
define('MAIL_TRANSPORT', $__get('MAIL_TRANSPORT', 'file'));
define('MAIL_FROM', $__get('MAIL_FROM', HOTEL_EMAIL));
define('FRONT_DESK_EMAIL', $__get('FRONT_DESK_EMAIL', HOTEL_EMAIL));
define('SMTP_HOST', $__get('SMTP_HOST'));
define('SMTP_PORT', (int)$__get('SMTP_PORT', '587'));
define('SMTP_USER', $__get('SMTP_USER'));
define('SMTP_PASS', $__get('SMTP_PASS'));
define('SMTP_SECURE', $__get('SMTP_SECURE', 'starttls'));   // starttls, ssl or none
define('SITE_URL', rtrim($__get('SITE_URL', 'http://localhost:8000'), '/'));   // for links in emails; never taken from the request
// Staff sign-in at login.php. With no hash set, nobody can sign in. Set one with tools/set-admin-password.php.
define('ADMIN_PASSWORD_HASH', $__get('ADMIN_PASSWORD_HASH'));
define('ADMIN_USERNAME', $__get('ADMIN_USERNAME', 'admin'));
define('PHPMYADMIN_URL', $__get('PHPMYADMIN_URL', 'http://localhost/phpmyadmin/index.php'));   // the Database tab's button; shown only on the computer running the site
