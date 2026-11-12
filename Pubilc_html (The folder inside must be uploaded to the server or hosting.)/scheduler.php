<?php
/**
 * Scheduler — Dijalankan via cron job
 * Cron: * * * * /usr/bin/php /path/to/scheduler.php (setiap jam)
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/campaign_sender.php';

$db = new Database();
$pdo = $db->pdo;

echo date('Y-m-d H:i:s') . " — Scheduler started\n";

try {
    // 1. Proses campaign yang sudah dijadwalkan secara manual
    $stmt = $pdo->query("SELECT ec.id, ec.subject, ec.scheduled_at, cl.timezone FROM email_campaigns ec JOIN clients cl ON ec.client_id = cl.id WHERE ec.status = 'scheduled'");
    $scheduled = $stmt->fetchAll();

    if (count($scheduled) > 0) {
        $sender = new CampaignSender();
        foreach ($scheduled as $campaign) {
            // Set timezone to client's timezone (or Jakarta as default) to compare properly
            $tz = !empty($campaign['timezone']) ? $campaign['timezone'] : 'Asia/Jakarta';
            date_default_timezone_set($tz);

            $now = date('Y-m-d H:i:s');

            // Periksa apakah waktu saat ini di wilayah klien sudah melewati waktu yang dijadwalkan
            if ($now >= $campaign['scheduled_at']) {
                echo "📧 Processing manual campaign #{$campaign['id']}: {$campaign['subject']} (Client TZ: $tz)\n";
                $result = $sender->send($campaign['id']);
                echo "   Result: {$result['message']}\n";
            }
        }
    }
    else {
        echo "ℹ️ No manual campaigns to process.\n";
    }

    // 2. Automations (Phase 4)
    echo "\n🤖 Checking Automation Rules...\n";

    // Fetch all clients
    $clients = $pdo->query("SELECT * FROM clients")->fetchAll();
    $sender = new CampaignSender();

    foreach ($clients as $client) {
        $clientId = $client['id'];

        $tz = !empty($client['timezone']) ? $client['timezone'] : 'Asia/Jakarta';
        date_default_timezone_set($tz);
        $now = date('Y-m-d H:i:s');

        // --- 2a. Auto Send Invites ---
        if ($client['auto_send_invites'] == 1) {
            // Find guests who have never received an email and are pending
            $stmt = $pdo->prepare("SELECT id FROM guests WHERE client_id = ? AND rsvp_status = 'pending' AND id NOT IN (SELECT guest_id FROM email_logs el JOIN email_campaigns ec ON el.campaign_id = ec.id WHERE ec.client_id = ?)");
            $stmt->execute([$clientId, $clientId]);
            if ($stmt->rowCount() > 0) {
                echo "   ➡️ Triggering Auto Formal Invitations for Client #{$clientId} (TZ: $tz)\n";
                // Create Campaign Draft
                $subject = "You're Invited: " . ($client['couple_names'] ?: "Our Wedding");
                $stmtInsert = $pdo->prepare("INSERT INTO email_campaigns (client_id, campaign_type, subject, status, audience_filter, created_at) VALUES (?, 'formal_invitation', ?, 'sending', 'pending', ?)");
                $stmtInsert->execute([$clientId, $subject, $now]);
                $newCampaignId = $pdo->lastInsertId();

                // Send immediately
                $res = $sender->send($newCampaignId, 'pending');
                echo "      Result: {$res['message']}\n";
            }
        }

        // --- 2b. Auto Send RSVP Reminders ---
        // This runs if auto_send_reminders_days > 0 AND it's exactly that many days before rsvp_deadline
        if ($client['auto_send_reminders_days'] > 0 && !empty($client['rsvp_deadline'])) {
            $days = intval($client['auto_send_reminders_days']);
            $deadline = new DateTime($client['rsvp_deadline']);
            $today = new DateTime();
            $diff = $today->diff($deadline);

            // Only send if it is exactly X days before the deadline, and hasn't passed
            if (!$diff->invert && $diff->days == $days) {
                // Check if we already sent an auto reminder today for this client to avoid duplicates
                $stmtCheck = $pdo->prepare("SELECT id FROM email_campaigns WHERE client_id = ? AND campaign_type = 'rsvp_reminder' AND DATE(created_at) = CURDATE() AND subject LIKE '[AUTO]%'");
                $stmtCheck->execute([$clientId]);

                if ($stmtCheck->rowCount() == 0) {
                    echo "   ➡️ Triggering Auto RSVP Reminders for Client #{$clientId} ($days days before deadline)\n";
                    $subject = "[AUTO] Gentle Reminder: RSVP for " . ($client['couple_names'] ?: "Our Wedding");
                    $stmtInsert = $pdo->prepare("INSERT INTO email_campaigns (client_id, campaign_type, subject, status, audience_filter, created_at) VALUES (?, 'rsvp_reminder', ?, 'sending', 'pending', ?)");
                    $stmtInsert->execute([$clientId, $subject, $now]);
                    $newCampaignId = $pdo->lastInsertId();

                    $res = $sender->send($newCampaignId, 'pending');
                    echo "      Result: {$res['message']}\n";
                }
            }
        }

        // --- 2c. Auto Send Week-of Details ---
        if ($client['auto_send_week_of_days'] > 0 && !empty($client['event_date'])) {
            $days = intval($client['auto_send_week_of_days']);
            $eventDate = new DateTime($client['event_date']);
            $today = new DateTime();
            $diff = $today->diff($eventDate);

            if (!$diff->invert && $diff->days == $days) {
                $stmtCheck = $pdo->prepare("SELECT id FROM email_campaigns WHERE client_id = ? AND campaign_type = 'week_of_details' AND DATE(created_at) = CURDATE() AND subject LIKE '[AUTO]%'");
                $stmtCheck->execute([$clientId]);

                if ($stmtCheck->rowCount() == 0) {
                    echo "   ➡️ Triggering Auto Week-Of Details for Client #{$clientId} ($days days before event)\n";
                    $subject = "[AUTO] Important Details: " . ($client['couple_names'] ?: "Our Wedding");
                    $stmtInsert = $pdo->prepare("INSERT INTO email_campaigns (client_id, campaign_type, subject, status, audience_filter, created_at) VALUES (?, 'week_of_details', ?, 'sending', 'attending', ?)");
                    $stmtInsert->execute([$clientId, $subject, $now]);
                    $newCampaignId = $pdo->lastInsertId();

                    $res = $sender->send($newCampaignId, 'attending');
                    echo "      Result: {$res['message']}\n";
                }
            }
        }
    }

    echo "\n" . date('Y-m-d H:i:s') . " — Scheduler finished\n";

}
catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
