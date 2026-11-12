<?php
// Prevent caching of API responses
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * API — Internal REST-like API untuk dashboard (AJAX calls)
 * Semua endpoint require login
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Endpoints that don't require login
$publicActions = ['get_site_password_status', 'check_guest_email'];

if (!in_array($action, $publicActions)) {
    $client = Auth::check();
    if (!$client) {
        http_response_code(401);
        die(json_encode(['status' => false, 'message' => 'Unauthorized']));
    }

    $clientId = $client['id'];
    if (!empty($client['timezone'])) {
        date_default_timezone_set($client['timezone']);
    }
}

$clientId = $client['id'] ?? null;
if (!empty($client['timezone'])) {
    date_default_timezone_set($client['timezone']);
}

$db = new Database();
$pdo = $db->pdo;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================
// GLOBAL CSRF PROTECTION FOR STATE-CHANGING REQUESTS
// ============================================
$isPostMethod = $_SERVER['REQUEST_METHOD'] === 'POST';
$isSafeAction = in_array($action, ['preview_template', 'export_guests', 'get_stats', 'get_guests', 'get_campaigns', 'get_campaign_stats', 'get_whatsapp_logs', 'get_activity_logs', 'get_site_password_status', 'check_guest_email', 'get_template_texts']);

// If it's a POST request (most actions), verify CSRF Token, except for public webhook endpoints
if ($isPostMethod && !in_array($action, $publicActions)) {
    $headers = apache_request_headers();
    $token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';

    if (!Auth::verifyCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(['status' => false, 'message' => 'Invalid CSRF token. Please refresh the page and try again.']));
    }
}

// Activity logging helper
function logActivity($pdo, $clientId, $username, $action, $targetType = null, $targetId = null, $details = null)
{
    try {
        $pdo->prepare("INSERT INTO activity_logs (client_id, user_name, action, target_type, target_id, details) VALUES (?,?,?,?,?,?)")
            ->execute([$clientId, $username, $action, $targetType, $targetId, $details]);
    }
    catch (Exception $e) { /* Silently fail logging */
    }
}

try {
    switch ($action) {

        // ============================================
        // PUBLIC ENDPOINTS (WordPress Plugin)
        // ============================================
        case 'get_site_password_status':
            $targetClientId = intval($_GET['client_id'] ?? 0);
            if (!$targetClientId) {
                echo json_encode(['status' => false, 'message' => 'Missing client_id']);
                break;
            }
            $stmt = $pdo->prepare("SELECT site_password_enabled FROM clients WHERE id = ?");
            $stmt->execute([$targetClientId]);
            $clientData = $stmt->fetch();

            if (!$clientData) {
                echo json_encode(['status' => false, 'message' => 'Client not found']);
                break;
            }

            echo json_encode([
                'status' => true,
                'site_password_enabled' => (bool)$clientData['site_password_enabled']
            ]);
            break;

        case 'check_guest_email':
            $targetClientId = intval($_POST['client_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');

            if (!$targetClientId || empty($email)) {
                echo json_encode(['status' => false, 'message' => 'Missing client_id or email']);
                break;
            }

            // Allow multiple emails separated by comma in database or direct match
            $stmt = $pdo->prepare("SELECT token FROM guests WHERE client_id = ? AND (email = ? OR email LIKE ? OR email LIKE ? OR email LIKE ?)");
            $stmt->execute([
                $targetClientId,
                $email,
                $email . ',%',
                '%,' . $email,
                '%,' . $email . ',%'
            ]);
            $guest = $stmt->fetch();

            if ($guest) {
                echo json_encode(['status' => true, 'token' => $guest['token']]);
            }
            else {
                echo json_encode(['status' => false, 'message' => 'Email belum terdaftar / Email not found']);
            }
            break;

        // ============================================
        // STATS (Requires Login)
        // ============================================
        case 'get_stats':
            $stats = [];

            // Guest stats
            $stmt = $pdo->prepare("SELECT rsvp_status, COUNT(*) as cnt FROM guests WHERE client_id = ? GROUP BY rsvp_status");
            $stmt->execute([$clientId]);
            $rows = $stmt->fetchAll();

            $stats['total'] = 0;
            $stats['attending'] = 0;
            $stats['declined'] = 0;
            $stats['pending'] = 0;

            foreach ($rows as $r) {
                $stats[$r['rsvp_status']] = (int)$r['cnt'];
                $stats['total'] += (int)$r['cnt'];
            }

            // Meal stats (Primary Guests)
            $stmt = $pdo->prepare("SELECT meal_choice, COUNT(*) as cnt FROM guests WHERE client_id = ? AND rsvp_status = 'attending' AND meal_choice IS NOT NULL AND meal_choice != '' GROUP BY meal_choice");
            $stmt->execute([$clientId]);
            $primaryMeals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Dietary Restrictions (Primary Guests)
            $stmt = $pdo->prepare("SELECT name, dietary_notes FROM guests WHERE client_id = ? AND rsvp_status = 'attending' AND dietary_notes IS NOT NULL AND dietary_notes != ''");
            $stmt->execute([$clientId]);
            $dietaryNotes = $stmt->fetchAll();

            // Total seats & Accompanying data extraction
            $stmt = $pdo->prepare("SELECT name, seats_allocated, accompanying_guests FROM guests WHERE client_id = ? AND rsvp_status = 'attending'");
            $stmt->execute([$clientId]);
            $attendingGuests = $stmt->fetchAll();

            $totalSeatsAllocated = 0;
            $totalActualAttending = $stats['attending']; // start with primary guests
            $meals = $primaryMeals;

            foreach ($attendingGuests as $g) {
                $totalSeatsAllocated += (int)$g['seats_allocated'];

                if (!empty($g['accompanying_guests'])) {
                    $accompanying = json_decode($g['accompanying_guests'], true);
                    if (is_array($accompanying)) {
                        $totalActualAttending += count($accompanying);
                        foreach ($accompanying as $acc) {
                            if (!empty($acc['meal'])) {
                                $meals[$acc['meal']] = ($meals[$acc['meal']] ?? 0) + 1;
                            }
                            if (!empty($acc['dietary_notes'])) {
                                $dietaryNotes[] = [
                                    'name' => 'Guest of ' . ($g['name'] ?? 'Unknown'),
                                    'dietary_notes' => $acc['dietary_notes']
                                ];
                            }
                        }
                    }
                }
            }

            $stats['meals'] = [];
            foreach ($meals as $meal => $cnt) {
                $stats['meals'][] = ['meal_choice' => $meal, 'cnt' => $cnt];
            }
            $stats['dietary_notes'] = $dietaryNotes;
            $stats['total_seats'] = $totalSeatsAllocated;
            $stats['total_actual_attending'] = $totalActualAttending;

            // Campaign stats
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(total_sent) as sent, SUM(total_opened) as opened FROM email_campaigns WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $cs = $stmt->fetch();
            $stats['campaigns_total'] = (int)($cs['total'] ?? 0);
            $stats['emails_sent'] = (int)($cs['sent'] ?? 0);
            $stats['emails_opened'] = (int)($cs['opened'] ?? 0);

            // Recent RSVP activity
            $stmt = $pdo->prepare("SELECT name, rsvp_status, rsvp_responded_at FROM guests WHERE client_id = ? AND rsvp_responded_at IS NOT NULL ORDER BY rsvp_responded_at DESC LIMIT 10");
            $stmt->execute([$clientId]);
            $stats['recent_activity'] = $stmt->fetchAll();

            // Check-in stats
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM guests WHERE client_id = ? AND check_in_status = 1");
            $stmt->execute([$clientId]);
            $stats['checked_in'] = (int)($stmt->fetch()['cnt'] ?? 0);

            $stmt = $pdo->prepare("SELECT name, check_in_time FROM guests WHERE client_id = ? AND check_in_status = 1 ORDER BY check_in_time DESC LIMIT 5");
            $stmt->execute([$clientId]);
            $stats['recent_checkins'] = $stmt->fetchAll();

            echo json_encode(['status' => true, 'data' => $stats]);
            break;

        // ============================================
        // GUESTS
        // ============================================
        case 'get_guests':
            $search = $_GET['search'] ?? '';
            $filter = $_GET['filter'] ?? 'all';

            $query = "SELECT g.*, 
                (SELECT CASE 
                    WHEN clicked_at IS NOT NULL THEN 'clicked' 
                    WHEN opened_at IS NOT NULL THEN 'opened' 
                    WHEN status = 'sent' THEN 'sent' 
                    ELSE NULL 
                END FROM email_logs WHERE guest_id = g.id ORDER BY sent_at DESC LIMIT 1) as latest_email_status,
                (SELECT opened_at FROM email_logs WHERE guest_id = g.id AND opened_at IS NOT NULL ORDER BY opened_at DESC LIMIT 1) as email_opened_at,
                (SELECT clicked_at FROM email_logs WHERE guest_id = g.id AND clicked_at IS NOT NULL ORDER BY clicked_at DESC LIMIT 1) as email_clicked_at,
                (SELECT sent_at FROM email_logs WHERE guest_id = g.id AND status IN ('sent','opened','clicked') ORDER BY sent_at DESC LIMIT 1) as email_sent_at
                FROM guests g WHERE g.client_id = ?";
            $params = [$clientId];

            if ($filter !== 'all') {
                $query .= " AND g.rsvp_status = ?";
                $params[] = $filter;
            }
            if (!empty($search)) {
                $query .= " AND (g.name LIKE ? OR g.email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $query .= " ORDER BY g.created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'add_guest':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = str_replace([' ', '-'], '', trim($_POST['phone'] ?? ''));
            $groupLabel = trim($_POST['group_label'] ?? '');
            $tableNumber = trim($_POST['table_number'] ?? '');
            $seats = max(1, intval($_POST['seats_allocated'] ?? 1));

            if (empty($name)) {
                echo json_encode(['status' => false, 'message' => 'Guest name is required']);
                break;
            }

            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO guests (client_id, token, name, email, phone, group_label, table_number, seats_allocated) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$clientId, $token, $name, $email ?: null, $phone ?: null, $groupLabel ?: null, $tableNumber ?: null, $seats]);

            logActivity($pdo, $clientId, $client['username'], 'add_guest', 'guest', $pdo->lastInsertId(), "Added guest: $name");

            echo json_encode(['status' => true, 'message' => 'Guest added', 'guest_id' => $pdo->lastInsertId(), 'token' => $token]);
            break;

        case 'update_guest':
            $guestId = intval($_POST['guest_id'] ?? 0);
            $fields = [];
            $values = [];

            $allowed = ['name', 'email', 'phone', 'group_label', 'seats_allocated', 'rsvp_status', 'meal_choice', 'dietary_notes', 'plus_one_name', 'plus_one_meal', 'table_number', 'song_request', 'notes', 'accommodation', 'events_attending'];

            foreach ($allowed as $field) {
                if (isset($_POST[$field])) {
                    $fields[] = "$field = ?";
                    $val = $_POST[$field];
                    if ($field === 'phone') {
                        $val = str_replace([' ', '-'], '', trim($val));
                    }
                    $values[] = $val;
                }
            }

            if (empty($fields) || $guestId === 0) {
                echo json_encode(['status' => false, 'message' => 'No fields to update']);
                break;
            }

            $values[] = $guestId;
            $values[] = $clientId;
            $pdo->prepare("UPDATE guests SET " . implode(', ', $fields) . " WHERE id = ? AND client_id = ?")->execute($values);

            logActivity($pdo, $clientId, $client['username'], 'update_guest', 'guest', $guestId, "Updated guest #$guestId");

            echo json_encode(['status' => true, 'message' => 'Guest updated']);
            break;

        case 'delete_guest':
            $guestId = intval($_POST['guest_id'] ?? 0);
            // Get name before deleting for log
            $stmt = $pdo->prepare("SELECT name FROM guests WHERE id = ? AND client_id = ?");
            $stmt->execute([$guestId, $clientId]);
            $delGuest = $stmt->fetch();
            $pdo->prepare("DELETE FROM guests WHERE id = ? AND client_id = ?")->execute([$guestId, $clientId]);
            logActivity($pdo, $clientId, $client['username'], 'delete_guest', 'guest', $guestId, 'Deleted guest: ' . ($delGuest['name'] ?? 'Unknown'));
            echo json_encode(['status' => true, 'message' => 'Guest deleted']);
            break;

        case 'toggle_checkin':
            $guestId = intval($_POST['guest_id'] ?? 0);
            $newStatus = intval($_POST['status'] ?? 0);
            if ($newStatus) {
                $pdo->prepare("UPDATE guests SET check_in_status = 1, check_in_time = NOW() WHERE id = ? AND client_id = ?")->execute([$guestId, $clientId]);
            }
            else {
                $pdo->prepare("UPDATE guests SET check_in_status = 0, check_in_time = NULL WHERE id = ? AND client_id = ?")->execute([$guestId, $clientId]);
            }
            // Get guest name for log
            $stmt = $pdo->prepare("SELECT name FROM guests WHERE id = ? AND client_id = ?");
            $stmt->execute([$guestId, $clientId]);
            $ciGuest = $stmt->fetch();
            logActivity($pdo, $clientId, $client['username'], $newStatus ? 'checkin' : 'undo_checkin', 'guest', $guestId, ($newStatus ? 'Checked in: ' : 'Undo check-in: ') . ($ciGuest['name'] ?? ''));
            echo json_encode(['status' => true, 'message' => $newStatus ? 'Checked in' : 'Check-in undone']);
            break;

        case 'import_guests':
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['status' => false, 'message' => 'No CSV file uploaded']);
                break;
            }

            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($file); // Skip header
            $imported = 0;

            while (($row = fgetcsv($file)) !== false) {
                $name = trim($row[0] ?? '');
                $email = trim($row[1] ?? '');
                $phone = str_replace([' ', '-'], '', trim($row[2] ?? ''));
                $groupLabel = trim($row[3] ?? '');
                $seats = max(1, intval($row[4] ?? 1));
                $tableNumber = trim($row[5] ?? '');

                if (empty($name))
                    continue;

                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("INSERT INTO guests (client_id, token, name, email, phone, group_label, table_number, seats_allocated) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$clientId, $token, $name, $email ?: null, $phone ?: null, $groupLabel ?: null, $tableNumber ?: null, $seats]);
                $imported++;
            }
            fclose($file);

            echo json_encode(['status' => true, 'message' => "$imported guests imported"]);
            break;

        case 'export_guests':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="guests_report.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Type', 'Primary Guest', 'Name', 'Email', 'Phone', 'Group', 'Total Seats',
                'RSVP Status', 'Events Attending', 'Accommodation', 'Meal', 'Dietary Notes', 'Song Request', 'Table', 'Responded At', 'Check-in Token'
            ]);

            $stmt = $pdo->prepare("SELECT * FROM guests WHERE client_id = ? ORDER BY name");
            $stmt->execute([$clientId]);

            while ($row = $stmt->fetch()) {
                $eventsAttending = '';
                if (!empty($row['events_attending'])) {
                    $arr = json_decode($row['events_attending'], true);
                    if (is_array($arr)) {
                        $eventsAttending = implode(', ', $arr);
                    }
                }

                // Primary Guest Row
                fputcsv($output, [
                    'Primary',
                    $row['name'],
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['group_label'],
                    $row['seats_allocated'],
                    $row['rsvp_status'],
                    $eventsAttending,
                    $row['accommodation'],
                    $row['meal_choice'],
                    $row['dietary_notes'],
                    $row['song_request'],
                    $row['table_number'],
                    $row['rsvp_responded_at'],
                    $row['token']
                ]);

                // Accompanying Guests Rows
                if (!empty($row['accompanying_guests'])) {
                    $acc = json_decode($row['accompanying_guests'], true);
                    if (is_array($acc)) {
                        foreach ($acc as $i => $guestDetails) {
                            $guestName = !empty($guestDetails['name']) ? $guestDetails['name'] : "Guest " . ($i + 1) . " of " . $row['name'];

                            fputcsv($output, [
                                'Accompanying',
                                $row['name'], // Relation to primary
                                $guestName, // Their name
                                '', // specific email
                                '', // specific phone
                                $row['group_label'],
                                '1', // Uses 1 seat
                                $row['rsvp_status'], // Inherits RSVP
                                $eventsAttending, // Inherits Events
                                $row['accommodation'], // Inherits Hotel
                                $guestDetails['meal'] ?? '',
                                $guestDetails['dietary_notes'] ?? '',
                                '', // song request
                                $row['table_number'],
                                $row['rsvp_responded_at'],
                                '' // Token only on primary
                            ]);
                        }
                    }
                }
            }
            fclose($output);
            exit;

        // ============================================
        // CAMPAIGNS
        // ============================================
        case 'get_campaigns':
            $stmt = $pdo->prepare("SELECT c.*, 
                (SELECT error_message FROM email_logs WHERE campaign_id = c.id AND status = 'failed' ORDER BY sent_at DESC, id DESC LIMIT 1) as last_error 
                FROM email_campaigns c WHERE c.client_id = ? ORDER BY c.created_at DESC");
            $stmt->execute([$clientId]);
            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'create_campaign':
            require_once __DIR__ . '/templates.php';

            $type = $_POST['campaign_type'] ?? 'custom';
            $subject = trim($_POST['subject'] ?? '');
            $htmlBody = $_POST['html_body'] ?? '';
            $scheduledAt = $_POST['scheduled_at'] ?? null;
            $audienceFilter = $_POST['audience_filter'] ?? 'all';

            // Auto-generate subject jika kosong
            if (empty($subject)) {
                $subject = Templates::getDefaultSubject($type, $client['couple_names']);
            }

            $status = !empty($scheduledAt) ? 'scheduled' : 'draft';
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("INSERT INTO email_campaigns (client_id, campaign_type, subject, html_body, status, scheduled_at, audience_filter, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$clientId, $type, $subject, $htmlBody ?: null, $status, $scheduledAt ?: null, $audienceFilter, $now]);

            echo json_encode(['status' => true, 'message' => 'Campaign created', 'campaign_id' => $pdo->lastInsertId()]);
            break;

        case 'send_campaign':
            require_once __DIR__ . '/campaign_sender.php';

            $campaignId = intval($_POST['campaign_id'] ?? 0);
            $filter = $_POST['filter'] ?? 'all';

            // Verifikasi campaign milik klien ini
            $stmt = $pdo->prepare("SELECT id FROM email_campaigns WHERE id = ? AND client_id = ?");
            $stmt->execute([$campaignId, $clientId]);
            if (!$stmt->fetch()) {
                echo json_encode(['status' => false, 'message' => 'Campaign not found']);
                break;
            }

            $sender = new CampaignSender();
            $result = $sender->send($campaignId, $filter);

            echo json_encode($result);
            break;

        case 'get_campaign_stats':
            $campaignId = intval($_GET['campaign_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ? AND client_id = ?");
            $stmt->execute([$campaignId, $clientId]);
            $campaign = $stmt->fetch();

            if (!$campaign) {
                echo json_encode(['status' => false, 'message' => 'Campaign not found']);
                break;
            }

            // Per-email logs
            $stmt = $pdo->prepare("SELECT el.*, g.name, g.email FROM email_logs el JOIN guests g ON el.guest_id = g.id WHERE el.campaign_id = ? ORDER BY el.sent_at DESC");
            $stmt->execute([$campaignId]);
            $logs = $stmt->fetchAll();

            echo json_encode(['status' => true, 'campaign' => $campaign, 'logs' => $logs]);
            break;

        case 'delete_campaign':
            $campaignId = intval($_POST['campaign_id'] ?? 0);
            $pdo->prepare("DELETE FROM email_campaigns WHERE id = ? AND client_id = ?")->execute([$campaignId, $clientId]);
            echo json_encode(['status' => true, 'message' => 'Campaign deleted']);
            break;

        // ============================================
        // SETTINGS
        // ============================================
        case 'update_settings':
            $fields = [];
            $values = [];

            $allowed = [
                'app_name', 'email_footer_text', 'email_footer_link',
                'couple_names', 'email', 'event_date', 'event_time',
                'venue_name', 'venue_address', 'venue_map_url', 'dress_code',
                'website_url', 'reply_to_email', 'timezone', 'language',
                'color_primary', 'color_secondary', 'hero_image_url', 'social_hashtag',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
                'smtp_from_name', 'smtp_from_email',
                'rsvp_fields', 'meal_options', 'rsvp_deadline', 'events_list',
                'email_title_font', 'email_body_font', 'email_title_transform', 'email_title_size',
                'wishing_well_enable', 'wishing_well_details',
                'auto_send_invites', 'auto_send_reminders_days', 'auto_send_week_of_days',
                'fonnte_token', 'fonnte_device',
                'gift_registry_url', 'gift_registry_label', 'embed_rsvp_url', 'site_password_enabled',
                'email_provider', 'sendgrid_api_key', 'sendgrid_from_email', 'sendgrid_from_name'
            ];

            foreach ($allowed as $field) {
                if (isset($_POST[$field])) {
                    $fields[] = "$field = ?";

                    if (in_array($field, ['wishing_well_enable', 'auto_send_invites', 'site_password_enabled'])) {
                        $values[] = filter_var($_POST[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                    }
                    else {
                        $values[] = $_POST[$field];
                    }
                }
            }

            if (!empty($fields)) {
                $values[] = $clientId;
                $pdo->prepare("UPDATE clients SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                logActivity($pdo, $clientId, $client['username'], 'update_settings', 'client', $clientId, 'Updated settings');
            }

            echo json_encode(['status' => true, 'message' => 'Settings updated']);
            break;

        case 'change_password':
            $result = Auth::changePassword($clientId, $_POST['old_password'] ?? '', $_POST['new_password'] ?? '');
            echo json_encode($result);
            break;

        // ============================================
        // RECEPTIONIST MANAGEMENT
        // ============================================
        case 'get_receptionists':
            $stmt = $pdo->prepare("SELECT id, name, username, is_active, created_at FROM receptionists WHERE client_id = ? ORDER BY created_at DESC");
            $stmt->execute([$clientId]);
            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'create_receptionist':
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $pin = trim($_POST['pin'] ?? '');
            if (empty($name) || empty($username) || empty($pin)) {
                echo json_encode(['status' => false, 'message' => 'Name, username, and PIN are required']);
                break;
            }
            if (strlen($pin) < 4 || strlen($pin) > 6 || !ctype_digit($pin)) {
                echo json_encode(['status' => false, 'message' => 'PIN must be 4-6 digits']);
                break;
            }
            // Check username unique
            $stmt = $pdo->prepare("SELECT id FROM receptionists WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => false, 'message' => 'Username already taken']);
                break;
            }
            $pinHash = password_hash($pin, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO receptionists (client_id, name, username, pin_hash) VALUES (?, ?, ?, ?)")->execute([$clientId, $name, $username, $pinHash]);
            logActivity($pdo, $clientId, $client['username'], 'create_receptionist', 'receptionist', $pdo->lastInsertId(), 'Created receptionist: ' . $name);
            echo json_encode(['status' => true, 'message' => 'Receptionist created']);
            break;

        case 'delete_receptionist':
            $recId = intval($_POST['receptionist_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT name FROM receptionists WHERE id = ? AND client_id = ?");
            $stmt->execute([$recId, $clientId]);
            $rec = $stmt->fetch();
            if (!$rec) {
                echo json_encode(['status' => false, 'message' => 'Receptionist not found']);
                break;
            }
            $pdo->prepare("DELETE FROM receptionists WHERE id = ? AND client_id = ?")->execute([$recId, $clientId]);
            logActivity($pdo, $clientId, $client['username'], 'delete_receptionist', 'receptionist', $recId, 'Deleted receptionist: ' . $rec['name']);
            echo json_encode(['status' => true, 'message' => 'Receptionist deleted']);
            break;

        // ============================================
        // TEMPLATES PREVIEW
        // ============================================
        case 'preview_template':
            require_once __DIR__ . '/templates.php';
            require_once __DIR__ . '/email_service.php';

            $type = $_GET['type'] ?? 'save_the_date';
            $baseUrl = rtrim(APP_URL, '/');

            // Load custom template texts from client
            $templateTexts = [];
            if (!empty($client['template_texts'])) {
                $templateTexts = json_decode($client['template_texts'], true) ?: [];
            }

            $sampleData = [
                'guest_name' => 'Jane Smith',
                'couple_names' => $client['couple_names'] ?: 'Emma & James',
                'event_date' => $client['event_date'] ?: '2026-06-15',
                'event_time' => $client['event_time'] ?: '14:00',
                'venue_name' => $client['venue_name'] ?: 'The Grand Estate',
                'venue_address' => $client['venue_address'] ?: '123 Elegance Lane, London',
                'venue_map_url' => $client['venue_map_url'] ?: '#',
                'dress_code' => $client['dress_code'] ?: 'Black Tie',
                'rsvp_link' => !empty($client['embed_rsvp_url']) ? rtrim($client['embed_rsvp_url'], '/') . '/?token=sample' : $baseUrl . '/rsvp.php?token=sample',
                'calendar_link' => '#',
                'calendar_link_google' => '#',
                'calendar_link_apple' => '#',
                'website_url' => !empty($client['website_url']) ? $client['website_url'] . (parse_url($client['website_url'], PHP_URL_QUERY) ? '&token=sample' : '?token=sample') : '#',
                'social_hashtag' => $client['social_hashtag'] ?: '#EmmaAndJames2026',
                'days_until' => '7',
                'seats_allocated' => '2',
                'table_number' => 'VIP-1',
                'table_number_display' => 'table-row',
                'qr_checkin_link' => $baseUrl . '/scanner.php?token=sample',
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($baseUrl . '/scanner.php?token=sample'),
                'color_primary' => $client['color_primary'] ?? '#4A6741',
                'wishing_well_enable' => $client['wishing_well_enable'] ?? 0,
                'wishing_well_details' => $client['wishing_well_details'] ?? '',
                'email_title_font' => $client['email_title_font'] ?? 'Playfair Display',
                'email_body_font' => $client['email_body_font'] ?? 'Inter',
                'email_title_transform' => $client['email_title_transform'] ?? 'none',
                'email_title_size' => $client['email_title_size'] ?? '28px',
                'template_texts' => $templateTexts
            ];



            $html = Templates::render($type, $sampleData);
            $html = EmailService::replaceMergeTags($html, $sampleData);

            $brandConfig = EmailService::getBrandConfig($client);

            // Use general wrapper for general template type
            if ($type === 'general') {
                $html = EmailService::wrapGeneralTemplate($html, 'Template Preview', $brandConfig);
            } else {
                $html = EmailService::wrapTemplate($html, 'Template Preview', $brandConfig);
            }

            header('Content-Type: text/html');
            echo $html;
            exit;

        case 'send_test_email':
            require_once __DIR__ . '/templates.php';
            require_once __DIR__ . '/email_service.php';

            $type = $_POST['type'] ?? 'save_the_date';
            $testEmail = trim($_POST['test_email'] ?? '');

            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => false, 'message' => 'Invalid email address']);
                break;
            }

            $baseUrl = rtrim(APP_URL, '/');

            // Load custom template texts from client
            $templateTextsTest = [];
            if (!empty($client['template_texts'])) {
                $templateTextsTest = json_decode($client['template_texts'], true) ?: [];
            }

            $sampleData = [
                'guest_name' => 'Demo Guest',
                'couple_names' => $client['couple_names'] ?: 'Emma & James',
                'event_date' => $client['event_date'] ?: '2026-06-15',
                'event_time' => $client['event_time'] ?: '14:00',
                'venue_name' => $client['venue_name'] ?: 'The Grand Estate',
                'venue_address' => $client['venue_address'] ?: '123 Elegance Lane, London',
                'venue_map_url' => $client['venue_map_url'] ?: '#',
                'dress_code' => $client['dress_code'] ?: 'Black Tie',
                'rsvp_link' => !empty($client['embed_rsvp_url']) ? rtrim($client['embed_rsvp_url'], '/') . '/?token=sample' : $baseUrl . '/rsvp.php?token=sample',
                'calendar_link_google' => '#',
                'calendar_link_apple' => '#',
                'website_url' => !empty($client['website_url']) ? $client['website_url'] . (parse_url($client['website_url'], PHP_URL_QUERY) ? '&token=sample' : '?token=sample') : '#',
                'social_hashtag' => $client['social_hashtag'] ?: '#EmmaAndJames2026',
                'days_until' => '7',
                'seats_allocated' => '2',
                'table_number' => 'VIP-1',
                'table_number_display' => 'table-row',
                'qr_checkin_link' => $baseUrl . '/scanner.php?token=sample',
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($baseUrl . '/scanner.php?token=sample'),
                'color_primary' => $client['color_primary'] ?? '#4A6741',
                'email_title_font' => $client['email_title_font'] ?? 'Playfair Display',
                'email_body_font' => $client['email_body_font'] ?? 'Inter',
                'email_title_transform' => $client['email_title_transform'] ?? 'none',
                'email_title_size' => $client['email_title_size'] ?? '28px',
                'template_texts' => $templateTextsTest
            ];

            // Setup Email
            switch ($type) {
                case 'save_the_date':
                    $subject = "Save the Date: " . $sampleData['couple_names'];
                    break;
                case 'formal_invitation':
                    $subject = "You're Invited: " . $sampleData['couple_names'];
                    break;
                case 'rsvp_reminder':
                    $subject = "Gentle Reminder: RSVP for " . $sampleData['couple_names'];
                    break;
                case 'week_of_details':
                    $subject = "Important Details: " . $sampleData['couple_names'] . " Wedding";
                    break;
                case 'thank_you':
                    $subject = "Thank You from " . $sampleData['couple_names'];
                    break;
                case 'general':
                    $subject = "Message from " . ($sampleData['couple_names'] ?: 'Us');
                    break;
                default:
                    $subject = "Test Email";
            }
            $subject = "[TEST] " . $subject;



            $html = Templates::render($type, $sampleData);
            $html = EmailService::replaceMergeTags($html, $sampleData);
            $brandConfig = EmailService::getBrandConfig($client);

            // Use general wrapper for general template type
            if ($type === 'general') {
                $sent = EmailService::sendEmail($testEmail, $sampleData['guest_name'], $subject, $html, $client, null, true);
            } else {
                $sent = EmailService::sendEmail($testEmail, $sampleData['guest_name'], $subject, $html, $client);
            }

            if ($sent === true) {
                echo json_encode(['status' => true, 'message' => 'Test email sent successfully! Please check your inbox.']);
            }
            else {
                // $sent is either false (if config missing) or an error string
                $errorMsg = is_string($sent) ? "SMTP Error: " . $sent : "Check SMTP Configuration in Settings tab.";
                echo json_encode(['status' => false, 'message' => $errorMsg]);
            }
            break;

        // ============================================
        // TEMPLATE TEXTS MANAGEMENT
        // ============================================
        case 'get_template_texts':
            require_once __DIR__ . '/templates.php';

            $allDefaults = Templates::getAllDefaultTexts();
            $customTexts = [];
            if (!empty($client['template_texts'])) {
                $customTexts = json_decode($client['template_texts'], true) ?: [];
            }

            // Merge defaults with custom overrides
            $merged = [];
            foreach ($allDefaults as $type => $defaults) {
                $merged[$type] = $defaults;
                if (isset($customTexts[$type]) && is_array($customTexts[$type])) {
                    foreach ($customTexts[$type] as $key => $val) {
                        if (array_key_exists($key, $defaults) && $val !== '' && $val !== null) {
                            $merged[$type][$key] = $val;
                        }
                    }
                }
            }

            echo json_encode(['status' => true, 'data' => $merged, 'defaults' => $allDefaults]);
            break;

        case 'save_template_texts':
            $templateType = $_POST['template_type'] ?? '';
            $textsJson = $_POST['texts'] ?? '{}';
            $texts = json_decode($textsJson, true);

            if (empty($templateType) || !is_array($texts)) {
                echo json_encode(['status' => false, 'message' => 'Invalid data']);
                break;
            }

            // Load existing template_texts
            $existing = [];
            if (!empty($client['template_texts'])) {
                $existing = json_decode($client['template_texts'], true) ?: [];
            }

            // Update the specific template type
            $existing[$templateType] = $texts;

            $pdo->prepare("UPDATE clients SET template_texts = ? WHERE id = ?")->execute([json_encode($existing, JSON_UNESCAPED_UNICODE), $clientId]);
            logActivity($pdo, $clientId, $client['username'], 'save_template_texts', 'template', null, 'Updated texts for template: ' . $templateType);

            echo json_encode(['status' => true, 'message' => 'Template texts saved successfully']);
            break;

        case 'reset_template_texts':
            require_once __DIR__ . '/templates.php';

            $templateType = $_POST['template_type'] ?? '';

            if (empty($templateType)) {
                echo json_encode(['status' => false, 'message' => 'Template type required']);
                break;
            }

            // Load existing template_texts and remove the specific type
            $existing = [];
            if (!empty($client['template_texts'])) {
                $existing = json_decode($client['template_texts'], true) ?: [];
            }
            unset($existing[$templateType]);

            $pdo->prepare("UPDATE clients SET template_texts = ? WHERE id = ?")->execute([json_encode($existing, JSON_UNESCAPED_UNICODE), $clientId]);
            logActivity($pdo, $clientId, $client['username'], 'reset_template_texts', 'template', null, 'Reset texts for template: ' . $templateType);

            // Return defaults
            $defaults = Templates::getDefaultTexts($templateType);
            echo json_encode(['status' => true, 'message' => 'Template texts reset to default', 'data' => $defaults]);
            break;

        // ============================================
        // SUPER ADMIN ACTIONS
        // ============================================
        case 'admin_get_clients':
            Auth::requireAdmin();
            $stmt = $pdo->prepare("SELECT id, username, couple_names, email, created_at, is_admin, whatsapp_enabled FROM clients ORDER BY id DESC");
            $stmt->execute();
            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'admin_create_client':
            Auth::requireAdmin();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $coupleNames = trim($_POST['couple_names'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (strlen($password) < 6) {
                echo json_encode(['status' => false, 'message' => 'Password must be at least 6 characters']);
                break;
            }
            if (empty($coupleNames) || empty($username)) {
                echo json_encode(['status' => false, 'message' => 'Couple names and username are required']);
                break;
            }

            $result = Auth::register($username, $password, $coupleNames, $email);
            echo json_encode($result);
            break;

        case 'admin_delete_client':
            Auth::requireAdmin();
            $targetId = intval($_POST['client_id'] ?? 0);

            if ($targetId === $clientId) {
                echo json_encode(['status' => false, 'message' => 'Cannot delete your own admin account']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$targetId]);
            echo json_encode(['status' => true, 'message' => 'Client account and all associated data permanently deleted']);
            break;

        case 'admin_login_as':
            Auth::requireAdmin();
            $targetId = intval($_POST['client_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$targetId]);
            $targetClient = $stmt->fetch();

            if (!$targetClient) {
                echo json_encode(['status' => false, 'message' => 'Client not found']);
                break;
            }

            // Impersonate
            $_SESSION['client_id'] = $targetClient['id'];
            $_SESSION['client_username'] = $targetClient['username'];
            $_SESSION['client_name'] = $targetClient['couple_names'];
            $_SESSION['is_admin'] = (bool)$targetClient['is_admin'];

            echo json_encode(['status' => true, 'message' => 'Switched session to ' . $targetClient['couple_names']]);
            break;

        case 'admin_toggle_whatsapp':
            Auth::requireAdmin();
            $targetId = intval($_POST['client_id'] ?? 0);
            $enable = filter_var($_POST['enable'] ?? 0, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $pdo->prepare("UPDATE clients SET whatsapp_enabled = ? WHERE id = ?")->execute([$enable, $targetId]);
            echo json_encode(['status' => true, 'message' => 'WhatsApp ' . ($enable ? 'enabled' : 'disabled')]);
            break;

        case 'send_whatsapp_blast':
            $message = trim($_POST['message'] ?? '');
            $audience = $_POST['audience'] ?? 'all';

            if (empty($message)) {
                echo json_encode(['status' => false, 'message' => 'Message cannot be empty']);
                break;
            }

            // Get client's Fonnte config
            $stmt = $pdo->prepare("SELECT fonnte_token, fonnte_device, couple_names, event_date, event_time, venue_name FROM clients WHERE id = ? AND whatsapp_enabled = 1");
            $stmt->execute([$clientId]);
            $waClient = $stmt->fetch();

            if (!$waClient || empty($waClient['fonnte_token'])) {
                echo json_encode(['status' => false, 'message' => 'WhatsApp not configured. Please set Fonnte API Token in settings.']);
                break;
            }

            // Get target guests
            $where = "client_id = ?";
            if ($audience === 'attending')
                $where .= " AND rsvp_status = 'attending'";
            elseif ($audience === 'pending')
                $where .= " AND rsvp_status = 'pending'";
            elseif ($audience === 'declined')
                $where .= " AND rsvp_status = 'declined'";

            $stmt = $pdo->prepare("SELECT name, phone FROM guests WHERE $where AND phone IS NOT NULL AND phone != ''");
            $stmt->execute([$clientId]);
            $targets = $stmt->fetchAll();

            $sent = 0;
            $failed = 0;

            foreach ($targets as $target) {
                // Replace merge tags
                $personalMsg = str_replace(
                ['{{guest_name}}', '{{couple_names}}', '{{event_date}}', '{{event_time}}', '{{venue_name}}'],
                [$target['name'], $waClient['couple_names'], $waClient['event_date'] ?? '', $waClient['event_time'] ?? '', $waClient['venue_name'] ?? ''],
                    $message
                );

                $phone = preg_replace('/[^0-9+]/', '', $target['phone']);

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => 'https://api.fonnte.com/send',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Authorization: ' . $waClient['fonnte_token']],
                    CURLOPT_POSTFIELDS => [
                        'target' => $phone,
                        'message' => $personalMsg,
                        'countryCode' => '62'
                    ],
                    CURLOPT_TIMEOUT => 15
                ]);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($httpCode == 200) {
                    $result = json_decode($response, true);
                    if (!empty($result['status']))
                        $sent++;
                    else
                        $failed++;
                }
                else {
                    $failed++;
                }
            }

            // Log the blast
            $pdo->prepare("INSERT INTO whatsapp_logs (client_id, message, audience_filter, total_sent, total_failed) VALUES (?,?,?,?,?)")
                ->execute([$clientId, $message, $audience, $sent, $failed]);

            echo json_encode(['status' => true, 'message' => "WhatsApp blast complete: $sent sent, $failed failed", 'sent' => $sent, 'failed' => $failed]);
            logActivity($pdo, $clientId, $client['username'], 'send_whatsapp', 'whatsapp', null, "WA blast ($audience): $sent sent, $failed failed");
            break;

        case 'get_whatsapp_logs':
            $stmt = $pdo->prepare("SELECT * FROM whatsapp_logs WHERE client_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$clientId]);
            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_activity_logs':
            $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE client_id = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$clientId]);
            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode(['status' => false, 'message' => 'Unknown action']);
    }
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
