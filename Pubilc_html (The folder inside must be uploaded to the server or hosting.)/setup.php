<?php
require_once __DIR__ . '/database.php';

$db = new Database();
$pdo = $db->pdo;

try {
    echo "<h2>⚙️ Setting up " . htmlspecialchars(APP_NAME) . " Database...</h2>";

    // 1. Tabel: clients
    $sql = "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        couple_names VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        event_date DATE NULL,
        event_time TIME NULL,
        venue_name VARCHAR(255) NULL,
        venue_address TEXT NULL,
        venue_map_url VARCHAR(500) NULL,
        dress_code VARCHAR(100) NULL,
        website_url VARCHAR(500) NULL,
        embed_rsvp_url VARCHAR(255) NULL,
        reply_to_email VARCHAR(255) NULL,
        timezone VARCHAR(50) DEFAULT 'Europe/London',
        language VARCHAR(5) DEFAULT 'en',
        color_primary VARCHAR(7) DEFAULT '#4A6741',
        color_secondary VARCHAR(7) DEFAULT '#F5F0EB',
        hero_image_url VARCHAR(500) NULL,
        social_hashtag VARCHAR(100) NULL,
        smtp_host VARCHAR(255) NULL,
        smtp_port INT NULL,
        smtp_user VARCHAR(255) NULL,
        smtp_pass VARCHAR(255) NULL,
        smtp_from_name VARCHAR(255) NULL,
        smtp_from_email VARCHAR(255) NULL,
        rsvp_fields TEXT NULL COMMENT 'JSON: field visibility config',
        meal_options TEXT NULL COMMENT 'JSON: array of meal choices',
        rsvp_deadline DATE NULL,
        is_admin BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'clients' berhasil dibuat.<br>";

    // 2. Tabel: guests
    $sql = "CREATE TABLE IF NOT EXISTS guests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(20) NULL,
        group_label VARCHAR(100) NULL,
        seats_allocated INT DEFAULT 1,
        rsvp_status ENUM('pending','attending','declined') DEFAULT 'pending',
        meal_choice VARCHAR(100) NULL,
        dietary_notes TEXT NULL,
        plus_one_name VARCHAR(255) NULL,
        plus_one_meal VARCHAR(100) NULL,
        table_number VARCHAR(50) NULL,
        song_request VARCHAR(255) NULL,
        rsvp_responded_at DATETIME NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        INDEX idx_client_id (client_id),
        INDEX idx_token (token),
        INDEX idx_rsvp_status (rsvp_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'guests' berhasil dibuat.<br>";

    // --- AUTO-MIGRATE KOLOM BARU (Mencegah error 'Unknown column' jika tabel sudah ada sebelumnya) ---
    // 1. Migrate guests
    $columnsToAddGuests = [
        'phone' => 'VARCHAR(20) NULL AFTER email',
        'table_number' => 'VARCHAR(50) NULL AFTER plus_one_meal',
        'song_request' => 'VARCHAR(255) NULL AFTER table_number',
        'accompanying_guests' => "TEXT NULL COMMENT 'JSON array of additional guests' AFTER notes",
        'events_attending' => "TEXT NULL COMMENT 'JSON array of events attended' AFTER rsvp_status",
        'accommodation' => "VARCHAR(255) NULL AFTER events_attending"
    ];

    foreach ($columnsToAddGuests as $col => $def) {
        $stmt = $pdo->query("SHOW COLUMNS FROM guests LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE guests ADD COLUMN $col $def");
            echo "✅ Kolom baru ditambahkan ke 'guests': <b>$col</b><br>";
        }
    }

    // 2. Migrate clients
    $columnsToAddClients = [
        'is_admin' => 'BOOLEAN DEFAULT FALSE AFTER rsvp_deadline',
        'events_list' => "TEXT NULL COMMENT 'JSON array of events' AFTER meal_options",
        'whatsapp_enabled' => "BOOLEAN DEFAULT 0 COMMENT 'Super admin toggle for WA feature'",
        'fonnte_token' => "VARCHAR(255) NULL COMMENT 'Fonnte API token'",
        'fonnte_device' => "VARCHAR(100) NULL COMMENT 'Fonnte device ID'",
        'gift_registry_url' => "VARCHAR(500) NULL COMMENT 'External gift registry URL'",
        'gift_registry_label' => "VARCHAR(100) DEFAULT 'Gift Registry' COMMENT 'Custom label for registry link'",
        'email_provider' => "VARCHAR(20) DEFAULT 'sendgrid' COMMENT 'Email provider: smtp or sendgrid'",
        'sendgrid_api_key' => "VARCHAR(255) NULL COMMENT 'SendGrid API Key'",
        'sendgrid_from_email' => "VARCHAR(255) NULL COMMENT 'SendGrid verified sender email'",
        'sendgrid_from_name' => "VARCHAR(255) NULL COMMENT 'SendGrid sender display name'",
        'template_texts' => "LONGTEXT NULL COMMENT 'JSON: custom template texts per template type'",
        'embed_rsvp_url' => "VARCHAR(255) DEFAULT NULL AFTER website_url"
    ];

    foreach ($columnsToAddClients as $col => $def) {
        $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
            echo "✅ Kolom baru ditambahkan ke 'clients': <b>$col</b><br>";

            // Auto-promote first user to Admin if exists and none are admin
            if ($col === 'is_admin') {
                $checkAdmin = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_admin = TRUE")->fetchColumn();
                if ($checkAdmin == 0) {
                    $pdo->exec("UPDATE clients SET is_admin = TRUE ORDER BY id ASC LIMIT 1");
                    echo "✅ Pengguna pertama otomatis dijadikan Super Admin.<br>";
                }
            }
        }
    }

    // 3. Tabel: email_campaigns
    $sql = "CREATE TABLE IF NOT EXISTS email_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        campaign_type ENUM('save_the_date','formal_invitation','rsvp_reminder','week_of_details','thank_you','custom','general') NOT NULL DEFAULT 'custom',
        subject VARCHAR(255) NOT NULL,
        html_body LONGTEXT NULL,
        status ENUM('draft','scheduled','sending','sent') DEFAULT 'draft',
        scheduled_at DATETIME NULL,
        sent_at DATETIME NULL,
        total_recipients INT DEFAULT 0,
        total_sent INT DEFAULT 0,
        total_opened INT DEFAULT 0,
        total_clicked INT DEFAULT 0,
        audience_filter VARCHAR(20) DEFAULT 'all' COMMENT 'all, attending, pending, declined',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        INDEX idx_client_status (client_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'email_campaigns' berhasil dibuat.<br>";

    // Auto-migrate: add 'audience_filter' if not exists
    $stmt = $pdo->query("SHOW COLUMNS FROM email_campaigns LIKE 'audience_filter'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE email_campaigns ADD COLUMN audience_filter VARCHAR(20) DEFAULT 'all' AFTER total_clicked");
        echo "✅ Kolom 'audience_filter' berhasil ditambahkan ke 'email_campaigns'.<br>";
    }

    // Auto-migrate: ensure 'general' is in campaign_type ENUM
    try {
        $pdo->exec("ALTER TABLE email_campaigns MODIFY COLUMN campaign_type ENUM('save_the_date','formal_invitation','rsvp_reminder','week_of_details','thank_you','custom','general') NOT NULL DEFAULT 'custom'");
        echo "✅ campaign_type ENUM updated (added 'general').<br>";
    } catch (Exception $e) {
        // Silently skip if already correct
    }

    // 4. Tabel: email_logs
    $sql = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        guest_id INT NOT NULL,
        status ENUM('queued','sent','failed','opened','clicked') DEFAULT 'queued',
        sent_at DATETIME NULL,
        opened_at DATETIME NULL,
        clicked_at DATETIME NULL,
        error_message TEXT NULL,
        FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
        INDEX idx_campaign_status (campaign_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'email_logs' berhasil dibuat.<br>";

    // 5. Tabel: activity_logs
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        user_name VARCHAR(100) NULL,
        action VARCHAR(50) NOT NULL,
        target_type VARCHAR(50) NULL,
        target_id INT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        INDEX idx_client_created (client_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'activity_logs' berhasil dibuat.<br>";

    // 6. Tabel: whatsapp_logs
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        message TEXT NOT NULL,
        audience_filter VARCHAR(20) DEFAULT 'all',
        total_sent INT DEFAULT 0,
        total_failed INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        INDEX idx_client_wa (client_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'whatsapp_logs' berhasil dibuat.<br>";

    // 7. Tabel: receptionists
    $sql = "CREATE TABLE IF NOT EXISTS receptionists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        pin_hash VARCHAR(255) NOT NULL,
        permissions JSON DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabel 'receptionists' berhasil dibuat.<br>";

    echo "<h3>🎉 Setup Database Selesai!</h3>";
    echo "<p>Silakan akses <a href='login.php'>Halaman Login</a> untuk membuat akun dan mulai mengelola wedding email marketing.</p>";

}
catch (PDOException $e) {
    die("❌ Setup Gagal: " . $e->getMessage());
}
