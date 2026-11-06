<?php
// Local development only: php -S localhost:8000 router.php
// The PHP built-in server ignores .htaccess, so this applies the same deny rules Apache gets.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#(^|/)\.|^/(includes|database|storage|tools)\b|^/router\.php$#i', $path)) {
    http_response_code(404);
    exit('Not found');
}
return false;   // anything else: let the built-in server handle it as usual
