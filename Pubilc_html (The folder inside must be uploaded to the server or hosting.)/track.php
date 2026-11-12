<?php
/**
 * Track — Email open/click tracking
 * Open: track.php?action=open&cid=1&gid=5
 * Click: track.php?action=click&cid=1&gid=5&url=https://...
 */
require_once __DIR__ . '/database.php';

$action = $_GET['action'] ?? $_GET['amp;action'] ?? '';
$campaignId = intval($_GET['cid'] ?? $_GET['amp;cid'] ?? 0);
$guestId = intval($_GET['gid'] ?? $_GET['amp;gid'] ?? 0);

if ($campaignId > 0 && $guestId > 0) {
    try {
        $db = new Database();
        $pdo = $db->pdo;

        // Apply client timezone
        $stmtTz = $pdo->prepare("SELECT cl.timezone FROM email_campaigns ec JOIN clients cl ON ec.client_id = cl.id WHERE ec.id = ?");
        $stmtTz->execute([$campaignId]);
        $tzRes = $stmtTz->fetch();
        if ($tzRes && !empty($tzRes['timezone'])) {
            date_default_timezone_set($tzRes['timezone']);
        }

        $now = date('Y-m-d H:i:s');

        if ($action === 'open') {
            $stmt = $pdo->prepare("UPDATE email_logs SET status = IF(status = 'clicked', 'clicked', 'opened'), opened_at = COALESCE(opened_at, ?) WHERE campaign_id = ? AND guest_id = ?");
            $stmt->execute([$now, $campaignId, $guestId]);

            $pdo->prepare("UPDATE email_campaigns SET total_opened = (SELECT COUNT(DISTINCT guest_id) FROM email_logs WHERE campaign_id = ? AND opened_at IS NOT NULL) WHERE id = ?")->execute([$campaignId, $campaignId]);

            header('Content-Type: image/gif');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }

        if ($action === 'click') {
            $url = $_GET['url'] ?? '';

            $stmt = $pdo->prepare("UPDATE email_logs SET status = 'clicked', clicked_at = COALESCE(clicked_at, ?) WHERE campaign_id = ? AND guest_id = ?");
            $stmt->execute([$now, $campaignId, $guestId]);

            $pdo->prepare("UPDATE email_campaigns SET total_clicked = (SELECT COUNT(DISTINCT guest_id) FROM email_logs WHERE campaign_id = ? AND clicked_at IS NOT NULL) WHERE id = ?")->execute([$campaignId, $campaignId]);

            // SECURITY: Validate URL - only allow http/https and block javascript: URIs
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url)) {
                header("Location: $url");
                exit;
            }
        }
    }
    catch (Exception $e) {
        // Silently fail — do not expose error details
        error_log('[Track Error] ' . $e->getMessage());
    }
}

// Default: return pixel
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
