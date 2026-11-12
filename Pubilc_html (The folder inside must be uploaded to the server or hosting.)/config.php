<?php
// Block direct access — this file should only be included by other PHP scripts
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    die('Access denied');
}

date_default_timezone_set('Asia/Jakarta');
// ============================================
// KONFIGURASI DATABASE
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'invyirzx_mailmarketing');
define('DB_PASS', 'Invytes1.');
define('DB_NAME', 'invyirzx_mailmarketing');

// ============================================
// KONFIGURASI SMTP DEFAULT (Hostinger)
// Digunakan jika klien tidak mengatur SMTP sendiri
// ============================================
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'admin@digitalinvitee.com');
define('SMTP_PASS', '');
define('SMTP_FROM_NAME', 'Digital Invitee');
define('SMTP_FROM_EMAIL', 'admin@digitalinvitee.com');

// ============================================
// KONFIGURASI APLIKASI
// ============================================
define('APP_NAME', 'Invytes');
define('APP_URL', 'https://invytes.co.za/');
define('APP_SECRET', 'wdg_secret_key_2026_change_this'); // Untuk signing token

// ============================================
// KONFIGURASI LABEL WHITE-LABEL (RESELLER)
// ============================================
define('APP_SHORT_NAME', 'Invytes'); // Dipakai di judul halaman (misal: Sign In — Invytes)
define('EMAIL_FOOTER_TEXT', 'VXC Express'); // Teks tebal di bawah email
define('EMAIL_FOOTER_LINK', 'https://vxcexpress.com/'); // Link di teks footer email

// ============================================
// SESSION
// ============================================
define('SESSION_NAME', 'wmail_session');
define('SESSION_LIFETIME', 86400); // 24 jam
