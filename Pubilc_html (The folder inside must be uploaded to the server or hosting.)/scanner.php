<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

Auth::startSession();
$isAdmin = Auth::check();
$isReceptionist = Auth::checkReceptionist();

if (!$isAdmin && !$isReceptionist) {
    header("Location: login.php?tab=receptionist");
    exit;
}

$clientId = $_SESSION['client_id'] ?? null;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Token is missing.");
}

$db = new Database();
$pdo = $db->pdo;

// Fetch guest
$stmt = $pdo->prepare("SELECT g.*, c.color_primary, c.color_secondary, c.couple_names, c.hero_image_url 
                        FROM guests g 
                        JOIN clients c ON g.client_id = c.id 
                        WHERE g.token = ? AND g.client_id = ?");
$stmt->execute([$token, $clientId]);
$guest = $stmt->fetch();

if (!$guest) {
    die("Invalid check-in token. Guest not found or does not belong to your account.");
}

$primary = $guest['color_primary'] ?? '#4A6741';
$coupleNames = $guest['couple_names'] ?? 'Our Wedding';
$alreadyCheckedIn = (bool)$guest['check_in_status'];
$checkInTime = $guest['check_in_time'];

$accompanyingCount = 0;
if (!empty($guest['accompanying_guests'])) {
    $arr = json_decode($guest['accompanying_guests'], true);
    if (is_array($arr)) {
        $accompanyingCount = count($arr);
    }
}
$totalPack = 1 + $accompanyingCount;

// Process Check-in
if (!$alreadyCheckedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkin'])) {
        $stmt = $pdo->prepare("UPDATE guests SET check_in_status = 1, check_in_time = NOW() WHERE token = ?");
        $stmt->execute([$token]);

        $alreadyCheckedIn = true;
        // Re-fetch to get correct check in time
        $stmt = $pdo->prepare("SELECT check_in_time FROM guests WHERE token = ?");
        $stmt->execute([$token]);
        $checkInTime = $stmt->fetchColumn();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in | <?php echo htmlspecialchars($coupleNames); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $primary; ?>;
        }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="bg-white max-w-sm w-full rounded-3xl shadow-xl overflow-hidden text-center relative">
        <div class="h-32 bg-[var(--primary)] absolute top-0 left-0 right-0 z-0"></div>
        
        <div class="relative z-10 pt-16 px-6 pb-8">
            <div class="w-24 h-24 bg-white rounded-full mx-auto shadow-md border-4 border-white flex items-center justify-center mb-4">
                <?php if ($alreadyCheckedIn): ?>
                    <i class="fa-regular fa-circle-check text-5xl text-green-500"></i>
                <?php
else: ?>
                    <i class="fa-regular fa-id-badge text-5xl" style="color: var(--primary)"></i>
                <?php
endif; ?>
            </div>

            <h1 class="text-2xl font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($guest['name']); ?></h1>
            <p class="text-slate-500 text-sm font-medium mb-6 uppercase tracking-wider"><?php echo htmlspecialchars($guest['group_label'] ?: 'Guest'); ?></p>

            <div class="bg-slate-50 rounded-2xl p-4 mb-6 border border-slate-100 text-left space-y-3">
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-xs text-slate-400 uppercase tracking-widest font-semibold">Total Entry</span>
                    <span class="text-sm font-bold text-slate-800"><?php echo $totalPack; ?> Pax</span>
                </div>
                <div class="flex justify-between items-center pb-1">
                    <span class="text-xs text-slate-400 uppercase tracking-widest font-semibold">Table No.</span>
                    <span class="text-sm font-bold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md">
                        <?php echo htmlspecialchars($guest['table_number'] ?: 'Any'); ?>
                    </span>
                </div>
            </div>

            <?php if ($alreadyCheckedIn): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 mb-4">
                    <p class="text-sm font-bold">✅ Already Checked-in</p>
                    <p class="text-xs mt-1 opacity-80">at <?php echo date('d M Y, H:i', strtotime($checkInTime)); ?></p>
                </div>
                <p class="text-xs text-slate-400 mt-6">Enjoy the celebration!</p>
            <?php
else: ?>
                <form method="POST">
                    <button type="submit" name="confirm_checkin" class="w-full py-4 px-6 text-white font-semibold rounded-2xl shadow-[0_8px_20px_rgba(0,0,0,0.1)] hover:-translate-y-1 transition duration-300 transform" style="background-color: var(--primary);">
                        Confirm Check-In
                    </button>
                </form>
                <p class="text-xs text-slate-400 mt-4"><i class="fa-solid fa-lock"></i> Staff validation required</p>
            <?php
endif; ?>

        </div>
    </div>

</body>
</html>
