<?php
/**
 * RSVP Page — Halaman publik untuk tamu mengisi RSVP
 * URL: rsvp.php?token=abc123
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

// Allow iframe embedding
$embedMode = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($embedMode) {
    // Beri izin iframe dari domain manapun.
    // Hapus X-Frame-Options bawaan server (Apache/Nginx) jika ada
    header_remove('X-Frame-Options');
    header("Content-Security-Policy: frame-ancestors *");
}
else {
    header('X-Frame-Options: SAMEORIGIN');
}

$token = $_GET['token'] ?? '';
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';
$success = false;
$error = '';

// Multi-language labels
$lang = 'en';
$labels = [
    'en' => [
        'rsvp' => 'RSVP',
        'youre_invited' => "You're Invited",
        'dear' => 'Dear',
        'will_you_join' => 'Will you be joining us?',
        'accept' => '✨ Joyfully Accept',
        'decline' => '💔 Regretfully Decline',
        'meal_pref' => 'Meal Preference',
        'select_meal' => 'Select your meal...',
        'dietary' => 'Dietary Restrictions or Allergies',
        'dietary_placeholder' => 'e.g., Nut allergy, Vegan, Gluten-free, Kosher...',
        'accompanying' => 'Accompanying Guests',
        'additional_seats' => 'You have %d additional seat(s) reserved. Please fill in their details if they are attending.',
        'guest_n' => 'Guest %d',
        'full_name' => 'Full Name',
        'guest_full_name' => "Guest's full name",
        'select_meal_guest' => 'Select meal for this guest...',
        'song_request' => 'Song Request',
        'song_placeholder' => 'What song will get you on the dance floor?',
        'phone' => 'Phone Number',
        'accommodation' => 'Accommodation / Hotel Name (Optional)',
        'accommodation_placeholder' => 'Where will you be staying?',
        'notes' => 'Any Notes?',
        'notes_placeholder' => "Anything you'd like us to know?",
        'submit' => 'Submit RSVP',
        'update_rsvp' => 'Update My RSVP',
        'back_website' => '← Back to Wedding Website',
        'events_which' => 'Which events will you attend?',
        'thrilled' => "We're Thrilled!",
        'thank_confirm' => 'Thank you for confirming your attendance,',
        'cant_wait' => "We can't wait to celebrate with you.",
        'thank_you' => 'Thank You',
        'understand' => 'We understand,',
        'youll_be_missed' => "Thank you for letting us know. You'll be missed! We hope to celebrate with you another time.",
        'rsvp_closed' => 'RSVP Closed',
        'rsvp_closed_msg' => 'We are no longer accepting RSVPs as our final guest count has been submitted. If you have any questions, please contact the couple directly.',
        'oops' => 'Oops!',
        'days_to_go' => 'days to go',
        'events_attending' => 'Events Attending',
        'your_meal' => 'Your Meal',
        'dietary_options' => 'Dietary Options',
        'seats' => 'seats',
        'reserved_honor' => 'reserved in your honor',
        'dress_code' => 'Dress Code',
        'view_gift_registry' => '🎁 View Gift Registry',
        'update_rsvp_btn' => '✏️ Update My RSVP',
    ],
    'id' => [
        'rsvp' => 'RSVP',
        'youre_invited' => 'Anda Diundang',
        'dear' => 'Kepada',
        'will_you_join' => 'Apakah Anda akan hadir?',
        'accept' => '✨ Dengan Senang Hati Hadir',
        'decline' => '💔 Maaf Tidak Bisa Hadir',
        'meal_pref' => 'Pilihan Menu',
        'select_meal' => 'Pilih menu Anda...',
        'dietary' => 'Alergi atau Pantangan Makanan',
        'dietary_placeholder' => 'Contoh: Alergi kacang, Vegan, Bebas gluten...',
        'accompanying' => 'Tamu Pendamping',
        'additional_seats' => 'Anda memiliki %d kursi tambahan. Silakan isi detail tamu pendamping Anda.',
        'guest_n' => 'Tamu %d',
        'full_name' => 'Nama Lengkap',
        'guest_full_name' => 'Nama lengkap tamu',
        'select_meal_guest' => 'Pilih menu untuk tamu ini...',
        'song_request' => 'Request Lagu',
        'song_placeholder' => 'Lagu apa yang ingin Anda dengar?',
        'phone' => 'Nomor Telepon',
        'accommodation' => 'Akomodasi / Nama Hotel (Opsional)',
        'accommodation_placeholder' => 'Di mana Anda akan menginap?',
        'notes' => 'Catatan Tambahan',
        'notes_placeholder' => 'Ada yang ingin disampaikan?',
        'submit' => 'Kirim RSVP',
        'update_rsvp' => 'Perbarui RSVP Saya',
        'back_website' => '← Kembali ke Website Pernikahan',
        'events_which' => 'Acara mana yang akan Anda hadiri?',
        'thrilled' => 'Terima Kasih!',
        'thank_confirm' => 'Terima kasih telah mengonfirmasi kehadiran Anda,',
        'cant_wait' => 'Kami sangat menantikan kehadiran Anda.',
        'thank_you' => 'Terima Kasih',
        'understand' => 'Kami memahami,',
        'youll_be_missed' => 'Terima kasih telah memberi tahu kami. Kami akan merindukan Anda! Semoga bisa merayakan di kesempatan lain.',
        'rsvp_closed' => 'RSVP Ditutup',
        'rsvp_closed_msg' => 'Kami sudah tidak menerima RSVP karena jumlah tamu final sudah diserahkan. Jika ada pertanyaan, silakan hubungi pasangan secara langsung.',
        'oops' => 'Oops!',
        'days_to_go' => 'hari lagi',
        'events_attending' => 'Acara yang Dihadiri',
        'your_meal' => 'Menu Anda',
        'dietary_options' => 'Pantangan Makanan',
        'seats' => 'kursi',
        'reserved_honor' => 'disiapkan untuk kehormatan Anda',
        'dress_code' => 'Dress Code',
        'view_gift_registry' => '🎁 Lihat Gift Registry',
        'update_rsvp_btn' => '✏️ Perbarui RSVP Saya',
    ]
];

if (empty($token)) {
    $error = 'Invalid invitation link.';
}

$db = new Database();
$pdo = $db->pdo;

// Ambil data tamu + klien
$guest = null;
$client = null;
$rsvpFields = [];
$mealOptions = [];

if (empty($error)) {
    $stmt = $pdo->prepare("SELECT g.*, c.couple_names, c.event_date, c.event_time, c.venue_name, c.venue_address, c.venue_map_url, c.dress_code, c.website_url, c.color_primary, c.color_secondary, c.hero_image_url, c.social_hashtag, c.rsvp_fields, c.events_list, c.meal_options, c.rsvp_deadline, c.language, c.gift_registry_url, c.gift_registry_label, c.email_footer_text, c.email_footer_link FROM guests g JOIN clients c ON g.client_id = c.id WHERE g.token = ?");
    $stmt->execute([$token]);
    $guest = $stmt->fetch();

    if (!$guest) {
        $error = 'Invalid or expired invitation link.';
    }
    else {
        $rsvpFields = json_decode($guest['rsvp_fields'] ?? '{}', true) ?: [];
        $mealOptions = json_decode($guest['meal_options'] ?? '[]', true) ?: [];

        // Cek deadline
        $deadlinePassed = false;
        if (!empty($guest['rsvp_deadline'])) {
            $deadline = new DateTime($guest['rsvp_deadline']);
            $now = new DateTime();
            if ($now > $deadline && $guest['rsvp_status'] === 'pending') {
                $deadlinePassed = true;
                $error = 'deadline_passed';
            }
        }
        // Set language
        $lang = $guest['language'] ?? 'en';
        if (!isset($labels[$lang]))
            $lang = 'en';
    }
}

// Handle form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $guest && empty($error)) {
    $rsvpStatus = $_POST['rsvp_status'] ?? '';

    if (!in_array($rsvpStatus, ['attending', 'declined'])) {
        $error = 'Please select your attendance status.';
    }
    else {
        $updateFields = ['rsvp_status = ?', 'rsvp_responded_at = NOW()'];
        $updateValues = [$rsvpStatus];

        // Ensure phone is sanitized (no spaces or dashes)
        if (isset($_POST['phone'])) {
            $cleanPhone = str_replace([' ', '-'], '', trim($_POST['phone']));
            $updateFields[] = 'phone = ?';
            $updateValues[] = $cleanPhone;
        }

        // Conditional fields berdasarkan konfigurasi
        if ($rsvpStatus === 'attending') {
            if (!empty($rsvpFields['meal_choice']) && isset($_POST['meal_choice'])) {
                $updateFields[] = 'meal_choice = ?';
                $updateValues[] = $_POST['meal_choice'];
            }
            if (!empty($rsvpFields['dietary_notes']) && isset($_POST['dietary_notes'])) {
                $updateFields[] = 'dietary_notes = ?';
                $updateValues[] = $_POST['dietary_notes'];
            }
            if (!empty($rsvpFields['plus_one']) && $guest['seats_allocated'] > 1) {
                $additionalGuests = [];
                $maxAdditional = $guest['seats_allocated'] - 1;

                for ($i = 0; $i < $maxAdditional; $i++) {
                    $name = $_POST['additional_guests'][$i]['name'] ?? '';
                    $meal = $_POST['additional_guests'][$i]['meal'] ?? '';
                    $dietary = $_POST['additional_guests'][$i]['dietary_notes'] ?? '';

                    if (!empty($name)) {
                        $additionalGuests[] = [
                            'name' => $name,
                            'meal' => $meal,
                            'dietary_notes' => $dietary
                        ];
                    }
                }

                if (!empty($additionalGuests)) {
                    $updateFields[] = 'accompanying_guests = ?';
                    $updateValues[] = json_encode($additionalGuests);
                }
            }
            if (!empty($rsvpFields['song_request']) && isset($_POST['song_request'])) {
                $updateFields[] = 'song_request = ?';
                $updateValues[] = $_POST['song_request'];
            }
            if (!empty($rsvpFields['accommodation']) && isset($_POST['accommodation'])) {
                $updateFields[] = 'accommodation = ?';
                $updateValues[] = $_POST['accommodation'];
            }
            if (isset($_POST['events_attending']) && is_array($_POST['events_attending'])) {
                $updateFields[] = 'events_attending = ?';
                // Remove empty values and json encode
                $eventsAttending = array_filter($_POST['events_attending']);
                $updateValues[] = json_encode(array_values($eventsAttending));
            }
            if (!empty($rsvpFields['notes']) && isset($_POST['notes'])) {
                $updateFields[] = 'notes = ?';
                $updateValues[] = $_POST['notes'];
            }
        }

        $updateValues[] = $token;
        $sql = "UPDATE guests SET " . implode(', ', $updateFields) . " WHERE token = ?";

        try {
            $pdo->prepare($sql)->execute($updateValues);
            $success = true;
            // Refresh data
            $stmt = $pdo->prepare("SELECT g.*, c.couple_names, c.event_date, c.event_time, c.venue_name, c.venue_address, c.venue_map_url, c.dress_code, c.website_url, c.color_primary, c.color_secondary, c.hero_image_url, c.social_hashtag, c.rsvp_fields, c.events_list, c.meal_options, c.rsvp_deadline, c.language, c.gift_registry_url, c.gift_registry_label, c.email_footer_text, c.email_footer_link FROM guests g JOIN clients c ON g.client_id = c.id WHERE g.token = ?");
            $stmt->execute([$token]);
            $guest = $stmt->fetch();
        }
        catch (Exception $e) {
            error_log("RSVP Error: " . $e->getMessage());
            $error = 'An error occurred while processing your RSVP. Please try again.';
        }
    }
}

// Colors
$primary = $guest['color_primary'] ?? '#4A6741';
$secondary = $guest['color_secondary'] ?? '#F5F0EB';
$heroImage = $guest['hero_image_url'] ?? '';
$websiteUrl = $guest['website_url'] ?? '';
if (!empty($websiteUrl) && !empty($token)) {
    $separator = (parse_url($websiteUrl, PHP_URL_QUERY) == NULL) ? '?' : '&';
    $websiteUrl .= $separator . 'token=' . urlencode($token);
}
$calendarLink = rtrim(APP_URL, '/') . '/calendar.php?token=' . urlencode($token);
$giftRegistryUrl = $guest['gift_registry_url'] ?? '';
$giftRegistryLabel = $guest['gift_registry_label'] ?? 'Gift Registry';

$alreadyResponded = $guest && $guest['rsvp_status'] !== 'pending';
$embedSuffix = $embedMode ? '&embed=1' : '';

// Calculate countdown days
$countdownDays = null;
if (!empty($guest['event_date'])) {
    $eventDt = new DateTime($guest['event_date']);
    $nowDt = new DateTime();
    if ($eventDt > $nowDt) {
        $countdownDays = $nowDt->diff($eventDt)->days;
    }
}

// Label shorthand
$L = $labels[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $guest['language'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSVP — <?php echo htmlspecialchars($guest['couple_names'] ?? 'Wedding'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/css/intlTelInput.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: <?php echo $secondary; ?>;
            color: #334155;
            min-height: 100vh;
        }
        .hero {
            width: 100%;
            max-height: 350px;
            object-fit: cover;
            display: block;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            min-height: 100vh;
            box-shadow: 0 0 60px rgba(0,0,0,0.08);
        }
        .header {
            background-color: <?php echo $primary; ?>;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            font-family: 'Playfair Display', Georgia, serif;
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .header p { color: rgba(255,255,255,0.85); font-size: 14px; margin-top: 6px; }
        .content { padding: 40px 30px; }
        .greeting {
            text-align: center;
            margin-bottom: 32px;
        }
        .greeting .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .greeting h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: #1e293b;
            font-weight: 500;
        }
        .event-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .event-card .row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .event-card .row:last-child { border-bottom: none; }
        .event-card .row .icon { width: 30px; color: #94a3b8; }
        .event-card .row .info { flex: 1; }
        .event-card .row .info strong { color: #1e293b; }
        .event-card .row .info span { color: #1e293b; font-size: 14px; display: block; margin-top: 2px; }

        .iti { width: 100%; display: block; }
        .iti__flag { background-image: url("https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/img/flags.png"); }
        @media (min-resolution: 2dppx) {
            .iti__flag { background-image: url("https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/img/flags@2x.png"); }
        }
        .iti__selected-flag { background-color: #f8fafc; border-right: 1px solid #cbd5e1; border-top-left-radius: 8px; border-bottom-left-radius: 8px; padding: 0 12px; }
        .iti__country-list { border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); margin-top: 4px; }
        .form-group input[type="tel"] {
            padding-left: 90px !important; 
        }
        /* Form Styles */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #334155;
            background: #fff;
            transition: border-color 0.2s;
            outline: none;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: <?php echo $primary; ?>;
            box-shadow: 0 0 0 3px <?php echo $primary; ?>22;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }

        /* Radio Buttons */
        .rsvp-options { display: flex; gap: 12px; margin-bottom: 24px; }
        .rsvp-option {
            flex: 1;
            text-align: center;
        }
        .rsvp-option input[type="radio"] { display: none; }
        .rsvp-option label {
            display: block;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        .rsvp-option label:hover { border-color: <?php echo $primary; ?>; }
        .rsvp-option input[type="radio"]:checked + label.accept {
            border-color: #22c55e;
            background-color: #f0fdf4;
            color: #15803d;
        }
        .rsvp-option input[type="radio"]:checked + label.decline {
            border-color: #ef4444;
            background-color: #fef2f2;
            color: #dc2626;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            text-align: center;
            width: 100%;
        }
        .btn-primary {
            background-color: <?php echo $primary; ?>;
            color: #fff;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-outline {
            background: transparent;
            border: 2px solid <?php echo $primary; ?>;
            color: <?php echo $primary; ?>;
            margin-top: 10px;
        }
        .btn-outline:hover { background: <?php echo $primary; ?>11; }
        .btn-back {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #64748b;
            margin-top: 10px;
        }
        .btn-back:hover { background: #f8fafc; border-color: #cbd5e1; }

        .success-box {
            text-align: center;
            padding: 30px 0;
        }
        .success-box > .icon { font-size: 48px; margin-bottom: 16px; }
        .success-box h3 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .success-box p { font-size: 14px; color: #64748b; line-height: 1.7; margin-bottom: 8px; }

        .error-box {
            text-align: center;
            padding: 60px 30px;
        }
        .error-box .icon { font-size: 48px; margin-bottom: 16px; }
        .error-box h3 { font-size: 20px; color: #1e293b; margin-bottom: 10px; }
        .error-box p { font-size: 14px; color: #64748b; }

        .footer {
            background: <?php echo $secondary; ?>;
            padding: 20px 30px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        .conditional-fields { display: none; }
        .conditional-fields.show { display: block; }

        @media (max-width: 600px) {
            .content { padding: 30px 20px; }
            .rsvp-options { flex-direction: column; }
        }
        /* Embed mode — remove shadow and background for seamless iframe */
        <?php if ($embedMode): ?>
        body { background: transparent; }
        .container { box-shadow: none; }
        <?php
endif; ?>
    </style>
</head>
<body>
<div class="container">
    <?php if (!empty($heroImage)): ?>
        <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="<?php echo htmlspecialchars($guest['couple_names'] ?? ''); ?>" class="hero" />
    <?php
endif; ?>

    <div class="header">
        <h1><?php echo htmlspecialchars($guest['couple_names'] ?? 'Wedding Invitation'); ?></h1>
        <p>RSVP</p>
    </div>

    <div class="content">
        <?php if (!empty($error)): ?>
            <?php if ($error === 'deadline_passed'): ?>
                <div class="error-box" style="background-color: #fffbfa; border: 1px solid #fce7e0; border-radius: 12px; padding: 40px 30px;">
                    <div class="icon" style="color: <?php echo $primary; ?>;"><i class="fa-regular fa-clock"></i></div>
                    <h3 style="font-family: 'Playfair Display', serif; color: #1e293b; font-size: 24px;"><?php echo $L['rsvp_closed']; ?></h3>
                    <p style="color: #64748b; font-size: 15px; line-height: 1.8; margin-top: 12px;"><?php echo $L['rsvp_closed_msg']; ?></p>
                </div>
            <?php
    else: ?>
                <div class="error-box">
                    <div class="icon" style="color:#ef4444;"><i class="fa-solid fa-circle-exclamation"></i></div>
                    <h3>Oops!</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php
    endif; ?>
        <?php
elseif (($success || ($alreadyResponded && $_SERVER['REQUEST_METHOD'] !== 'POST')) && !$editMode): ?>
            <!-- Success / Already Responded -->
            <div class="success-box">
                <?php if ($guest['rsvp_status'] === 'attending'): ?>
                    <div class="icon">🎉</div>
                    <?php
        // Custom thank you based on group_label
        $groupLabel = strtolower(trim($guest['group_label'] ?? ''));
        $thankTitle = $L['thrilled'];
        $thankMsg = $L['cant_wait'];
        if (strpos($groupLabel, 'family') !== false || strpos($groupLabel, 'keluarga') !== false) {
            $thankTitle = $lang === 'id' ? 'Keluarga Tercinta!' : 'Dearest Family!';
            $thankMsg = $lang === 'id' ? 'Kehadiran keluarga sangat berarti bagi kami. Tidak sabar merayakan bersama!' : 'Your presence means the world to us. Family makes every celebration special!';
        }
        elseif (strpos($groupLabel, 'friend') !== false || strpos($groupLabel, 'teman') !== false || strpos($groupLabel, 'sahabat') !== false) {
            $thankTitle = $lang === 'id' ? 'Yeay, Datang Ya!' : 'Yay, You\'re Coming!';
            $thankMsg = $lang === 'id' ? 'Pesta tanpa sahabat tidak akan seru! Siap-siap bersenang-senang!' : 'The party wouldn\'t be the same without you! Get ready to celebrate!';
        }
        elseif (strpos($groupLabel, 'vip') !== false) {
            $thankTitle = $lang === 'id' ? 'Tamu Istimewa!' : 'Honored Guest!';
            $thankMsg = $lang === 'id' ? 'Kehadiran Anda sangat kami hormati dan nantikan.' : 'We are deeply honored by your presence. Thank you for being part of our special day.';
        }
        elseif (strpos($groupLabel, 'colleague') !== false || strpos($groupLabel, 'rekan') !== false || strpos($groupLabel, 'kantor') !== false) {
            $thankTitle = $lang === 'id' ? 'Terima Kasih!' : 'Wonderful!';
            $thankMsg = $lang === 'id' ? 'Senang sekali Anda bisa hadir! Sampai jumpa di hari bahagia kami.' : 'So glad you can make it! Looking forward to celebrating together.';
        }
?>
                    <h3><?php echo $thankTitle; ?></h3>
                    <p><?php echo $L['thank_confirm']; ?> <strong><?php echo htmlspecialchars($guest['name']); ?></strong>!</p>
                    <p><?php echo $thankMsg; ?></p>

                    <?php
        $hasMeal = !empty($guest['meal_choice']);
        $hasDietary = !empty($guest['dietary_notes']);
        $hasSong = !empty($guest['song_request']);
        $hasNotes = !empty($guest['notes']);
        $hasAccommodation = !empty($guest['accommodation']);
        $eventsAttending = json_decode($guest['events_attending'] ?? '[]', true) ?: [];
        $hasEvents = !empty($eventsAttending);

        $accompanying = json_decode($guest['accompanying_guests'] ?? '[]', true) ?: [];
        $hasAnyDetails = $hasMeal || $hasDietary || $hasSong || $hasNotes || $hasAccommodation || $hasEvents || !empty($accompanying);

        if ($hasAnyDetails): ?>
                        <div class="event-card" style="text-align:left;margin-top:20px;">
                            <?php if ($hasEvents): ?>
                            <div class="row" style="flex-direction: column; align-items: flex-start;">
                                <div style="display: flex; gap: 10px; width: 100%; margin-bottom: 4px;">
                                    <div class="icon"><i class="fa-regular fa-calendar-check"></i></div>
                                    <div class="info"><strong><?php echo $L['events_attending']; ?></strong></div>
                                </div>
                                <div style="padding-left: 40px; font-size: 14px; color: #1e293b;">
                                    <ul style="list-style-type: disc; padding-left: 16px;">
                                        <?php foreach ($eventsAttending as $evt): ?>
                                            <li><?php echo htmlspecialchars($evt); ?></li>
                                        <?php
                endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <?php
            endif; ?>

                            <?php if ($hasAccommodation): ?>
                            <div class="row">
                                <div class="icon"><i class="fa-solid fa-bed"></i></div>
                                <div class="info"><strong>Accommodation</strong><span><?php echo htmlspecialchars($guest['accommodation']); ?></span></div>
                            </div>
                            <?php
            endif; ?>

                            <?php if ($hasMeal): ?>
                            <div class="row">
                                <div class="icon"><i class="fa-solid fa-utensils"></i></div>
                                <div class="info"><strong><?php echo $L['your_meal']; ?></strong><span><?php echo htmlspecialchars($guest['meal_choice']); ?></span></div>
                            </div>
                            <?php
            endif; ?>
                            
                            <?php if ($hasDietary): ?>
                            <div class="row">
                                <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <div class="info"><strong><?php echo $L['dietary_options']; ?></strong><span><?php echo htmlspecialchars($guest['dietary_notes']); ?></span></div>
                            </div>
                            <?php
            endif; ?>

                            <?php if ($hasSong): ?>
                            <div class="row">
                                <div class="icon"><i class="fa-solid fa-music"></i></div>
                                <div class="info"><strong><?php echo $L['song_request']; ?></strong><span><?php echo htmlspecialchars($guest['song_request']); ?></span></div>
                            </div>
                            <?php
            endif; ?>

                            <?php if ($hasNotes): ?>
                            <div class="row">
                                <div class="icon"><i class="fa-regular fa-note-sticky"></i></div>
                                <div class="info"><strong>Notes</strong><span><?php echo htmlspecialchars($guest['notes']); ?></span></div>
                            </div>
                            <?php
            endif; ?>
                            
                            <?php if (!empty($accompanying)):
                foreach ($accompanying as $idx => $accGuest):
?>
                                <div class="row" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                                    <div style="display: flex; gap: 10px; width: 100%;">
                                        <div class="icon"><i class="fa-regular fa-user"></i></div>
                                        <div class="info">
                                            <strong>Guest <?php echo $idx + 1; ?>: <?php echo htmlspecialchars($accGuest['name']); ?></strong>
                                            <?php if (!empty($accGuest['meal'])): ?>
                                            <span>Meal: <?php echo htmlspecialchars($accGuest['meal']); ?></span>
                                            <?php
                    endif; ?>
                                            <?php if (!empty($accGuest['dietary_notes'])): ?>
                                                <span style="color: #ef4444; margin-top: 4px;"><i class="fa-solid fa-triangle-exclamation" style="font-size:10px; margin-right:4px;"></i>Allergies: <?php echo htmlspecialchars($accGuest['dietary_notes']); ?></span>
                                            <?php
                    endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php
                endforeach;
            endif;
?>
                        </div>
                    <?php
        endif; ?>

                    <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="<?php echo htmlspecialchars($calendarLink . '&type=google'); ?>" target="_blank" class="btn" style="flex:1; min-width:140px; background-color:#4285F4; color:#fff; display:flex; align-items:center; justify-content:center; gap:8px; border-radius:8px; padding:14px 20px; text-decoration:none; font-size:14px; font-weight:600; transition:all 0.2s;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            Google Calendar
                        </a>
                        <a href="<?php echo htmlspecialchars($calendarLink); ?>" class="btn" style="flex:1; min-width:140px; background-color:#333333; color:#fff; display:flex; align-items:center; justify-content:center; gap:8px; border-radius:8px; padding:14px 20px; text-decoration:none; font-size:14px; font-weight:600; transition:all 0.2s;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                            Apple Calendar
                        </a>
                    </div>

                    <?php if (!empty($giftRegistryUrl)): ?>
                        <a href="<?php echo htmlspecialchars($giftRegistryUrl); ?>" target="_blank" class="btn btn-outline" style="margin-top:12px;"><?php echo htmlspecialchars($L['view_gift_registry']); ?></a>
                    <?php
        endif; ?>

                    <?php if (!$deadlinePassed): ?>
                        <a href="rsvp.php?token=<?php echo urlencode($token); ?>&edit=1<?php echo $embedSuffix; ?>" class="btn btn-back" style="margin-top:12px;"><?php echo $L['update_rsvp_btn']; ?></a>
                    <?php
        endif; ?>

                <?php
    else: ?>
                    <div class="icon"><i class="fa-regular fa-envelope"></i></div>
                    <h3><?php echo $L['thank_you']; ?></h3>
                    <p><?php echo $L['understand']; ?> <strong><?php echo htmlspecialchars($guest['name']); ?></strong>.</p>
                    <p><?php echo $L['youll_be_missed']; ?></p>

                    <?php if (!$deadlinePassed): ?>
                        <a href="rsvp.php?token=<?php echo urlencode($token); ?>&edit=1<?php echo $embedSuffix; ?>" class="btn btn-back" style="margin-top:12px;"><?php echo $L['update_rsvp_btn']; ?></a>
                    <?php
        endif; ?>
                <?php
    endif; ?>

                <?php if (!empty($websiteUrl)): ?>
                    <a href="<?php echo htmlspecialchars($websiteUrl); ?>" class="btn btn-back" style="margin-top:16px;">← Back to Wedding Website</a>
                <?php
    endif; ?>
            </div>

        <?php
else: ?>
            <!-- RSVP Form -->
            <div class="greeting">
                <div class="label"><?php echo $L['youre_invited']; ?></div>
                <h2><?php echo $L['dear']; ?> <?php echo htmlspecialchars($guest['group_label'] ?: $guest['name']); ?></h2>
                <?php if ($countdownDays !== null): ?>
                    <div style="display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, <?php echo $primary; ?>22, <?php echo $primary; ?>11); border:1px solid <?php echo $primary; ?>33; padding:8px 16px; border-radius:20px; margin-top:12px; font-size:13px; color:<?php echo $primary; ?>; font-weight:600;">
                        ⏳ <span style="font-size:18px; font-weight:700;"><?php echo $countdownDays; ?></span> <?php echo $L['days_to_go']; ?>
                    </div>
                <?php
    endif; ?>
            </div>

            <!-- Event Details -->
            <div class="event-card">
                <?php if (!empty($guest['event_date'])): ?>
                    <div class="row"><div class="icon"><i class="fa-regular fa-calendar" style="font-size:16px;"></i></div><div class="info"><strong><?php echo date('F j, Y', strtotime($guest['event_date'])); ?></strong><?php echo !empty($guest['event_time']) ? '<span>' . ($lang === 'id' ? 'pukul ' : 'at ') . date('g:i A', strtotime($guest['event_time'])) . '</span>' : ''; ?></div></div>
                <?php
    endif; ?>
                <?php if (!empty($guest['venue_name'])): ?>
                    <div class="row"><div class="icon"><i class="fa-solid fa-location-dot" style="font-size:16px;"></i></div><div class="info"><strong><?php echo htmlspecialchars($guest['venue_name']); ?></strong><?php echo !empty($guest['venue_address']) ? '<span>' . htmlspecialchars($guest['venue_address']) . '</span>' : ''; ?></div></div>
                <?php
    endif; ?>
                <?php if ($guest['seats_allocated'] > 1): ?>
                    <div class="row"><div class="icon"><i class="fa-solid fa-user-group" style="font-size:14px;"></i></div><div class="info"><strong><?php echo $guest['seats_allocated']; ?> <?php echo $L['seats']; ?></strong><span><?php echo $L['reserved_honor']; ?></span></div></div>
                <?php
    endif; ?>
                <?php if (!empty($guest['dress_code'])): ?>
                    <div class="row"><div class="icon"><i class="fa-solid fa-shirt" style="font-size:14px;"></i></div><div class="info"><strong><?php echo $L['dress_code']; ?></strong><span><?php echo htmlspecialchars($guest['dress_code']); ?></span></div></div>
                <?php
    endif; ?>
            </div>

            <form method="POST" action="rsvp.php?token=<?php echo urlencode($token); ?><?php echo $embedSuffix; ?>" id="rsvpForm">
                <p style="font-size:14px;color:#475569;margin-bottom:16px;font-weight:500;"><?php echo $L['will_you_join']; ?></p>

                <div class="rsvp-options">
                    <div class="rsvp-option">
                        <input type="radio" name="rsvp_status" id="accept" value="attending" required>
                        <label for="accept" class="accept"><?php echo $L['accept']; ?></label>
                    </div>
                    <div class="rsvp-option">
                        <input type="radio" name="rsvp_status" id="decline" value="declined">
                        <label for="decline" class="decline"><?php echo $L['decline']; ?></label>
                    </div>
                </div>

                <!-- Conditional Fields (shown only when Accepting) -->
                <div id="attendingFields" class="conditional-fields">
                    <?php
    $eventsListStr = $guest['events_list'] ?? '[]';
    $eventsList = json_decode($eventsListStr, true) ?: [];
    if (!empty($rsvpFields['events']) && !empty($eventsList)): ?>
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label><i class="fa-regular fa-calendar-check" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['events_which']; ?></label>
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                                <?php foreach ($eventsList as $idx => $event): ?>
                                    <label style="display: flex; align-items: flex-start; gap: 10px; font-weight: 400; font-size: 14px; cursor: pointer;">
                                        <input type="checkbox" name="events_attending[]" value="<?php echo htmlspecialchars($event); ?>" checked style="width: 18px; height: 18px; margin-top: 2px; accent-color: <?php echo $primary; ?>;">
                                        <span style="flex: 1;"><?php echo htmlspecialchars($event); ?></span>
                                    </label>
                                <?php
        endforeach; ?>
                            </div>
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['meal_choice']) && !empty($mealOptions)): ?>
                        <div class="form-group">
                            <label><i class="fa-solid fa-utensils" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['meal_pref']; ?></label>
                            <select name="meal_choice">
                                <option value=""><?php echo $L['select_meal']; ?></option>
                                <?php foreach ($mealOptions as $meal): ?>
                                    <option value="<?php echo htmlspecialchars($meal); ?>"><?php echo htmlspecialchars($meal); ?></option>
                                <?php
        endforeach; ?>
                            </select>
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['dietary_notes'])): ?>
                        <div class="form-group">
                            <label><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['dietary']; ?></label>
                            <textarea name="dietary_notes" placeholder="<?php echo $L['dietary_placeholder']; ?>"></textarea>
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['plus_one']) && $guest['seats_allocated'] > 1): ?>
                        <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                            <h4 style="font-size:15px; font-weight:600; color:#1e293b; margin-bottom:12px;"><?php echo $L['accompanying']; ?></h4>
                            <p style="font-size:13px; color:#64748b; margin-bottom:16px;"><?php echo sprintf($L['additional_seats'], $guest['seats_allocated'] - 1); ?></p>
                            
                            <?php for ($i = 0; $i < ($guest['seats_allocated'] - 1); $i++): ?>
                                <div style="background: #f8fafc; border: 1px solid #f1f5f9; padding: 16px; border-radius: 12px; margin-bottom: 16px;">
                                    <h5 style="font-size:13px; font-weight:600; color:#475569; margin-bottom:12px;"><?php echo sprintf($L['guest_n'], $i + 1); ?></h5>
                                    <div class="form-group">
                                        <label><i class="fa-regular fa-user" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['full_name']; ?></label>
                                        <input type="text" name="additional_guests[<?php echo $i; ?>][name]" placeholder="<?php echo $L['guest_full_name']; ?>">
                                    </div>
                                    
                                    <?php if (!empty($rsvpFields['meal_choice']) && !empty($mealOptions)): ?>
                                        <div class="form-group" style="margin-bottom:12px;">
                                            <label><i class="fa-solid fa-utensils" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['meal_pref']; ?></label>
                                            <select name="additional_guests[<?php echo $i; ?>][meal]">
                                                <option value=""><?php echo $L['select_meal_guest']; ?></option>
                                                <?php foreach ($mealOptions as $meal): ?>
                                                    <option value="<?php echo htmlspecialchars($meal); ?>"><?php echo htmlspecialchars($meal); ?></option>
                                                <?php
                endforeach; ?>
                                            </select>
                                        </div>
                                    <?php
            endif; ?>
            
                                    <?php if (!empty($rsvpFields['dietary_notes'])): ?>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['dietary']; ?></label>
                                            <input type="text" name="additional_guests[<?php echo $i; ?>][dietary_notes]" placeholder="<?php echo $L['dietary_placeholder']; ?>">
                                        </div>
                                    <?php
            endif; ?>
                                </div>
                            <?php
        endfor; ?>
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['song_request'])): ?>
                        <div class="form-group">
                            <label><i class="fa-solid fa-music" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['song_request']; ?></label>
                            <input type="text" name="song_request" placeholder="<?php echo $L['song_placeholder']; ?>">
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['phone'])): ?>
                        <div class="form-group">
                            <label><i class="fa-solid fa-mobile-screen-button" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['phone']; ?></label>
                            <input type="tel" id="mobile_number" name="phone" placeholder="e.g. 812 3456 7890">
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['accommodation'])): ?>
                        <div class="form-group">
                            <label><i class="fa-solid fa-bed" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['accommodation']; ?></label>
                            <input type="text" name="accommodation" placeholder="<?php echo $L['accommodation_placeholder']; ?>">
                        </div>
                    <?php
    endif; ?>

                    <?php if (!empty($rsvpFields['notes'])): ?>
                        <div class="form-group">
                            <label><i class="fa-regular fa-note-sticky" style="margin-right:8px; color:#cbd5e1;"></i> <?php echo $L['notes']; ?></label>
                            <textarea name="notes" placeholder="<?php echo $L['notes_placeholder']; ?>"></textarea>
                        </div>
                    <?php
    endif; ?>
                </div>

                <button type="submit" class="btn btn-primary"><?php echo $alreadyResponded ? $L['update_rsvp'] : $L['submit']; ?></button>

                <?php if (!empty($websiteUrl)): ?>
                    <a href="<?php echo htmlspecialchars($websiteUrl); ?>" class="btn btn-back"><?php echo $L['back_website']; ?></a>
                <?php
    endif; ?>
            </form>
        <?php
endif; ?>
    </div>

    <div class="footer">
        <p>Powered by <a href="<?= htmlspecialchars(!empty($guest['email_footer_link']) ? $guest['email_footer_link'] : EMAIL_FOOTER_LINK) ?>" target="_blank" style="color:inherit;text-decoration:none;"><strong><?= htmlspecialchars(!empty($guest['email_footer_text']) ? $guest['email_footer_text'] : EMAIL_FOOTER_TEXT) ?></strong></a></p>
    </div>
</div>

<script>
    // Show/hide conditional fields based on RSVP selection
    const radios = document.querySelectorAll('input[name="rsvp_status"]');
    const attendingFields = document.getElementById('attendingFields');

    if (radios && attendingFields) {
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'attending') {
                    attendingFields.classList.add('show');
                } else {
                    attendingFields.classList.remove('show');
                }
            });
        });
    }
</script>
<?php if ($embedMode): ?>
<script>
// Auto-resize: kirim tinggi halaman ke parent (WordPress)
function sendHeight() {
    var h = document.documentElement.scrollHeight;
    window.parent.postMessage({ type: 'rsvp-resize', height: h }, '*');
}
sendHeight();
window.addEventListener('resize', sendHeight);
// Observe DOM changes (e.g. showing/hiding conditional fields)
new MutationObserver(sendHeight).observe(document.body, { childList: true, subtree: true, attributes: true });
</script>
<?php
endif; ?>
</body>
</html>
