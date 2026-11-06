<?php
declare(strict_types=1);

/* ---------- Sending ---------- */

/** Removes line breaks and control characters, so guest input can never add an email header. */
function mail_line(string $s): string { return trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $s)); }

function mail_word(string $s): string { return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }

function mail_address(string $email, string $name = ''): string {
    $name = str_replace(['"', '\\'], '', mail_line($name));
    return $name === '' ? '<' . mail_line($email) . '>' : (preg_match('/[^\x20-\x7E]/', $name) ? mail_word($name) : '"' . $name . '"') . ' <' . mail_line($email) . '>';
}

function mail_b64(string $s): string { return chunk_split(base64_encode($s), 76, "\r\n"); }

/** Builds [headers, body]. $m: to, to_name, subject, text, html, reply_to (optional), ics (optional), ics_name, ics_method. */
function mail_build(array $m): array {
    $alt = 'alt_' . bin2hex(random_bytes(8));
    $mix = 'mix_' . bin2hex(random_bytes(8));
    $domain = substr((string)strrchr(MAIL_FROM, '@'), 1) ?: 'localhost';
    $h = ['From: ' . mail_address(MAIL_FROM, HOTEL_NAME), 'To: ' . mail_address($m['to'], $m['to_name'] ?? ''), 'Subject: ' . mail_word(mail_line($m['subject'])),
          'Date: ' . date(DATE_RFC2822), 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>', 'MIME-Version: 1.0'];
    if (!empty($m['reply_to'])) $h[] = 'Reply-To: ' . mail_address($m['reply_to'], $m['reply_name'] ?? '');

    $body = '--' . $alt . "\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . mail_b64($m['text'])
          . '--' . $alt . "\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . mail_b64($m['html'])
          . '--' . $alt . "--\r\n";
    if (empty($m['ics'])) {
        $h[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
        return [implode("\r\n", $h), $body];
    }
    $h[] = 'Content-Type: multipart/mixed; boundary="' . $mix . '"';
    $name = mail_line($m['ics_name'] ?? 'stay.ics');
    $body = '--' . $mix . "\r\nContent-Type: multipart/alternative; boundary=\"" . $alt . "\"\r\n\r\n" . $body
          . '--' . $mix . "\r\nContent-Type: text/calendar; charset=utf-8; method=" . ($m['ics_method'] ?? 'PUBLISH') . '; name="' . $name . "\"\r\nContent-Disposition: attachment; filename=\"" . $name . "\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . mail_b64($m['ics'])
          . '--' . $mix . "--\r\n";
    return [implode("\r\n", $h), $body];
}

/** Saves the message for reading in development. */
function mail_to_file(string $headers, string $body): bool {
    $dir = __DIR__ . '/../storage/mail';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) return false;
    if (!is_file($dir . '/../.htaccess')) file_put_contents($dir . '/../.htaccess', "Require all denied\n");
    return file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml', $headers . "\r\n\r\n" . $body) !== false;
}

function smtp_expect($fp, array $codes): bool {
    $code = 0;
    while (($line = fgets($fp, 1024)) !== false) {
        $code = (int)substr($line, 0, 3);
        if (($line[3] ?? ' ') !== '-') break;
    }
    if (!in_array($code, $codes, true)) {
        error_log('Mail: SMTP server replied ' . $code);   // status only, never addresses or content
        return false;
    }
    return true;
}

function smtp_cmd($fp, string $line, array $codes): bool { return fwrite($fp, $line . "\r\n") !== false && smtp_expect($fp, $codes); }

function mail_to_smtp(string $to, string $headers, string $body): bool {
    if (SMTP_HOST === '') { error_log('Mail: MAIL_TRANSPORT is smtp but SMTP_HOST is empty.'); return false; }
    $target = (SMTP_SECURE === 'ssl' ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . SMTP_PORT;
    $fp = @stream_socket_client($target, $errno, $errstr, 8);
    if (!$fp) { error_log('Mail: could not connect to the SMTP server (' . $errno . ').'); return false; }
    stream_set_timeout($fp, 10);
    try {
        $helo = 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
        if (!smtp_expect($fp, [220]) || !smtp_cmd($fp, $helo, [250])) return false;
        if (SMTP_SECURE === 'starttls') {
            if (!smtp_cmd($fp, 'STARTTLS', [220])) return false;
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { error_log('Mail: TLS could not be started.'); return false; }
            if (!smtp_cmd($fp, $helo, [250])) return false;
        }
        if (SMTP_USER !== '') {
            if (!smtp_cmd($fp, 'AUTH LOGIN', [334]) || !smtp_cmd($fp, base64_encode(SMTP_USER), [334]) || !smtp_cmd($fp, base64_encode(SMTP_PASS), [235])) return false;
        }
        if (!smtp_cmd($fp, 'MAIL FROM:<' . mail_line(MAIL_FROM) . '>', [250]) || !smtp_cmd($fp, 'RCPT TO:<' . mail_line($to) . '>', [250, 251]) || !smtp_cmd($fp, 'DATA', [354])) return false;
        // Lines starting with a dot must be doubled, or the server would end the message early.
        $data = preg_replace('/^\./m', '..', $headers . "\r\n\r\n" . $body);
        if (!smtp_cmd($fp, $data . "\r\n.", [250])) return false;
        smtp_cmd($fp, 'QUIT', [221]);
        return true;
    } finally {
        fclose($fp);
    }
}

/** Never throws: a failed email must not undo a booking that is already saved. */
function mail_send(array $m): bool {
    try {
        if (!filter_var($m['to'], FILTER_VALIDATE_EMAIL)) return false;
        [$headers, $body] = mail_build($m);
        $ok = MAIL_TRANSPORT === 'smtp' ? mail_to_smtp($m['to'], $headers, $body) : mail_to_file($headers, $body);
        if (!$ok) error_log('Mail: a message could not be sent.');
        return $ok;
    } catch (Throwable $x) {
        error_log('Mail: a message could not be sent.');
        return false;
    }
}

/* ---------- Calendar ---------- */

function ics_text(string $s): string { return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s); }

/** iCalendar lines may not pass 75 bytes. Longer ones continue on a line starting with a space, which counts too. */
function ics_fold(string $line): string {
    $out = '';
    $room = 75;
    while (strlen($line) > $room) {
        $part = mb_strcut($line, 0, $room, 'UTF-8');
        $out .= $part . "\r\n ";
        $line = substr($line, strlen($part));
        $room = 74;
    }
    return $out . $line;
}

function ics_utc(string $ymd, string $time): string {
    return (new DateTimeImmutable($ymd . ' ' . $time, new DateTimeZone(HOTEL_TZ)))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

function booking_sequence(PDO $db, int $id): int {
    $s = $db->prepare("SELECT COUNT(*) FROM booking_events WHERE booking_id = ? AND event_type IN ('modified','cancelled')");
    $s->execute([$id]);
    return (int)$s->fetchColumn();
}

function booking_ics(array $b, int $sequence): string {
    $cancelled = $b['status'] === 'cancelled';
    $domain = substr((string)strrchr(HOTEL_EMAIL, '@'), 1) ?: 'localhost';
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . HOTEL_NAME . '//Reservations//EN', 'CALSCALE:GREGORIAN', 'METHOD:' . ($cancelled ? 'CANCEL' : 'PUBLISH'),
        'BEGIN:VEVENT', 'UID:' . $b['booking_ref'] . '@' . $domain, 'SEQUENCE:' . $sequence, 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . ics_utc($b['check_in'], '15:00'), 'DTEND:' . ics_utc($b['check_out'], '12:00'),
        'SUMMARY:' . ics_text('Stay at ' . HOTEL_NAME . ' (' . $b['booking_ref'] . ')'),
        'LOCATION:' . ics_text(HOTEL_ADDRESS), 'GEO:' . HOTEL_LAT . ';' . HOTEL_LNG,
        'DESCRIPTION:' . ics_text($b['room_name'] . '. Booking ID ' . $b['booking_ref'] . '. Check-in from 15:00, check-out by 12:00. Front desk: ' . HOTEL_PHONE . '. Manage your stay: ' . SITE_URL . '/manage.php'),
        'URL:' . SITE_URL . '/manage.php', 'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT', 'END:VCALENDAR'];
    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}

/* ---------- Messages ---------- */

function mail_html(string $title, string $intro, array $rows, string $note, ?array $button = null): string {
    $r = '';
    foreach ($rows as [$k, $v, $strong]) {
        $w = $strong ? 'font-weight:600;font-size:17px;border-top:1px solid #A98A52;' : '';
        $r .= '<tr><td style="padding:6px 0;color:#6B6B70;' . $w . '">' . e($k) . '</td><td align="right" style="padding:6px 0;' . $w . '">' . e($v) . '</td></tr>';
    }
    $b = $button ? '<p style="margin:24px 0 0"><a href="' . e($button[1]) . '" style="display:inline-block;padding:12px 24px;background:#141415;color:#E9D9B5;text-decoration:none;font-size:13px;letter-spacing:2px;text-transform:uppercase">' . e($button[0]) . '</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;padding:24px;background:#F4EFE6;color:#141415;font:15px/1.6 Georgia,serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#FBF8F3;border-top:3px solid #A98A52"><tr><td style="padding:32px">'
        . '<p style="margin:0 0 24px;font-size:20px;letter-spacing:1px">' . e(HOTEL_NAME) . '</p>'
        . '<h1 style="margin:0 0 12px;font-size:28px;font-weight:normal">' . e($title) . '</h1><p style="margin:0 0 20px">' . e($intro) . '</p>'
        . ($r ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial,sans-serif;font-size:14px">' . $r . '</table>' : '')
        . '<p style="margin:20px 0 0;font-size:13px;color:#6B6B70;font-family:Arial,sans-serif">' . nl2br(e($note)) . '</p>' . $b
        . '</td></tr></table></body></html>';
}

/** Sends the guest a confirmation, change or cancellation notice for a booking. $kind: confirmed, changed or cancelled. */
function mail_booking(PDO $db, int $id, string $kind): bool {
    try {
        $b = load_booking($db, $id);
        if (!$b) return false;
        $copy = [
            'confirmed' => ['Your stay is confirmed', 'Thank you, ' . $b['first_name'] . '. We look forward to welcoming you.'],
            'changed' => ['Your new dates are confirmed', 'Thank you, ' . $b['first_name'] . '. We have updated your reservation.'],
            'cancelled' => ['Your reservation is cancelled', 'We are sorry not to be seeing you, ' . $b['first_name'] . '. Your reservation has been cancelled.'],
        ][$kind];

        $rows = [['Booking ID', $b['booking_ref'], false], ['Room', $b['room_name'], false], ['Check in', nice_date($b['check_in']) . ', from 15:00', false], ['Check out', nice_date($b['check_out']) . ', by 12:00', false],
                 ['Guests', $b['adults'] . ' adult' . ($b['adults'] > 1 ? 's' : ''), false], ['Room, ' . $b['nights'] . ' night' . ($b['nights'] > 1 ? 's' : ''), money2($b['subtotal']), false]];
        foreach (booking_lines($db, $id) as $l) $rows[] = [$l['name'], money2($l['line_total']), false];
        $rows[] = ['Service charge', money2($b['service_charge']), false];
        $rows[] = ['Tax', money2($b['tax']), false];
        if ($kind === 'cancelled') {
            $rows[] = ['Original total', money2($b['total']), false];
            $rows[] = ['Amount due', money2($b['cancellation_fee']), true];
            $note = 'Questions? The front desk is open at any hour on ' . HOTEL_PHONE . '.';
        } else {
            $rows[] = ['Total, payable at the hotel', money2($b['total']), true];
            $note = 'Free cancellation until ' . date('j M Y, H:i', free_until($b)) . '. After that, the first night is charged.' . "\n"
                  . HOTEL_ADDRESS . '. Front desk, open at any hour: ' . HOTEL_PHONE . '.';
        }
        $manage = SITE_URL . '/manage.php';

        $text = $copy[0] . "\n\n" . $copy[1] . "\n\n";
        foreach ($rows as [$k, $v]) $text .= $k . ': ' . $v . "\n";
        $text .= "\n" . $note . "\n\nManage your reservation with your booking ID and email: " . $manage . "\n";

        return mail_send([
            'to' => $b['email'], 'to_name' => $b['first_name'] . ' ' . $b['last_name'], 'subject' => $copy[0] . ': ' . $b['booking_ref'], 'text' => $text,
            'html' => mail_html($copy[0], $copy[1], $rows, $note, ['Manage reservation', $manage]),
            'ics' => booking_ics($b, booking_sequence($db, $id)), 'ics_name' => $b['booking_ref'] . '.ics', 'ics_method' => $kind === 'cancelled' ? 'CANCEL' : 'PUBLISH',
        ]);
    } catch (Throwable $x) {
        error_log('Mail: the booking message could not be prepared.');
        return false;
    }
}

/** Tells the front desk about an inquiry. Replying goes straight to the guest. */
function mail_front_desk(string $name, string $email, string $topic, string $message, string $ref = ''): bool {
    $rows = [['From', $name, false], ['Email', $email, false], ['Topic', $topic, false]];
    if ($ref !== '') $rows[] = ['Booking ID', $ref, false];
    $text = "New inquiry\n\n";
    foreach ($rows as [$k, $v]) $text .= $k . ': ' . $v . "\n";
    $text .= "\n" . $message . "\n";
    return mail_send(['to' => FRONT_DESK_EMAIL, 'subject' => 'New inquiry: ' . $topic . ($ref !== '' ? ' (' . $ref . ')' : ''), 'text' => $text,
        'html' => mail_html('New inquiry', 'Reply to this email to answer the guest directly.', $rows, $message), 'reply_to' => $email, 'reply_name' => $name]);
}
