<?php
require_once __DIR__ . '/config.php';

/**
 * Email Service - Native SMTP via fsockopen
 * Mendukung SMTP default (Hostinger) dan SMTP kustom per-klien
 */
class EmailService
{
    /**
     * Kirim email via SMTP atau SendGrid (tergantung config klien)
     *
     * @param string $to Email penerima
     * @param string $subject Subjek email
     * @param string $htmlBody Konten HTML
     * @param string $toName Nama penerima (opsional)
     * @param array|null $smtpConfig SMTP kustom klien (opsional)
     * @param array $brandConfig Konfigurasi brand (colors, hero image, dll.)
     * @param string $trackPixelUrl URL tracking pixel transparan (opsional)
     * @param bool $useGeneralWrapper Gunakan template wrapper umum
     * @param array|null $client Data klien lengkap (untuk cek email_provider)
     * @return array Status pengiriman
     */
    public static function send($to, $subject, $htmlBody, $toName = '', $smtpConfig = null, $brandConfig = [], $trackPixelUrl = '', $useGeneralWrapper = false, $client = null)
    {
        if (empty($to)) {
            return ['status' => false, 'message' => 'Recipient email is empty'];
        }

        // Bungkus HTML body dengan template wrapper
        if ($useGeneralWrapper) {
            $fullHtml = self::wrapGeneralTemplate($htmlBody, $subject, $brandConfig, $trackPixelUrl);
        } else {
            $fullHtml = self::wrapTemplate($htmlBody, $subject, $brandConfig, $trackPixelUrl);
        }
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        // Cek apakah klien menggunakan SendGrid
        $emailProvider = $client['email_provider'] ?? 'sendgrid';
        if ($emailProvider === 'sendgrid') {
            $sgConfig = self::getClientSendGrid($client);
            if ($sgConfig) {
                $sgResult = self::sendViaSendGrid($to, $toName, $subject, $fullHtml, $plainText, $sgConfig);
                if ($sgResult['status']) {
                    return $sgResult; // Berhasil via SendGrid
                }
                // Jika gagal auth (401/403), coba ulang pakai key Super Admin
                $httpCode = $sgResult['http_code'] ?? 0;
                if (in_array($httpCode, [401, 403])) {
                    $superSgConfig = self::getSuperAdminSendGrid($client);
                    if ($superSgConfig) {
                        $sgResult2 = self::sendViaSendGrid($to, $toName, $subject, $fullHtml, $plainText, $superSgConfig);
                        if ($sgResult2['status']) {
                            return $sgResult2;
                        }
                    }
                    // Masih gagal? Fallback ke SMTP
                    $smtpFallback = $smtpConfig ?: self::getClientSmtp($client);
                    if ($smtpFallback) {
                        $smtpResult = self::sendViaSmtp($to, $toName, $subject, $fullHtml, $plainText, $smtpFallback);
                        if ($smtpResult['status']) {
                            return $smtpResult;
                        }
                    }
                }
                return $sgResult; // Kembalikan error jika semua gagal
            }
            // Fallback ke SMTP jika SendGrid tidak dikonfigurasi
        }

        // Kirim via SMTP (default fallback)
        return self::sendViaSmtp($to, $toName, $subject, $fullHtml, $plainText, $smtpConfig);
    }

    /**
     * Kirim email via SMTP menggunakan PHPMailer
     */
    public static function sendViaSmtp($to, $toName, $subject, $fullHtml, $plainText, $smtpConfig = null)
    {
        $host = $smtpConfig['host'] ?? SMTP_HOST;
        $port = $smtpConfig['port'] ?? SMTP_PORT;
        $user = $smtpConfig['user'] ?? SMTP_USER;
        $pass = $smtpConfig['pass'] ?? SMTP_PASS;
        $fromName = $smtpConfig['from_name'] ?? SMTP_FROM_NAME;
        $fromEmail = $smtpConfig['from_email'] ?? SMTP_FROM_EMAIL;

        try {
            require_once __DIR__ . '/lib/PHPMailer/Exception.php';
            require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = ($port == 465) ?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $port;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Recipients
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $fullHtml;
            $mail->AltBody = $plainText;

            $mail->send();
            return ['status' => true, 'message' => 'Email sent successfully via SMTP'];
        }
        catch (Exception $e) {
            $errorMsg = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            return ['status' => false, 'message' => 'SMTP Error: ' . $errorMsg];
        }
    }

    /**
     * Kirim email via SendGrid API v3
     */
    public static function sendViaSendGrid($to, $toName, $subject, $fullHtml, $plainText, $sgConfig)
    {
        $apiKey = $sgConfig['api_key'];
        $fromEmail = $sgConfig['from_email'];
        $fromName = $sgConfig['from_name'];

        $payload = [
            'personalizations' => [[
                'to' => [['email' => $to, 'name' => $toName ?: $to]]
            ]],
            'from' => ['email' => $fromEmail, 'name' => $fromName],
            'subject' => $subject,
            'content' => [
                ['type' => 'text/plain', 'value' => $plainText ?: 'Email content'],
                ['type' => 'text/html', 'value' => $fullHtml]
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.sendgrid.com/v3/mail/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['status' => false, 'message' => 'SendGrid cURL Error: ' . $curlError];
        }

        // SendGrid returns 202 Accepted for successful sends
        if ($httpCode === 202 || $httpCode === 200) {
            return ['status' => true, 'message' => 'Email sent successfully via SendGrid'];
        }

        // Parse error response
        $body = json_decode($response, true);
        $errorMsg = 'SendGrid Error (HTTP ' . $httpCode . ')';
        if (!empty($body['errors'])) {
            $msgs = array_map(fn($e) => $e['message'] ?? '', $body['errors']);
            $errorMsg .= ': ' . implode('; ', $msgs);
        }

        return ['status' => false, 'message' => $errorMsg, 'http_code' => $httpCode];
    }

    /**
     * Dapatkan SMTP config dari data klien (atau fallback ke Super Admin/default)
     */
    public static function getClientSmtp($client)
    {
        if (
            !empty(trim($client['smtp_host'] ?? '')) && trim($client['smtp_host']) !== 'null' &&
            !empty(trim($client['smtp_user'] ?? '')) && trim($client['smtp_user']) !== 'null' &&
            !empty(trim($client['smtp_pass'] ?? '')) && trim($client['smtp_pass']) !== 'null'
        ) {
            return [
                'host' => trim($client['smtp_host']),
                'port' => $client['smtp_port'] ?? 465,
                'user' => trim($client['smtp_user']),
                'pass' => trim($client['smtp_pass']),
                'from_name' => !empty(trim($client['smtp_from_name'] ?? '')) ? trim($client['smtp_from_name']) : (!empty(trim($client['couple_names'] ?? '')) ? trim($client['couple_names']) : SMTP_FROM_NAME),
                'from_email' => !empty(trim($client['smtp_from_email'] ?? '')) ? trim($client['smtp_from_email']) : trim($client['smtp_user'])
            ];
        }

        // Fallback ke SMTP Super Admin jika ada
        require_once __DIR__ . '/database.php';
        $db = new \Database();
        $superAdmin = $db->pdo->query("SELECT * FROM clients WHERE is_admin = 1 ORDER BY id ASC LIMIT 1")->fetch();

        if ($superAdmin && 
            !empty(trim($superAdmin['smtp_host'] ?? '')) && trim($superAdmin['smtp_host']) !== 'null' &&
            !empty(trim($superAdmin['smtp_user'] ?? '')) && trim($superAdmin['smtp_user']) !== 'null' &&
            !empty(trim($superAdmin['smtp_pass'] ?? '')) && trim($superAdmin['smtp_pass']) !== 'null'
        ) {
            return [
                'host' => trim($superAdmin['smtp_host']),
                'port' => $superAdmin['smtp_port'] ?? 465,
                'user' => trim($superAdmin['smtp_user']),
                'pass' => trim($superAdmin['smtp_pass']),
                'from_name' => !empty(trim($client['smtp_from_name'] ?? '')) ? trim($client['smtp_from_name']) : (!empty(trim($client['couple_names'] ?? '')) ? trim($client['couple_names']) : (!empty(trim($superAdmin['smtp_from_name'] ?? '')) ? trim($superAdmin['smtp_from_name']) : SMTP_FROM_NAME)),
                'from_email' => !empty(trim($client['smtp_from_email'] ?? '')) ? trim($client['smtp_from_email']) : (!empty(trim($superAdmin['smtp_from_email'] ?? '')) ? trim($superAdmin['smtp_from_email']) : trim($superAdmin['smtp_user']))
            ];
        }

        return null; // Gunakan default config.php
    }

    /**
     * Sanitize API key: hapus whitespace, karakter non-printable, BOM, zero-width spaces
     */
    private static function sanitizeApiKey($key)
    {
        if (empty($key)) return '';
        // Hapus BOM
        $key = str_replace("\xEF\xBB\xBF", '', $key);
        // Hapus semua karakter non-printable dan whitespace
        $key = preg_replace('/[\x00-\x1F\x7F\xC2\xA0\s]+/', '', $key);
        // Hapus zero-width spaces dan karakter Unicode tak terlihat
        $key = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $key);
        return trim($key);
    }

    /**
     * Dapatkan SendGrid config dari data klien (atau fallback ke Super Admin)
     */
    public static function getClientSendGrid($client)
    {
        $apiKey = self::sanitizeApiKey($client['sendgrid_api_key'] ?? '');
        if (!empty($apiKey) && $apiKey !== 'null') {
            return [
                'api_key' => $apiKey,
                'from_email' => !empty(trim($client['sendgrid_from_email'] ?? '')) ? trim($client['sendgrid_from_email']) : ($client['email'] ?? ''),
                'from_name' => !empty(trim($client['sendgrid_from_name'] ?? '')) ? trim($client['sendgrid_from_name']) : (!empty($client['couple_names']) ? $client['couple_names'] : 'Wedding Mail')
            ];
        }

        // Fallback ke SendGrid Super Admin jika sub-admin tidak mengaturnya
        return self::getSuperAdminSendGrid($client);
    }

    /**
     * Dapatkan SendGrid config langsung dari Super Admin (is_admin=1)
     * Menggunakan from_name/from_email klien jika tersedia, fallback ke super admin
     */
    public static function getSuperAdminSendGrid($client = null)
    {
        require_once __DIR__ . '/database.php';
        $db = new \Database();
        $superAdmin = $db->pdo->query("SELECT * FROM clients WHERE is_admin = 1 ORDER BY id ASC LIMIT 1")->fetch();

        if (!$superAdmin) return null;

        $apiKey = self::sanitizeApiKey($superAdmin['sendgrid_api_key'] ?? '');
        if (empty($apiKey) || $apiKey === 'null') return null;

        // from_email: pakai klien punya jika ada, kalau tidak pakai super admin
        $fromEmail = !empty(trim($client['sendgrid_from_email'] ?? '')) 
            ? trim($client['sendgrid_from_email']) 
            : (!empty(trim($superAdmin['sendgrid_from_email'] ?? '')) ? trim($superAdmin['sendgrid_from_email']) : ($client['email'] ?? ''));

        // from_name: pakai couple_names klien, kalau tidak pakai super admin
        $fromName = !empty(trim($client['sendgrid_from_name'] ?? '')) 
            ? trim($client['sendgrid_from_name']) 
            : (!empty($client['couple_names'] ?? '') ? $client['couple_names'] 
                : (!empty(trim($superAdmin['sendgrid_from_name'] ?? '')) ? trim($superAdmin['sendgrid_from_name']) : 'Wedding Mail'));

        return [
            'api_key' => $apiKey,
            'from_email' => $fromEmail,
            'from_name' => $fromName
        ];
    }

    /**
     * Didesain untuk mengirimkan email percobaan.
     * Menggunakan konfigurasi SMTP klien yang sedang aktif.
     */
    public static function sendEmail($to, $toName, $subject, $htmlBody, $client, $trackPixelUrl = null, $useGeneralWrapper = false)
    {
        $brandConfig = self::getBrandConfig($client);
        $emailProvider = $client['email_provider'] ?? 'sendgrid';

        // Bungkus HTML body dengan template wrapper
        if ($useGeneralWrapper) {
            $fullHtml = self::wrapGeneralTemplate($htmlBody, $subject, $brandConfig, $trackPixelUrl);
        } else {
            $fullHtml = self::wrapTemplate($htmlBody, $subject, $brandConfig, $trackPixelUrl);
        }
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        // Cek apakah klien menggunakan SendGrid
        if ($emailProvider === 'sendgrid') {
            $sgConfig = self::getClientSendGrid($client);
            if ($sgConfig) {
                $result = self::sendViaSendGrid($to, $toName, $subject, $fullHtml, $plainText, $sgConfig);
                if ($result['status']) {
                    return true; // Berhasil via SendGrid
                }
                // Jika gagal auth (401/403), coba ulang pakai key Super Admin
                $httpCode = $result['http_code'] ?? 0;
                if (in_array($httpCode, [401, 403])) {
                    $superSgConfig = self::getSuperAdminSendGrid($client);
                    if ($superSgConfig) {
                        $result2 = self::sendViaSendGrid($to, $toName, $subject, $fullHtml, $plainText, $superSgConfig);
                        if ($result2['status']) {
                            return true;
                        }
                    }
                    // Masih gagal? Fallback ke SMTP
                    $smtpConfig = self::getClientSmtp($client);
                    if ($smtpConfig) {
                        $smtpResult = self::sendViaSmtp($to, $toName, $subject, $fullHtml, $plainText, $smtpConfig);
                        if ($smtpResult['status']) {
                            return true;
                        }
                    }
                }
                return $result['message']; // Kembalikan error jika semua gagal
            }
        }

        // Fallback ke SMTP
        $smtpConfig = self::getClientSmtp($client);
        if (!$smtpConfig) {
            return 'No email provider configured. Please set up SendGrid API Key or SMTP in Settings.';
        }

        $result = self::sendViaSmtp($to, $toName, $subject, $fullHtml, $plainText, $smtpConfig);
        return $result['status'] ? true : $result['message'];
    }

    /**
     * Dapatkan brand config dari data klien
     */
    public static function getBrandConfig($client)
    {
        return [
            'color_primary' => $client['color_primary'] ?? '#4A6741',
            'color_secondary' => $client['color_secondary'] ?? '#F5F0EB',
            'couple_names' => $client['couple_names'] ?? '',
            'hero_image_url' => $client['hero_image_url'] ?? '',
            'website_url' => $client['website_url'] ?? '',
            'email_title_font' => $client['email_title_font'] ?? 'Playfair Display',
            'email_body_font' => $client['email_body_font'] ?? 'Inter',
            'email_title_transform' => $client['email_title_transform'] ?? 'none',
            'email_title_size' => $client['email_title_size'] ?? '28px',
            'app_name' => $client['app_name'] ?? '',
            'email_footer_text' => $client['email_footer_text'] ?? '',
            'email_footer_link' => $client['email_footer_link'] ?? ''
        ];
    }

    /**
     * Ganti merge tags di template
     */
    public static function replaceMergeTags($template, $data)
    {
        $tags = [
            '{{guest_name}}', '{{couple_names}}', '{{event_names}}', '{{event_date}}', '{{event_time}}',
            '{{venue_name}}', '{{venue_address}}', '{{venue_map_url}}', '{{dress_code}}',
            '{{rsvp_link}}', '{{calendar_link}}', '{{website_url}}', '{{social_hashtag}}',
            '{{days_until}}', '{{seats_allocated}}', '{{group_label}}', '{{table_number}}',
            '{{qr_image_url}}', '{{qr_checkin_link}}', '{{calendar_link_google}}', '{{calendar_link_apple}}',
            '{{table_number_display}}', '{{color_primary}}', '{{host_name}}', '{{event_location}}', '{{event_name}}'
        ];

        $values = [
            $data['guest_name'] ?? '', $data['couple_names'] ?? '', $data['couple_names'] ?? '', $data['event_date'] ?? '',
            $data['event_time'] ?? '', $data['venue_name'] ?? '', $data['venue_address'] ?? '',
            $data['venue_map_url'] ?? '', $data['dress_code'] ?? '', $data['rsvp_link'] ?? '',
            $data['calendar_link'] ?? '', $data['website_url'] ?? '', $data['social_hashtag'] ?? '',
            $data['days_until'] ?? '', $data['seats_allocated'] ?? '1', $data['group_label'] ?? '',
            $data['table_number'] ?? '',
            $data['qr_image_url'] ?? '', $data['qr_checkin_link'] ?? '',
            $data['calendar_link_google'] ?? '', $data['calendar_link_apple'] ?? '',
            $data['table_number_display'] ?? 'none', $data['color_primary'] ?? '#4A6741',
            $data['couple_names'] ?? '', $data['venue_name'] ?? '', ($data['couple_names'] ?? 'Our') . ' Event'
        ];

        return str_replace($tags, $values, $template);
    }

    /**
     * Membungkus konten HTML dengan template email yang elegan
     */
    public static function wrapTemplate($content, $title = '', $brand = [], $trackPixelUrl = '')
    {
        $primary = $brand['color_primary'] ?? '#4A6741';
        $secondary = $brand['color_secondary'] ?? '#F5F0EB';
        $coupleName = htmlspecialchars($brand['couple_names'] ?? '');
        $heroImage = $brand['hero_image_url'] ?? '';
        $titleFont = $brand['email_title_font'] ?? 'Playfair Display';
        $bodyFont = $brand['email_body_font'] ?? 'Inter';
        $titleTransform = htmlspecialchars($brand['email_title_transform'] ?? 'none');
        $titleSize = htmlspecialchars($brand['email_title_size'] ?? '28px');

        $heroSection = '';
        if (!empty($heroImage)) {
            $heroSection = '
            <tr><td style="padding:0;">
                <img src="' . htmlspecialchars($heroImage) . '" alt="' . $coupleName . '" style="width:100%;max-height:300px;object-fit:cover;display:block;" />
            </td></tr>';
        }

        $trackingHtml = '';
        if (!empty($trackPixelUrl)) {
            $trackingHtml = '<img src="' . htmlspecialchars($trackPixelUrl) . '" width="1" height="1" style="display:none;visibility:hidden;" alt="" />';
        }

        // Generate dynamic Google Fonts URL
        $fontUrlTitle = urlencode($titleFont) . ':wght@400;600;700';
        $fontUrlBody = urlencode($bodyFont) . ':wght@300;400;500';
        if ($titleFont === $bodyFont) {
            $fontQuery = $fontUrlTitle;
        }
        else {
            $fontQuery = $fontUrlTitle . '&family=' . $fontUrlBody;
        }
        $googleFontsUrl = "https://fonts.googleapis.com/css2?family=" . $fontQuery . "&display=swap";

        $safeTitleFont = htmlspecialchars($titleFont);
        $safeBodyFont = htmlspecialchars($bodyFont);

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . '</title>
<style>
@import url("' . $googleFontsUrl . '");
body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f4f1ec; }
table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
.card { background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0,0,0,0.03); }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f1ec;font-family:\'' . $safeBodyFont . '\',Inter,\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f1ec;padding:40px 20px;">
<tr><td align="center" style="padding: 40px 20px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;" class="card">

<!-- Accent Top Border -->
<tr><td style="height:4px;background:linear-gradient(90deg, ' . $primary . ' 0%, ' . $primary . '99 50%, ' . $primary . '44 100%);font-size:0;line-height:0;">&nbsp;</td></tr>

<!-- Hero Image -->
' . $heroSection . '

<!-- Header -->
<tr><td style="background-color:#ffffff;padding:44px 40px 20px;text-align:center;">
' . (!empty($coupleName) ? '<h1 style="margin:0;color:' . $primary . ';font-family:\'' . $safeTitleFont . '\',\'Playfair Display\',Georgia,serif;font-size:' . $titleSize . ';text-transform:' . $titleTransform . ';font-weight:600;letter-spacing:1px;">' . $coupleName . '</h1>' : '') . '
</td></tr>

<!-- Body -->
<tr><td style="background-color:#ffffff;padding:10px 44px 50px;">
<div style="color:#334155;font-size:15px;line-height:1.9;font-family:\'' . $safeBodyFont . '\',Inter,\'Segoe UI\',sans-serif;">
' . $content . '
</div>
</td></tr>

<!-- Footer -->
<tr><td style="background-color:#faf9f7;padding:28px 30px;text-align:center;border-top:1px solid rgba(0,0,0,0.04);">
<p style="margin:0;color:#94a3b8;font-size:11px;letter-spacing:0.5px;">Crafted with ♥ by <a href="' . htmlspecialchars(!empty($brand['email_footer_link']) ? $brand['email_footer_link'] : EMAIL_FOOTER_LINK) . '" target="_blank" style="color:#64748b;text-decoration:none;"><strong>' . htmlspecialchars(!empty($brand['email_footer_text']) ? $brand['email_footer_text'] : EMAIL_FOOTER_TEXT) . '</strong></a></p>
<p style="margin:6px 0 0;color:#cbd5e1;font-size:10px;">This email was sent because you are an invited guest. Please do not reply.</p>
</td></tr>

</table>
</td></tr>
</table>
' . $trackingHtml . '
</body>
</html>';
    }

    /**
     * Membungkus konten HTML dengan template email GENERAL (tanpa branding wedding)
     * Tidak ada couple names header, tidak ada hero image
     */
    public static function wrapGeneralTemplate($content, $title = '', $brand = [], $trackPixelUrl = '')
    {
        $primary = $brand['color_primary'] ?? '#4A6741';
        $bodyFont = $brand['email_body_font'] ?? 'Inter';

        $safeBodyFont = htmlspecialchars($bodyFont);
        $fontUrlBody = urlencode($bodyFont) . ':wght@300;400;500';
        $googleFontsUrl = "https://fonts.googleapis.com/css2?family=" . $fontUrlBody . "&display=swap";

        $trackingHtml = '';
        if (!empty($trackPixelUrl)) {
            $trackingHtml = '<img src="' . htmlspecialchars($trackPixelUrl) . '" width="1" height="1" style="display:none;visibility:hidden;" alt="" />';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . '</title>
<style>
@import url("' . $googleFontsUrl . '");
body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f4f1ec; }
table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
.card { background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0,0,0,0.03); }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f1ec;font-family:\'' . $safeBodyFont . '\',Inter,\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f1ec;padding:40px 20px;">
<tr><td align="center" style="padding: 40px 20px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;" class="card">

<!-- Accent Top Border -->
<tr><td style="height:4px;background:linear-gradient(90deg, ' . $primary . ' 0%, ' . $primary . '99 50%, ' . $primary . '44 100%);font-size:0;line-height:0;">&nbsp;</td></tr>

<!-- Body -->
<tr><td style="background-color:#ffffff;padding:44px 44px 50px;">
<div style="color:#334155;font-size:15px;line-height:1.9;font-family:\'' . $safeBodyFont . '\',Inter,\'Segoe UI\',sans-serif;">
' . $content . '
</div>
</td></tr>

<!-- Footer -->
<tr><td style="background-color:#faf9f7;padding:28px 30px;text-align:center;border-top:1px solid rgba(0,0,0,0.04);">
<p style="margin:0;color:#94a3b8;font-size:11px;letter-spacing:0.5px;">Sent via <a href="' . htmlspecialchars(!empty($brand['email_footer_link']) ? $brand['email_footer_link'] : EMAIL_FOOTER_LINK) . '" target="_blank" style="color:#64748b;text-decoration:none;"><strong>' . htmlspecialchars(!empty($brand['email_footer_text']) ? $brand['email_footer_text'] : EMAIL_FOOTER_TEXT) . '</strong></a></p>
<p style="margin:6px 0 0;color:#cbd5e1;font-size:10px;">Please do not reply to this email.</p>
</td></tr>

</table>
</td></tr>
</table>
' . $trackingHtml . '
</body>
</html>';
    }
}

