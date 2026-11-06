<?php
// Sets the staff sign-in password: asks for one, hashes it, and saves only the hash in .env.
// Run from the project folder: C:\xampp\php\php.exe tools\set-admin-password.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$env = $argv[1] ?? __DIR__ . '/../.env';
if (!is_file($env)) exit("No .env file found. Copy .env.example to .env first.\n");

echo "Choose a staff password, at least 12 characters. It shows as you type, so check nobody is watching.\nPassword: ";
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12) exit("That is too short, so nothing was changed. Please run this again.\n");
if (strlen($password) > 200) exit("That is too long, so nothing was changed. Please run this again.\n");

$line = "ADMIN_PASSWORD_HASH='" . password_hash($password, PASSWORD_DEFAULT) . "'";
$text = (string)file_get_contents($env);
$text = preg_match('/^ADMIN_PASSWORD_HASH=.*$/m', $text)
    ? preg_replace_callback('/^ADMIN_PASSWORD_HASH=.*$/m', fn() => $line, $text)   // a callback, because the hash contains $ signs
    : rtrim($text) . "\n" . $line . "\n";
if (file_put_contents($env, $text) === false) exit("Could not write to .env, so nothing was changed.\n");

echo "Saved. The username is admin unless ADMIN_USERNAME says otherwise in .env.\nSign in at http://localhost:8000/login.php\n";
