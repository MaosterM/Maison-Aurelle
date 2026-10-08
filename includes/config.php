<?php
declare(strict_types=1);
const HOTEL_NAME = 'Maison Aurelle', CURRENCY = 'USD', SERVICE_RATE = 0.10, TAX_RATE = 0.08,
      FREE_CANCEL_HOURS = 48, HOLD_MINUTES = 15, MAX_NIGHTS = 30;
$__env = is_file(__DIR__ . '/../.env') ? (parse_ini_file(__DIR__ . '/../.env') ?: []) : [];
define('DB_DSN', 'mysql:host=' . ($__env['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($__env['DB_NAME'] ?? 'maison_aurelle') . ';charset=utf8mb4');
define('DB_USER', $__env['DB_USER'] ?? 'root');
define('DB_PASS', $__env['DB_PASS'] ?? '');
