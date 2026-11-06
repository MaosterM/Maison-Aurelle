<?php
// Prepares real photos for the site. Put originals in assets/img/originals using the names below, then run:
//   C:\xampp\php\php.exe -d extension=gd tools\optimize-images.php
// Each one is center-cropped to size and saved over its placeholder, with a smaller -800 copy for phones.
// Options for testing: --in=folder and --out=folder
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!extension_loaded('gd')) exit("The image library is off. Run this with: -d extension=gd\n");

$opts = getopt('', ['in::', 'out::']);
$root = dirname(__DIR__) . '/assets/img';
$in = rtrim($opts['in'] ?? $root . '/originals', '/\\');
$out = rtrim($opts['out'] ?? $root, '/\\');

$targets = ['hero' => [1920, 1080], 'spa' => [1200, 1500], 'gym' => [1200, 1500]];
foreach (['deluxe', 'premier', 'junior-suite', 'executive-suite', 'garden-villa', 'presidential-suite'] as $room) $targets["rooms/$room"] = [1200, 1500];
const QUALITY = 78;

function load_image(string $file) {
    return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => imagecreatefromjpeg($file),
        'png' => imagecreatefrompng($file),
        'webp' => imagecreatefromwebp($file),
        default => false,
    };
}

/** Scales and center-crops to exactly this size, then saves a progressive JPEG. */
function save_cover($src, int $w, int $h, string $to): int {
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale);
    $ch = (int)round($h / $scale);
    $dst = imagecreatetruecolor($w, $h);
    imagecopyresampled($dst, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $w, $h, $cw, $ch);
    imageinterlace($dst, true);
    imagejpeg($dst, $to, QUALITY);
    imagedestroy($dst);
    clearstatcache(true, $to);
    return (int)filesize($to);
}

$done = 0;
$missing = [];
foreach ($targets as $name => [$w, $h]) {
    $found = null;
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        if (is_file("$in/$name.$ext")) { $found = "$in/$name.$ext"; break; }
    }
    if (!$found) { $missing[] = $name; continue; }
    $src = @load_image($found);
    if (!$src) { echo "$name: could not read this file, skipped\n"; continue; }
    $note = (imagesx($src) < $w || imagesy($src) < $h) ? sprintf('  NOTE: the original is only %dx%d, so it will look soft', imagesx($src), imagesy($src)) : '';
    if (!is_dir(dirname("$out/$name"))) mkdir(dirname("$out/$name"), 0775, true);
    $big = save_cover($src, $w, $h, "$out/$name.jpg");
    $small = save_cover($src, 800, 1000, "$out/$name-800.jpg");   // portrait, because phones crop to a tall frame
    imagedestroy($src);
    printf("%-26s %dx%d %4d KB, phone copy %4d KB%s\n", $name, $w, $h, round($big / 1024), round($small / 1024), $note);
    $done++;
}
echo "\nPrepared $done of " . count($targets) . " images.\n";
if ($missing) echo 'No original found for: ' . implode(', ', $missing) . "\n";
