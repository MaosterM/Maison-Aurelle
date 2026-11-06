<?php
declare(strict_types=1);

const CHAT_MAX_LENGTH = 500, CHAT_HISTORY = 12, CHAT_TOOL_ROUNDS = 4;

function concierge_system_prompt(): string {
    $facts = '';
    foreach (hotel_faqs() as $f) $facts .= '- ' . $f['q'] . ' ' . $f['a'] . "\n";
    return 'You are the concierge of ' . HOTEL_NAME . ", a five-star hotel, chatting with a guest on the hotel's website.\n"
        . 'Today is ' . date('l, Y-m-d') . ". All dates are in the hotel's time zone.\n\n"
        . "Hotel facts:\n" . $facts . "\n"
        . "How to help:\n"
        . "- Only quote prices and availability that a tool returned. The guest sees those results as cards, so do not repeat every figure.\n"
        . "- If you need dates or the number of adults to use a tool, ask for them.\n"
        . "- You cannot book, change or cancel anything. When a guest is ready to book, call prefill_booking, which gives them a button to continue. For changes or cancellations, point them to Manage Reservation.\n"
        . "- Never ask for card details, passwords or other sensitive information.\n"
        . "- If you do not know something, say so and offer the front desk. Do not invent hotel details.\n"
        . "- Politely steer unrelated requests back to the guest's stay.\n\n"
        . "Style: warm and brief, usually one to three sentences of plain text with no markdown, since the chat cannot display it. Latency-sensitive; begin your visible answer immediately.";
}

function concierge_tools(): array {
    $dates = [
        'check_in' => ['type' => 'string', 'description' => 'Check-in date as YYYY-MM-DD'],
        'check_out' => ['type' => 'string', 'description' => 'Check-out date as YYYY-MM-DD'],
        'adults' => ['type' => 'integer', 'description' => 'Number of adults, 1 to 4'],
    ];
    $addons = ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Add-on slugs from list_addons'];
    return [
        ['name' => 'check_availability', 'description' => 'Find which room types are free for given dates and guests, with live prices. The guest sees the results as room cards.',
         'input_schema' => ['type' => 'object', 'properties' => $dates, 'required' => ['check_in', 'check_out', 'adults']]],
        ['name' => 'get_quote', 'description' => 'Calculate the exact itemized total for one room type, including any add-ons, service charge and tax. The guest sees it as a quote card.',
         'input_schema' => ['type' => 'object', 'properties' => ['room_slug' => ['type' => 'string', 'description' => 'Room slug from check_availability']] + $dates + ['addons' => $addons], 'required' => ['room_slug', 'check_in', 'check_out', 'adults']]],
        ['name' => 'list_addons', 'description' => 'List the extras a guest can add to a stay: dining, spa, transport, experiences and set deals.',
         'input_schema' => ['type' => 'object', 'properties' => (object)[]]],
        ['name' => 'prefill_booking', 'description' => 'Prepare the booking form with these details and give the guest a button to continue. This does not make a booking; the guest confirms on the booking page.',
         'input_schema' => ['type' => 'object', 'properties' => $dates + ['room_slug' => ['type' => 'string', 'description' => 'Room slug, if the guest has chosen one'], 'addons' => $addons], 'required' => ['check_in', 'check_out', 'adults']]],
    ];
}

function nice_stay(array $s): string {
    return date('j M', strtotime($s['check_in'])) . ' to ' . date('j M Y', strtotime($s['check_out'])) . ', ' . $s['nights'] . ' night' . ($s['nights'] > 1 ? 's' : '')
        . ', ' . $s['adults'] . ' adult' . ($s['adults'] > 1 ? 's' : '');
}

function booking_link(array $s, ?int $roomId = null): string {
    $q = ['step' => 1, 'check_in' => $s['check_in'], 'check_out' => $s['check_out'], 'adults' => $s['adults']];
    if ($roomId) $q['room'] = $roomId;
    return 'booking.php?' . http_build_query($q);
}

/** Add-on rows for the given slugs. Unknown slugs are ignored here; quote() only ever prices active add-ons. */
function addon_rows(PDO $db, array $slugs): array {
    $slugs = array_values(array_filter($slugs, 'is_string'));
    if (!$slugs) return [];
    $s = $db->prepare('SELECT id, slug, name FROM addons WHERE is_active = 1 AND slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')');
    $s->execute($slugs);
    return $s->fetchAll();
}

function room_by_slug(PDO $db, $slug): ?array {
    if (!is_string($slug)) return null;
    $s = $db->prepare('SELECT * FROM room_types WHERE slug = ? AND is_active = 1');
    $s->execute([$slug]);
    return $s->fetch() ?: null;
}

/**
 * Runs one tool. Every input came from the model, so all of it is validated here exactly like form input.
 * Returns [result for the model, is_error]. Cards and add-ons for the guest are collected in $out.
 */
function concierge_run_tool(PDO $db, string $name, array $in, array &$out): array {
    $needsStay = in_array($name, ['check_availability', 'get_quote', 'prefill_booking'], true);
    $stay = null;
    if ($needsStay) {
        [$stay, $err] = parse_stay($in);
        if (!$stay) return [['error' => $err], true];
    }
    switch ($name) {
        case 'check_availability':
            $rooms = available_rooms($db, $stay);
            $list = [];
            foreach ($rooms as $r) {
                $list[] = ['slug' => $r['slug'], 'name' => $r['name'], 'rate_per_night' => (float)$r['base_price'], 'room_total' => (float)$r['base_price'] * $stay['nights'], 'units_left' => $r['left'], 'view' => $r['view']];
            }
            if ($rooms) {
                $out['cards']['rooms'] = ['type' => 'rooms', 'title' => nice_stay($stay), 'rooms' => array_map(fn($r) => [
                    'name' => $r['name'], 'meta' => $r['size_sqm'] . ' m², ' . $r['view'], 'price' => money($r['base_price']) . ' a night',
                    'total' => money($r['base_price'] * $stay['nights']) . ' for the room', 'left' => $r['left'] <= 2 ? $r['left'] . ' left' : '',
                    'image' => small_image((string)$r['hero_image']), 'url' => booking_link($stay, (int)$r['id']),
                ], $rooms)];
            }
            return [['stay' => $stay, 'available' => $list, 'note' => $list ? 'Rates exclude service charge and tax.' : 'Nothing is free for these dates and guests.'], false];

        case 'get_quote':
            $room = room_by_slug($db, $in['room_slug'] ?? null);
            if (!$room) return [['error' => 'Unknown room_slug. Use a slug from check_availability.'], true];
            if ((int)$room['max_adults'] < $stay['adults']) return [['error' => 'This room sleeps at most ' . (int)$room['max_adults'] . ' adults.'], true];
            if ((int)$room['total_units'] - booked_units($db, (int)$room['id'], $stay['check_in'], $stay['check_out']) < 1) return [['error' => 'This room is not free for those dates.'], true];
            $q = quote($db, $room, $stay, array_column(addon_rows($db, (array)($in['addons'] ?? [])), 'id'));
            $lines = [['Room, ' . $q['nights'] . ' night' . ($q['nights'] > 1 ? 's' : ''), money2($q['room_total'])]];
            foreach ($q['addons'] as $a) $lines[] = [$a['name'], money2($a['amount'])];
            $lines[] = ['Service charge', money2($q['service'])];
            $lines[] = ['Tax', money2($q['tax'])];
            $out['cards']['quote'] = ['type' => 'quote', 'title' => $room['name'], 'sub' => nice_stay($stay), 'lines' => $lines, 'total' => money2($q['total']), 'url' => booking_link($stay, (int)$room['id']), 'cta' => 'Reserve this room'];
            return [['room' => $room['name'], 'stay' => $stay, 'room_total' => $q['room_total'], 'addons' => array_map(fn($a) => ['name' => $a['name'], 'amount' => $a['amount']], $q['addons']), 'service_charge' => $q['service'], 'tax' => $q['tax'], 'total' => $q['total'], 'currency' => CURRENCY], false];

        case 'list_addons':
            $rule = ['per_stay' => 'per stay', 'per_night' => 'per night', 'per_person' => 'per person', 'per_person_per_night' => 'per person, per night'];
            $rows = $db->query('SELECT slug, name, category, description, price, pricing_type, savings_note FROM addons WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
            return [['addons' => array_map(fn($a) => ['slug' => $a['slug'], 'name' => $a['name'], 'category' => $a['category'], 'description' => $a['description'], 'price' => (float)$a['price'], 'charged' => $rule[$a['pricing_type']], 'savings' => $a['savings_note']], $rows)], false];

        case 'prefill_booking':
            $room = null;
            if (!empty($in['room_slug'])) {
                $room = room_by_slug($db, $in['room_slug']);
                if (!$room) return [['error' => 'Unknown room_slug. Use a slug from check_availability.'], true];
                if ((int)$room['max_adults'] < $stay['adults'] || (int)$room['total_units'] - booked_units($db, (int)$room['id'], $stay['check_in'], $stay['check_out']) < 1) {
                    return [['error' => 'This room is not available for those dates and guests.'], true];
                }
            }
            $rows = addon_rows($db, (array)($in['addons'] ?? []));
            $out['wish'] = array_map('intval', array_column($rows, 'id'));
            $out['cards']['booking'] = ['type' => 'booking', 'title' => $room ? $room['name'] : 'Your stay', 'sub' => nice_stay($stay),
                'extras' => array_column($rows, 'name'), 'url' => booking_link($stay, $room ? (int)$room['id'] : null), 'cta' => 'Continue to booking'];
            return [['ok' => true, 'note' => 'The guest now has a button to continue. Nothing is booked until they confirm on the booking page.'], false];
    }
    return [['error' => 'Unknown tool.'], true];
}

/** One call to the model. Content blocks stay as objects so they can be sent back unchanged. Returns the response, or null on any failure. */
function llm_post(array $body): ?object {
    $headers = ['Content-Type: application/json', 'x-api-key: ' . LLM_API_KEY, 'anthropic-version: 2026-10-21'];
    if (preg_match('/^claude-(opus-5|fable-5-1|sonnet-5-5)/', LLM_MODEL)) {
        // If a safety check declines a message, retry on a suitable model instead of failing.
        $headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
        $body['fallbacks'] = 'default';
    }
    $c = curl_init(LLM_API_URL);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 45]);
    $raw = curl_exec($c);
    $status = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $errno = curl_errno($c);
    curl_close($c);
    if ($raw === false || $status !== 200) {
        error_log('Concierge LLM request failed: http=' . $status . ' curl=' . $errno);   // status codes only, never message text
        return null;
    }
    $res = json_decode((string)$raw);
    if (!is_object($res) || !isset($res->content) || !is_array($res->content)) {
        error_log('Concierge LLM returned an unexpected response shape.');
        return null;
    }
    return $res;
}

/** The tool loop. Returns the reply text, or null if the model could not be used and the FAQ should answer instead. */
function concierge_llm_reply(PDO $db, array $history, array &$out): ?string {
    $messages = $history;
    for ($round = 0; $round < CHAT_TOOL_ROUNDS; $round++) {
        $res = llm_post(['model' => LLM_MODEL, 'max_tokens' => 2000, 'output_config' => ['effort' => 'low'],
            'system' => concierge_system_prompt(), 'tools' => concierge_tools(), 'messages' => $messages]);
        if (!$res) return null;
        if (($res->stop_reason ?? '') === 'refusal') return 'I am not able to help with that here, but the front desk will be glad to assist on ' . HOTEL_PHONE . '.';

        $text = '';
        $calls = [];
        foreach ($res->content as $b) {
            if (($b->type ?? '') === 'text') $text .= $b->text;
            elseif (($b->type ?? '') === 'tool_use') $calls[] = $b;
        }
        if (($res->stop_reason ?? '') !== 'tool_use' || !$calls) return trim($text) !== '' ? trim($text) : null;

        $messages[] = ['role' => 'assistant', 'content' => $res->content];   // unchanged, including any thinking blocks
        $results = [];
        foreach ($calls as $call) {
            try {
                [$result, $isError] = concierge_run_tool($db, (string)$call->name, (array)$call->input, $out);
            } catch (Throwable $x) {
                error_log('Concierge tool failed: ' . $call->name);
                [$result, $isError] = [['error' => 'That lookup failed. Offer the front desk instead.'], true];
            }
            $results[] = ['type' => 'tool_result', 'tool_use_id' => $call->id, 'content' => json_encode($result), 'is_error' => $isError];
        }
        $messages[] = ['role' => 'user', 'content' => $results];   // all results in one message
    }
    error_log('Concierge stopped after too many tool rounds.');
    return null;
}

/** Keyword answers for when there is no API key, or the model is unavailable. */
function concierge_faq_reply(?PDO $db, string $message, array &$out): string {
    $m = mb_strtolower($message);
    foreach (hotel_faqs() as $f) {   // specific questions first, so "what is included in the price?" isn't taken as a room search
        if (preg_match('/' . $f['match'] . '/', $m)) return $f['a'];
    }
    if (preg_match('/\b(room|rooms|suite|suites|villa|availab\w*|how much|price|prices|rate|rates|book|reserve|stay)\b/', $m)) {
        $rooms = [];
        if ($db) {
            try { $rooms = $db->query('SELECT * FROM room_types WHERE is_active = 1 ORDER BY sort_order')->fetchAll(); } catch (Throwable $x) { $rooms = []; }
        }
        if ($rooms) {
            $out['cards']['rooms'] = ['type' => 'rooms', 'title' => 'Our rooms and suites', 'rooms' => array_map(fn($r) => [
                'name' => $r['name'], 'meta' => $r['size_sqm'] . ' m², ' . $r['view'], 'price' => 'From ' . money($r['base_price']) . ' a night', 'total' => '', 'left' => '',
                'image' => small_image((string)$r['hero_image']), 'url' => 'booking.php?step=1&room=' . (int)$r['id'],
            ], $rooms)];
            return 'Here are our six ways to stay. Choose one to see availability for your dates.';
        }
    }
    if (preg_match('/\b(extra|extras|add.?ons?|package|deal|deals|breakfast|dinner|lunch|dining|restaurant)\b/', $m)) {
        return 'You can add breakfast, a tasting dinner, spa treatments and set deals when you choose your extras during booking.';
    }
    if (preg_match('/\b(hello|hi|hey|good (morning|afternoon|evening)|thanks|thank you)\b/', $m)) {
        return 'You are very welcome. I can help with rooms, spa and gym hours, payment, or changing a reservation.';
    }
    return 'I can answer questions about rooms, check-in times, payment, cancellation, the spa and gym, and how to reach us. For anything else, the front desk is available at any hour on ' . HOTEL_PHONE . '.';
}
