<?php
/**
 * Campaign Sender — Memproses dan mengirim email campaign
 * Bisa dipanggil dari dashboard (manual) atau scheduler (otomatis)
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email_service.php';
require_once __DIR__ . '/templates.php';

class CampaignSender
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->pdo;
    }

    /**
     * Kirim campaign ke semua tamu
     * @param int $campaignId
     * @param string $filter 'all' | 'pending' | 'attending' | 'declined'
     * @return array
     */
    public function send($campaignId, $filter = 'all')
    {
        // Ambil campaign
        $stmt = $this->pdo->prepare("SELECT c.*, cl.* FROM email_campaigns c JOIN clients cl ON c.client_id = cl.id WHERE c.id = ?");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            return ['status' => false, 'message' => 'Campaign not found'];
        }

        if (!empty($campaign['timezone'])) {
            date_default_timezone_set($campaign['timezone']);
        }

        // Update status ke sending
        $this->pdo->prepare("UPDATE email_campaigns SET status = 'sending' WHERE id = ?")->execute([$campaignId]);

        // Ambil tamu berdasarkan filter dari database campaign
        $audienceFilter = $campaign['audience_filter'] ?? 'all';
        $guestQuery = "SELECT * FROM guests WHERE client_id = ?";
        $params = [$campaign['client_id']];

        if ($audienceFilter === 'pending') {
            $guestQuery .= " AND rsvp_status = 'pending'";
        }
        elseif ($audienceFilter === 'attending') {
            $guestQuery .= " AND rsvp_status = 'attending'";
        }
        elseif ($audienceFilter === 'declined') {
            $guestQuery .= " AND rsvp_status = 'declined'";
        }

        // Hanya yang punya email
        $guestQuery .= " AND email IS NOT NULL AND email != ''";

        $stmtGuests = $this->pdo->prepare($guestQuery);
        $stmtGuests->execute($params);
        $guests = $stmtGuests->fetchAll();

        // Update total recipients
        $this->pdo->prepare("UPDATE email_campaigns SET total_recipients = ? WHERE id = ?")->execute([count($guests), $campaignId]);

        // SMTP config klien
        $smtpConfig = EmailService::getClientSmtp($campaign);
        $brandConfig = EmailService::getBrandConfig($campaign);

        // Data merge umum
        $baseUrl = rtrim(APP_URL, '/');
        $eventDate = $campaign['event_date'] ?? '';
        $daysUntil = '';
        if (!empty($eventDate)) {
            $diff = (new DateTime())->diff(new DateTime($eventDate));
            $daysUntil = $diff->invert ? '0' : (string)$diff->days;
        }

        $sentCount = 0;
        $failCount = 0;

        foreach ($guests as $guest) {
            // Buat link RSVP dan calendar unik
            $rsvpLink = !empty($campaign['embed_rsvp_url'])
                ? rtrim($campaign['embed_rsvp_url'], '/') . '/?token=' . $guest['token']
                : $baseUrl . '/rsvp.php?token=' . $guest['token'];
            $calLinkGoogle = $baseUrl . '/calendar.php?token=' . $guest['token'] . '&type=google';
            $calLinkApple = $baseUrl . '/calendar.php?token=' . $guest['token'] . '&type=apple';
            $trackOpen = $baseUrl . '/track.php?action=open&cid=' . $campaignId . '&gid=' . $guest['id'];

            // Wrap clickable links with tracking
            $trackedRsvpLink = $baseUrl . '/track.php?action=click&cid=' . $campaignId . '&gid=' . $guest['id'] . '&url=' . urlencode($rsvpLink);
            $trackedCalLinkGoogle = $baseUrl . '/track.php?action=click&cid=' . $campaignId . '&gid=' . $guest['id'] . '&url=' . urlencode($calLinkGoogle);
            $trackedCalLinkApple = $baseUrl . '/track.php?action=click&cid=' . $campaignId . '&gid=' . $guest['id'] . '&url=' . urlencode($calLinkApple);
            
            $rawWebsiteUrl = $campaign['website_url'] ?? $baseUrl;
            if ($rawWebsiteUrl !== '#' && !empty($guest['token'])) {
                $rawWebsiteUrl .= (parse_url($rawWebsiteUrl, PHP_URL_QUERY) ? '&token=' : '?token=') . urlencode($guest['token']);
            }
            $trackedWebsite = $baseUrl . '/track.php?action=click&cid=' . $campaignId . '&gid=' . $guest['id'] . '&url=' . urlencode($rawWebsiteUrl);

            // Generate check-in link for QR
            $checkInLink = $baseUrl . '/scanner.php?token=' . $guest['token'];
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($checkInLink);

            // Data merge per tamu
            $mergeData = [
                'guest_name' => $guest['name'],
                'couple_names' => $campaign['couple_names'],
                'event_date' => $eventDate,
                'event_time' => $campaign['event_time'] ?? '',
                'venue_name' => $campaign['venue_name'] ?? '',
                'venue_address' => $campaign['venue_address'] ?? '',
                'venue_map_url' => $campaign['venue_map_url'] ?? '',
                'dress_code' => $campaign['dress_code'] ?? '',
                'rsvp_link' => $trackedRsvpLink,
                'calendar_link_google' => $trackedCalLinkGoogle,
                'calendar_link_apple' => $trackedCalLinkApple,
                'website_url' => $trackedWebsite,
                'social_hashtag' => $campaign['social_hashtag'] ?? '',
                'days_until' => $daysUntil,
                'seats_allocated' => $guest['seats_allocated'] ?? '1',
                'group_label' => $guest['group_label'] ?? $guest['name'],
                'table_number' => $guest['table_number'] ?? '',
                'table_number_display' => empty($guest['table_number']) ? 'none' : 'table-row',
                'qr_checkin_link' => $checkInLink,
                'qr_image_url' => $qrImageUrl,
                'color_primary' => $campaign['color_primary'] ?? '#4A6741',
                'template_texts' => !empty($campaign['template_texts']) ? (json_decode($campaign['template_texts'], true) ?: []) : []
            ];

            // Generate HTML body
            $htmlBody = '';
            if (!empty($campaign['html_body'])) {
                // Gunakan body kustom
                $htmlBody = EmailService::replaceMergeTags($campaign['html_body'], $mergeData);
            }
            else {
                // Gunakan template bawaan
                $htmlBody = Templates::render($campaign['campaign_type'], $mergeData);
                $htmlBody = EmailService::replaceMergeTags($htmlBody, $mergeData);
            }

            // Ganti merge tags di subject
            $subject = EmailService::replaceMergeTags($campaign['subject'], $mergeData);

            // Buat log entry
            $stmtLog = $this->pdo->prepare("INSERT INTO email_logs (campaign_id, guest_id, status) VALUES (?, ?, 'queued')");
            $stmtLog->execute([$campaignId, $guest['id']]);
            $logId = $this->pdo->lastInsertId();

            // Kirim email
            $result = EmailService::send(
                $guest['email'],
                $subject,
                $htmlBody,
                $guest['name'],
                $smtpConfig,
                $brandConfig,
                $trackOpen,
                $campaign['campaign_type'] === 'general', // useGeneralWrapper
                $campaign // client data (untuk cek email_provider)
            );

            if ($result['status']) {
                $sentCount++;
                $this->pdo->prepare("UPDATE email_logs SET status = 'sent', sent_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $logId]);
            }
            else {
                $failCount++;
                $lastError = $result['message'];
                $this->pdo->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?")->execute([$result['message'], $logId]);
            }

            // Update counter
            $this->pdo->prepare("UPDATE email_campaigns SET total_sent = ? WHERE id = ?")->execute([$sentCount, $campaignId]);

            // Jeda 1 detik antar email
            sleep(1);
        }

        // Update status selesai
        $this->pdo->prepare("UPDATE email_campaigns SET status = 'sent', sent_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $campaignId]);

        $finalMessage = "Campaign sent: $sentCount success, $failCount failed.";
        if ($failCount > 0 && isset($lastError)) {
            $finalMessage .= " Last error: " . $lastError;
        }

        return [
            'status' => ($sentCount > 0 || $failCount === 0),
            'message' => $finalMessage,
            'sent' => $sentCount,
            'failed' => $failCount,
            'last_error' => $lastError ?? null
        ];
    }
}

// Jika dipanggil langsung via CLI atau cron
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $sender = new CampaignSender();
    $db = new Database();
    $pdo = $db->pdo;

    // Proses semua campaign yang sedang 'sending'
    $stmt = $pdo->query("SELECT id FROM email_campaigns WHERE status = 'sending'");
    $campaigns = $stmt->fetchAll();

    foreach ($campaigns as $c) {
        echo "Sending campaign #{$c['id']}...\n";
        $result = $sender->send($c['id']);
        echo $result['message'] . "\n";
    }
}
