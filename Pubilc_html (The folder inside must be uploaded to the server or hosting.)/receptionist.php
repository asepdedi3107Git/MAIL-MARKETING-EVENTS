<?php
/**
 * Receptionist Dashboard — Mobile-first check-in dashboard
 * Limited access: QR scanner, guest search, manual check-in, live stats
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

Auth::startSession();

// Check receptionist session
$receptionist = Auth::checkReceptionist();
if (!$receptionist) {
    header("Location: login.php?tab=receptionist");
    exit;
}

$clientId = $receptionist['client_id'];
$primary = $receptionist['color_primary'] ?? '#0f766e';
$csrfToken = Auth::generateCsrfToken();

$db = new Database();
$pdo = $db->pdo;

// Handle API calls from this page
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];

    // Verify CSRF for POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
        if (!Auth::verifyCsrfToken($token)) {
            die(json_encode(['status' => false, 'message' => 'Invalid CSRF token']));
        }
    }

    switch ($action) {
        case 'get_guests':
            $stmt = $pdo->prepare("SELECT id, name, email, phone, group_label, table_number, seats_allocated, rsvp_status, meal_choice, dietary_notes, check_in_status, check_in_time, accompanying_guests FROM guests WHERE client_id = ? ORDER BY name ASC");
            $stmt->execute([$clientId]);
            echo json_encode(['status' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_stats':
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN rsvp_status='attending' THEN 1 ELSE 0 END) as attending, SUM(CASE WHEN check_in_status=1 THEN 1 ELSE 0 END) as checked_in FROM guests WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $stats = $stmt->fetch();
            // Total pax (including accompanying)
            $stmt2 = $pdo->prepare("SELECT SUM(seats_allocated) as total_pax FROM guests WHERE client_id = ? AND rsvp_status='attending'");
            $stmt2->execute([$clientId]);
            $pax = $stmt2->fetch();
            $stats['total_pax'] = (int)($pax['total_pax'] ?? 0);
            echo json_encode(['status' => true, 'data' => $stats]);
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
            echo json_encode(['status' => true, 'message' => $newStatus ? 'Checked in!' : 'Check-in undone']);
            break;

        case 'checkin_by_token':
            $token = trim($_POST['token'] ?? '');
            $stmt = $pdo->prepare("SELECT id, name, check_in_status FROM guests WHERE token = ? AND client_id = ?");
            $stmt->execute([$token, $clientId]);
            $guest = $stmt->fetch();
            if (!$guest) {
                echo json_encode(['status' => false, 'message' => 'Guest not found for this QR code']);
                break;
            }
            if ($guest['check_in_status']) {
                echo json_encode(['status' => false, 'message' => $guest['name'] . ' is already checked in', 'already' => true, 'guest' => $guest]);
                break;
            }
            $pdo->prepare("UPDATE guests SET check_in_status = 1, check_in_time = NOW() WHERE id = ?")->execute([$guest['id']]);
            echo json_encode(['status' => true, 'message' => '✅ ' . $guest['name'] . ' checked in!', 'guest' => $guest]);
            break;

        default:
            echo json_encode(['status' => false, 'message' => 'Unknown action']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Receptionist — <?php echo htmlspecialchars($receptionist['couple_names']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { --primary: <?php echo $primary; ?>; }
        body { font-family: 'Inter', sans-serif; background: #f0fdf4; }
        .glass { background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); }
        #qr-reader { width: 100%; border-radius: 16px; overflow: hidden; }
        #qr-reader video { border-radius: 16px; }
        #qr-reader__scan_region { background: transparent !important; }
        #qr-reader__dashboard { padding: 8px !important; }
        #qr-reader__dashboard button { background: var(--primary) !important; color: white !important; border: none !important; border-radius: 8px !important; padding: 8px 16px !important; font-family: 'Inter',sans-serif !important; }
    </style>
</head>
<body class="min-h-screen" x-data="receptionistApp()" x-init="init()">

    <!-- Top Bar -->
    <div class="sticky top-0 z-50 glass border-b border-green-100 shadow-sm px-4 py-3">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <div>
                <h1 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($receptionist['couple_names']); ?></h1>
                <p class="text-[11px] text-slate-500">👋 <?php echo htmlspecialchars($receptionist['name']); ?> · Receptionist</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-lg font-bold" style="color:var(--primary)" x-text="stats.checked_in + '/' + stats.attending"></div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Checked In</div>
                </div>
                <a href="login.php?action=logout" class="p-2 text-slate-400 hover:text-red-500 transition" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-4 space-y-4">

        <!-- Stats Cards -->
        <div class="grid grid-cols-3 gap-3">
            <div class="glass rounded-2xl p-4 text-center border border-green-100 shadow-sm">
                <div class="text-2xl font-bold text-slate-800" x-text="stats.attending">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-1">Attending</div>
            </div>
            <div class="glass rounded-2xl p-4 text-center border border-green-200 shadow-sm" style="background:linear-gradient(135deg, #f0fdf4, #dcfce7)">
                <div class="text-2xl font-bold" style="color:var(--primary)" x-text="stats.checked_in">0</div>
                <div class="text-[10px] text-green-600 uppercase tracking-wider font-semibold mt-1">Checked In</div>
            </div>
            <div class="glass rounded-2xl p-4 text-center border border-green-100 shadow-sm">
                <div class="text-2xl font-bold text-slate-800" x-text="stats.total_pax">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-1">Total Pax</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="glass rounded-xl p-3 border border-green-100 shadow-sm">
            <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1.5">
                <span>Check-in Progress</span>
                <span x-text="stats.attending > 0 ? Math.round((stats.checked_in / stats.attending) * 100) + '%' : '0%'"></span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2.5">
                <div class="h-2.5 rounded-full transition-all duration-500" style="background:var(--primary)" :style="'width:' + (stats.attending > 0 ? (stats.checked_in / stats.attending * 100) : 0) + '%'"></div>
            </div>
        </div>

        <!-- Mode Tabs -->
        <div class="flex bg-white rounded-xl p-1 border border-green-100 shadow-sm">
            <button @click="mode='scanner'" :class="mode==='scanner' ? 'bg-green-50 text-green-700 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-qrcode"></i> Scanner
            </button>
            <button @click="mode='search'" :class="mode==='search' ? 'bg-green-50 text-green-700 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <button @click="mode='table'" :class="mode==='table' ? 'bg-green-50 text-green-700 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-chair"></i> Tables
            </button>
        </div>

        <!-- QR Scanner Mode -->
        <div x-show="mode==='scanner'" x-cloak>
            <div class="glass rounded-2xl border border-green-100 shadow-sm overflow-hidden p-4">
                <div id="qr-reader" class="mb-3"></div>
                <!-- Scan Result -->
                <div x-show="scanResult" x-transition class="rounded-xl p-4 text-center" :class="scanSuccess ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'">
                    <p class="text-sm font-semibold" :class="scanSuccess ? 'text-green-700' : 'text-amber-700'" x-text="scanResult"></p>
                </div>
            </div>
        </div>

        <!-- Search Mode -->
        <div x-show="mode==='search'" x-cloak>
            <div class="glass rounded-2xl border border-green-100 shadow-sm p-4">
                <div class="relative mb-4">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" x-model="searchQuery" @input="filterGuests()" placeholder="Search guest name..." class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-green-200 focus:border-green-400 outline-none">
                </div>
                <!-- Guest List -->
                <div class="space-y-2 max-h-[60vh] overflow-y-auto">
                    <template x-for="guest in filteredGuestList" :key="guest.id">
                        <div class="flex items-center justify-between p-3 rounded-xl border transition" :class="guest.check_in_status == 1 ? 'bg-green-50 border-green-200' : 'bg-white border-slate-100 hover:border-green-200'">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm text-slate-800 truncate" x-text="guest.name"></span>
                                    <span x-show="guest.rsvp_status!=='attending'" class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-semibold" x-text="guest.rsvp_status"></span>
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-[11px] text-slate-500">
                                    <span x-show="guest.table_number" class="flex items-center gap-1"><i class="fa-solid fa-chair text-[9px]"></i> <span x-text="guest.table_number"></span></span>
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-users text-[9px]"></i> <span x-text="guest.seats_allocated"></span> pax</span>
                                    <span x-show="guest.meal_choice" class="flex items-center gap-1"><i class="fa-solid fa-utensils text-[9px]"></i> <span x-text="guest.meal_choice"></span></span>
                                </div>
                            </div>
                            <button @click="toggleCheckIn(guest)" class="ml-3 flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center text-lg transition" :class="guest.check_in_status == 1 ? 'bg-green-500 text-white shadow-md' : 'bg-slate-100 text-slate-400 hover:bg-green-100 hover:text-green-600'">
                                <i :class="guest.check_in_status == 1 ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle'"></i>
                            </button>
                        </div>
                    </template>
                    <div x-show="filteredGuestList.length === 0" class="text-center py-8 text-slate-400 text-sm">
                        <i class="fa-solid fa-magnifying-glass text-2xl mb-2 block"></i> No guests found
                    </div>
                </div>
            </div>
        </div>

        <!-- Table View Mode -->
        <div x-show="mode==='table'" x-cloak>
            <div class="space-y-3">
                <template x-for="(tableGuests, tableNum) in groupedByTable" :key="tableNum">
                    <div class="glass rounded-2xl border border-green-100 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                            <span class="text-sm font-bold text-slate-700 flex items-center gap-2"><i class="fa-solid fa-chair text-green-600"></i> Table <span x-text="tableNum"></span></span>
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold" x-text="tableGuests.filter(g => g.check_in_status == 1).length + '/' + tableGuests.length + ' in'"></span>
                        </div>
                        <div class="divide-y divide-slate-50">
                            <template x-for="guest in tableGuests" :key="guest.id">
                                <div class="flex items-center justify-between px-4 py-2.5">
                                    <div>
                                        <span class="text-sm font-medium text-slate-700" x-text="guest.name"></span>
                                        <span class="text-[10px] text-slate-400 ml-1" x-text="guest.seats_allocated + ' pax'"></span>
                                    </div>
                                    <button @click="toggleCheckIn(guest)" class="w-8 h-8 rounded-lg flex items-center justify-center transition" :class="guest.check_in_status == 1 ? 'bg-green-500 text-white' : 'bg-slate-100 text-slate-400 hover:bg-green-100'">
                                        <i :class="guest.check_in_status == 1 ? 'fa-solid fa-check text-xs' : 'fa-regular fa-circle text-xs'"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <div x-show="Object.keys(groupedByTable).length === 0" class="text-center py-12 text-slate-400 text-sm">
                    <i class="fa-solid fa-chair text-3xl mb-3 block"></i> No table assignments found
                </div>
            </div>
        </div>

    </div>

    <!-- Toast -->
    <div class="fixed bottom-4 left-4 right-4 z-[9999] flex flex-col items-center gap-2 pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'" class="text-white px-5 py-3 rounded-xl shadow-lg text-sm font-semibold pointer-events-auto max-w-sm text-center">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    <script>
    function receptionistApp() {
        const csrfToken = '<?php echo $csrfToken; ?>';
        let html5QrcodeScanner = null;

        return {
            mode: 'scanner',
            guests: [],
            filteredGuestList: [],
            searchQuery: '',
            stats: { total: 0, attending: 0, checked_in: 0, total_pax: 0 },
            toasts: [],
            scanResult: '',
            scanSuccess: false,
            groupedByTable: {},

            async init() {
                await this.loadGuests();
                await this.loadStats();
                this.initScanner();
                // Auto-refresh every 15s
                setInterval(() => this.loadStats(), 15000);
            },

            async _api(action, data = null) {
                const url = 'receptionist.php?api=' + action;
                const opts = { method: data ? 'POST' : 'GET' };
                if (data) {
                    const params = new URLSearchParams(data);
                    params.append('csrf_token', csrfToken);
                    opts.body = params;
                    opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken };
                }
                const res = await fetch(url, opts);
                return res.json();
            },

            async loadGuests() {
                const res = await this._api('get_guests');
                if (res.status) {
                    this.guests = res.data;
                    this.filterGuests();
                    this.buildTableView();
                }
            },

            async loadStats() {
                const res = await this._api('get_stats');
                if (res.status) this.stats = res.data;
            },

            filterGuests() {
                const q = this.searchQuery.toLowerCase().trim();
                if (!q) {
                    this.filteredGuestList = this.guests;
                } else {
                    this.filteredGuestList = this.guests.filter(g =>
                        g.name.toLowerCase().includes(q) ||
                        (g.table_number && g.table_number.toLowerCase().includes(q)) ||
                        (g.phone && g.phone.includes(q))
                    );
                }
            },

            buildTableView() {
                const groups = {};
                this.guests.filter(g => g.table_number).forEach(g => {
                    if (!groups[g.table_number]) groups[g.table_number] = [];
                    groups[g.table_number].push(g);
                });
                this.groupedByTable = groups;
            },

            async toggleCheckIn(guest) {
                const newStatus = guest.check_in_status == 1 ? 0 : 1;
                if (newStatus === 0 && !confirm('Undo check-in for ' + guest.name + '?')) return;
                const res = await this._api('toggle_checkin', { guest_id: guest.id, status: newStatus });
                if (res.status) {
                    guest.check_in_status = newStatus;
                    guest.check_in_time = newStatus ? new Date().toISOString() : null;
                    this.showToast(guest.name + ': ' + (newStatus ? '✅ Checked in!' : 'Check-in undone'));
                    this.loadStats();
                    this.buildTableView();
                } else {
                    this.showToast(res.message, 'error');
                }
            },

            initScanner() {
                const self = this;
                try {
                    html5QrcodeScanner = new Html5Qrcode("qr-reader");
                    html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 },
                        async (decodedText) => {
                            // Extract token from URL
                            let token = decodedText;
                            if (decodedText.includes('token=')) {
                                token = decodedText.split('token=')[1].split('&')[0];
                            }
                            // Pause scanner during processing
                            html5QrcodeScanner.pause();
                            const res = await self._api('checkin_by_token', { token: token });
                            self.scanResult = res.message;
                            self.scanSuccess = res.status;
                            self.showToast(res.message, res.status ? 'success' : 'error');
                            if (res.status) {
                                self.loadGuests();
                                self.loadStats();
                            }
                            // Resume after 2s
                            setTimeout(() => {
                                try { html5QrcodeScanner.resume(); } catch(e) {}
                                self.scanResult = '';
                            }, 2500);
                        },
                        (err) => { /* ignore scan errors */ }
                    ).catch(err => {
                        console.log('Camera not available:', err);
                        self.scanResult = 'Camera not available. Please use Search mode.';
                        self.scanSuccess = false;
                    });
                } catch(e) {
                    console.log('QR Scanner init error:', e);
                }
            },

            showToast(msg, type = 'success') {
                const id = Date.now();
                this.toasts.push({ id, message: msg, type, show: true });
                setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3000);
            }
        };
    }
    </script>
</body>
</html>
