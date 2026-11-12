<?php
/**
 * Calendar — Generate .ics file untuk Add to Calendar
 * URL: calendar.php?token=abc123
 */
require_once __DIR__ . '/database.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Missing token');
}

$db = new Database();
$pdo = $db->pdo;

// Ambil guest + client data
$stmt = $pdo->prepare("SELECT g.*, c.couple_names, c.event_date, c.event_time, c.venue_name, c.venue_address, c.timezone, c.app_name FROM guests g JOIN clients c ON g.client_id = c.id WHERE g.token = ?");
$stmt->execute([$token]);
$data = $stmt->fetch();

if (!$data) {
    http_response_code(404);
    die('Invalid token');
}

// Format tanggal untuk .ics
$eventDate = $data['event_date'];
$eventTime = $data['event_time'] ?? '14:00:00';
$timezone = $data['timezone'] ?? 'Europe/London';

$dtStart = new DateTime("$eventDate $eventTime", new DateTimeZone($timezone));
$dtEnd = clone $dtStart;
$dtEnd->modify('+5 hours'); // Asumsi durasi 5 jam

$dtStartFormatted = $dtStart->format('Ymd\THis');
$dtEndFormatted = $dtEnd->format('Ymd\THis');
$dtStamp = gmdate('Ymd\THis\Z');

$summary = "Wedding of " . $data['couple_names'];
$location = trim(($data['venue_name'] ?? '') . ', ' . ($data['venue_address'] ?? ''), ', ');
$description = "You are invited to the wedding celebration of " . $data['couple_names'] . ".";
$uid = md5($data['client_id'] . '-' . $token) . '@weddingmail';

// Cek tipe calendar
$type = $_GET['type'] ?? 'apple';

if ($type === 'google') {
    // Format tanggal untuk Google Calendar (YmdTHisZ) di konversi ke UTC
    $dtStartUtc = clone $dtStart;
    $dtStartUtc->setTimezone(new DateTimeZone('UTC'));
    $dtEndUtc = clone $dtEnd;
    $dtEndUtc->setTimezone(new DateTimeZone('UTC'));

    $dates = $dtStartUtc->format('Ymd\THis\Z') . '/' . $dtEndUtc->format('Ymd\THis\Z');

    $googleUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE';
    $googleUrl .= '&text=' . urlencode($summary);
    $googleUrl .= '&dates=' . $dates;
    $googleUrl .= '&details=' . urlencode($description);
    if (!empty($location)) {
        $googleUrl .= '&location=' . urlencode($location);
    }

    header('Location: ' . $googleUrl);
    exit;
}

// Default (Apple/Outlook): Generate .ics content
$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$appName = !empty($data['app_name']) ? $data['app_name'] : APP_NAME;
$ics .= "PRODID:-//" . escapeIcs($appName) . "//EN\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";
$ics .= "BEGIN:VTIMEZONE\r\n";
$ics .= "TZID:$timezone\r\n";
$ics .= "END:VTIMEZONE\r\n";
$ics .= "BEGIN:VEVENT\r\n";
$ics .= "DTSTART;TZID=$timezone:$dtStartFormatted\r\n";
$ics .= "DTEND;TZID=$timezone:$dtEndFormatted\r\n";
$ics .= "DTSTAMP:$dtStamp\r\n";
$ics .= "UID:$uid\r\n";
$ics .= "SUMMARY:" . escapeIcs($summary) . "\r\n";
$ics .= "DESCRIPTION:" . escapeIcs($description) . "\r\n";
if (!empty($location)) {
    $ics .= "LOCATION:" . escapeIcs($location) . "\r\n";
}
$ics .= "STATUS:CONFIRMED\r\n";
$ics .= "BEGIN:VALARM\r\n";
$ics .= "TRIGGER:-P1D\r\n";
$ics .= "ACTION:DISPLAY\r\n";
$ics .= "DESCRIPTION:Wedding Tomorrow!\r\n";
$ics .= "END:VALARM\r\n";
$ics .= "END:VEVENT\r\n";
$ics .= "END:VCALENDAR\r\n";

// Output
$filename = strtolower(str_replace([' ', '&'], ['-', 'and'], $data['couple_names'])) . '-wedding.ics';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $ics;

function escapeIcs($str)
{
    return str_replace(["\n", "\r", ",", ";", "\\"], ["\\n", "", "\\,", "\\;", "\\\\"], $str);
}
