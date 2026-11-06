<?php
function e($s): string { return htmlspecialchars(is_scalar($s) ? (string)$s : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($n): string { return (CURRENCY === 'USD' ? '$' : CURRENCY . ' ') . number_format((float)$n, 0); }
function money2($n): string { return (CURRENCY === 'USD' ? '$' : CURRENCY . ' ') . number_format((float)$n, 2); }
/** A trimmed text field from $_GET or $_POST. Anything else, such as field[]=x, counts as empty. */
function field(array $from, string $key): string { return is_string($from[$key] ?? null) ? trim($from[$key]) : ''; }
function nice_date(string $ymd,string $format = 'D, j M Y'): string { return date($format, (int)strtotime($ymd)); }

// What each room's photo shows, and which part to keep when a wide panel can only show a band of it.
const ROOM_PHOTOS = [
    'deluxe' => ['alt' => 'A made bed beside a glowing lamp in a dark room', 'focus' => '50% 65%'],
    'premier' => ['alt' => 'Sheer curtains over a tall window in a dim room with an armchair', 'focus' => '50% 45%'],
    'junior-suite' => ['alt' => 'A dark marble bathroom with a white bathtub and basin', 'focus' => '50% 50%'],
    'executive-suite' => ['alt' => 'A lounge with sofas and an armchair under a lit floor lamp', 'focus' => '50% 60%'],
    'garden-villa' => ['alt' => 'A swimming pool at night, with lights reflected in the water', 'focus' => '50% 92%'],
    'presidential-suite' => ['alt' => 'A study with a desk, leather chair and bookshelves beside curtained windows', 'focus' => '50% 60%'],
];

/** The phone-sized copy made by tools/optimize-images.php, if there is one. */
function small_image(string $path): string {
    $small = (string)preg_replace('/\.jpg$/', '-800.jpg', $path);
    return is_file(__DIR__ . '/../' . $small) ? $small : $path;
}

/** src, and srcset and sizes when a phone-sized copy exists. $attr = 'data-' holds the image back until a script loads it. */
function img_attrs(string $path, string $sizes = '100vw', string $attr = ''): string {
    $small = small_image($path);
    $out = $attr . 'src="' . e($path) . '"';
    if ($small !== $path) {
        $size = @getimagesize(__DIR__ . '/../' . $path);
        $out .= ' ' . $attr . 'srcset="' . e($small) . ' 800w, ' . e($path) . ' ' . (int)($size[0] ?? 1200) . 'w" sizes="' . e($sizes) . '"';
    }
    return $out;
}
