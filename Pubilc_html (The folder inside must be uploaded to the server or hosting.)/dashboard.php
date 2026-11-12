<?php
// Prevent caching on the server (like LiteSpeed) and the browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

require_once __DIR__ . '/auth.php';
$client = Auth::requireLogin();
$clientId = $client['id'];
$username = htmlspecialchars($client['username']);
$coupleNames = htmlspecialchars($client['couple_names']);

if (isset($_GET['logout'])) {
    Auth::logout();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard â€” <?php echo $coupleNames; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/css/intlTelInput.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo Auth::generateCsrfToken(); ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo $client['color_primary'] ?? '#4A6741'; ?>',
                        secondary: '<?php echo $client['color_secondary'] ?? '#F5F0EB'; ?>'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .glass-card { background: #fff; }
        .iti { width: 100%; display: block; }
        .iti__flag { background-image: url("https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/img/flags.png"); }
        @media (min-resolution: 2dppx) {
            .iti__flag { background-image: url("https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/img/flags@2x.png"); }
        }
        .iti__selected-flag { background-color: #fafafa; border-right: 1px solid #e5e5e5; border-top-left-radius: 8px; border-bottom-left-radius: 8px; padding: 0 12px; }
        .iti__country-list { border-radius: 8px; border: 1px solid #e5e5e5; box-shadow: 0 4px 12px rgb(0 0 0 / 0.06); margin-top: 4px; z-index: 60; }
        .iti-input { padding-left: 65px !important; }
        [data-lucide] { width: 18px; height: 18px; stroke-width: 1.75; }
        .sidebar-icon { width: 18px; height: 18px; stroke-width: 1.75; flex-shrink: 0; }
        .sidebar-item-active { position: relative; }
        .sidebar-item-active::before { content: ''; position: absolute; left: 0; top: 6px; bottom: 6px; width: 3px; border-radius: 0 3px 3px 0; background: var(--tw-color-primary, #4A6741); }
    </style>
</head>
<body class="bg-[#f8f8f7] text-neutral-800 antialiased" x-data="dashboardApp">

    <!-- Sidebar & Content Layout -->
    <div class="flex h-screen overflow-hidden relative">
        
        <!-- Mobile Overlay -->
        <div x-show="mobileMenuOpen" class="fixed inset-0 bg-black/30 z-40 lg:hidden" @click="mobileMenuOpen = false" x-transition.opacity x-cloak></div>

        <!-- Sidebar -->
        <aside class="w-52 bg-white border-r border-neutral-100/80 text-neutral-500 flex flex-col transition-transform duration-300 absolute inset-y-0 left-0 z-50 lg:relative lg:translate-x-0"
               :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="px-5 py-4 border-b border-neutral-100/80">
                <h1 class="text-neutral-900 text-[14px] font-semibold truncate"><?php echo $coupleNames; ?></h1>
                <p class="text-[10px] text-neutral-400 mt-0.5 tracking-wide"><?= htmlspecialchars(!empty($client['app_name']) ? $client['app_name'] : APP_SHORT_NAME) ?></p>
            </div>
            
            <nav class="flex-1 px-2 py-3 space-y-px overflow-y-auto">
                <template x-for="item in menuItems">
                    <button @click="currentTab = item.id; mobileMenuOpen = false" 
                            :class="currentTab === item.id ? 'text-neutral-900 font-semibold sidebar-item-active' : 'text-neutral-500 hover:text-neutral-700'"
                            class="w-full flex items-center gap-2.5 px-3 py-[7px] rounded-md text-[13px] font-medium transition-colors text-left hover:bg-neutral-50/60">
                        <span x-html="lucideIcon(item.lucide, 17)" class="flex-shrink-0"></span>
                        <span x-text="item.label"></span>
                    </button>
                </template>
            </nav>

            <div class="px-2 py-3 border-t border-neutral-100/80">
                <a href="?logout=1" class="flex items-center gap-2.5 px-3 py-[7px] rounded-md hover:bg-red-50 hover:text-red-500 text-[13px] font-medium transition-colors">
                    <i data-lucide="log-out" style="width:17px;height:17px;stroke-width:1.75"></i>
                    <span>Log Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-[#f8f8f7] relative">
            
            <!-- Toast Notifications -->
            <div class="fixed top-4 right-4 z-50 flex flex-col gap-2">
                <template x-for="toast in toasts" :key="toast.id">
                    <div x-show="toast.visible" x-transition.opacity.duration.300ms
                         :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border-red-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100'"
                         class="px-4 py-2.5 rounded-lg border text-sm font-medium flex items-center gap-2 min-w-[250px]">
                         <span x-text="toast.message"></span>
                    </div>
                </template>
            </div>

            <!-- Header -->
            <header class="bg-white/95 backdrop-blur-sm shadow-[0_1px_0_0_rgba(0,0,0,0.04)] sticky top-0 z-30 px-4 lg:px-8 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden text-neutral-400 hover:text-neutral-700 transition">
                        <i data-lucide="menu" style="width:20px;height:20px"></i>
                    </button>
                    <h2 class="text-lg font-semibold text-neutral-800" x-text="menuItems.find(m => m.id === currentTab)?.label"></h2>
                </div>
                
                <div class="flex items-center gap-4">
                    <span class="text-xs font-medium text-neutral-400" x-text="'@' + '<?php echo $username; ?>'"></span>
                </div>
            </header>

            <!-- TABS CONTENT -->
            <div class="p-4 lg:p-8">

                <!-- 1. OVERVIEW -->
                <div x-show="currentTab === 'overview'" x-cloak>
                    <!-- Stat Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-5 mb-6 lg:mb-8">
                        <div class="glass-card p-4 lg:p-5 rounded-xl border border-neutral-100 bg-white border-t-2 border-t-neutral-300">
                            <div class="flex items-center gap-1.5 lg:gap-2 mb-3 lg:mb-4">
                                <i data-lucide="users-round" style="width:14px;height:14px;stroke-width:1.75" class="text-neutral-400 hidden sm:block"></i>
                                <p class="text-[11px] lg:text-[12px] font-medium text-neutral-500">Total Guests</p>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-semibold text-neutral-800 tracking-tight" x-text="stats.total"></h3>
                        </div>
                        <div class="glass-card p-4 lg:p-5 rounded-xl border border-neutral-100 bg-white border-t-2 border-t-emerald-400">
                            <div class="flex items-center gap-1.5 lg:gap-2 mb-3 lg:mb-4">
                                <i data-lucide="check" style="width:14px;height:14px;stroke-width:2" class="text-emerald-500 hidden sm:block"></i>
                                <p class="text-[11px] lg:text-[12px] font-medium text-neutral-500">Attending</p>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-semibold text-neutral-800 tracking-tight" x-text="stats.attending"></h3>
                        </div>
                        <div class="glass-card p-4 lg:p-5 rounded-xl border border-neutral-100 bg-white border-t-2 border-t-rose-400">
                            <div class="flex items-center gap-1.5 lg:gap-2 mb-3 lg:mb-4">
                                <i data-lucide="x" style="width:14px;height:14px;stroke-width:2" class="text-rose-400 hidden sm:block"></i>
                                <p class="text-[11px] lg:text-[12px] font-medium text-neutral-500">Declined</p>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-semibold text-neutral-800 tracking-tight" x-text="stats.declined"></h3>
                        </div>
                        <div class="glass-card p-4 lg:p-5 rounded-xl border border-neutral-100 bg-white border-t-2 border-t-amber-400">
                            <div class="flex items-center gap-1.5 lg:gap-2 mb-3 lg:mb-4">
                                <i data-lucide="clock" style="width:14px;height:14px;stroke-width:1.75" class="text-amber-400 hidden sm:block"></i>
                                <p class="text-[11px] lg:text-[12px] font-medium text-neutral-500">Pending RSVP</p>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-semibold text-neutral-800 tracking-tight" x-text="stats.pending"></h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-8">
                        <!-- Campaign Performance -->
                        <div class="glass-card p-6 rounded-xl border border-neutral-100 lg:col-span-2 bg-white flex flex-col justify-between">
                            <div class="flex items-center gap-2 mb-6 text-neutral-600">
                                <i data-lucide="mail" class="text-neutral-400" style="width:16px;height:16px"></i>
                                <h3 class="text-sm font-medium">Email Performance</h3>
                            </div>
                            <div class="flex flex-col sm:flex-row justify-around items-center gap-6 sm:gap-0 mt-auto mb-2 border-t border-neutral-100 pt-6">
                                <div class="text-center w-full">
                                    <p class="text-4xl font-semibold text-neutral-700" x-text="stats.emails_sent"></p>
                                    <p class="text-[11px] text-neutral-500 mt-3 uppercase tracking-wide font-medium">Sent</p>
                                </div>
                                <div class="hidden sm:block h-12 w-px bg-neutral-200"></div>
                                <div class="text-center w-full">
                                    <p class="text-4xl font-semibold text-green-600" x-text="stats.emails_opened"></p>
                                    <p class="text-[11px] text-neutral-500 mt-3 uppercase tracking-wide font-medium">Opened</p>
                                </div>
                                <div class="hidden sm:block h-12 w-px bg-neutral-200"></div>
                                <div class="text-center w-full">
                                    <p class="text-4xl font-semibold text-primary" x-text="stats.campaigns_total"></p>
                                    <p class="text-[11px] text-neutral-500 mt-3 uppercase tracking-wide font-medium">Campaigns</p>
                                </div>
                            </div>
                        </div>

                        <!-- Meal Preferences Analytics -->
                        <div class="glass-card p-6 rounded-xl border border-neutral-100 bg-white lg:col-span-2">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-2 text-neutral-600">
                                    <i data-lucide="chef-hat" class="text-neutral-400" style="width:16px;height:16px"></i>
                                    <h3 class="text-sm font-medium">Catering Overview</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="exportCateringPDF()" class="text-xs bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-600 px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1.5 shadow-sm" title="Export Catering Report as PDF">
                                        <i data-lucide="file-text" text-red-500"></i> Export PDF
                                    </button>
                                    <div class="text-xs bg-neutral-100 text-neutral-600 px-3 py-1 rounded-full font-medium">
                                        <span x-text="stats.total_actual_attending || stats.attending"></span> Guests Attending
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Meal Counts -->
                                <div>
                                    <h4 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-3">Meal Selections</h4>
                                    <div class="space-y-3">
                                        <template x-for="meal in stats.meals" :key="meal.meal_choice">
                                            <div class="flex justify-between items-center text-sm border-b border-neutral-100 pb-2">
                                                <span class="text-neutral-700 capitalize" x-text="meal.meal_choice || 'Not Specified'"></span>
                                                <span class="font-semibold text-neutral-900 bg-neutral-50 px-2 py-0.5 rounded" x-text="meal.cnt"></span>
                                            </div>
                                        </template>
                                        <template x-if="!stats.meals || stats.meals.length === 0">
                                            <p class="text-sm text-neutral-500 italic">No meal data recorded yet.</p>
                                        </template>
                                    </div>
                                </div>
                                <!-- Dietary Needs -->
                                <div>
                                    <h4 class="text-xs font-semibold text-neutral-500 uppercase tracking-widest mb-3">Dietary Requirements</h4>
                                    <div class="space-y-3 max-h-[150px] overflow-y-auto pr-2 custom-scrollbar">
                                        <template x-for="(diet, index) in stats.dietary_notes" :key="index">
                                            <div class="text-sm border-l-2 border-amber-300 pl-3 py-1 mb-2 bg-amber-50/30 rounded-r-lg">
                                                <strong class="text-neutral-800 text-xs block mb-0.5" x-text="diet.name"></strong>
                                                <span class="text-neutral-600" x-text="diet.dietary_notes"></span>
                                            </div>
                                        </template>
                                        <template x-if="!stats.dietary_notes || stats.dietary_notes.length === 0">
                                            <p class="text-sm text-neutral-500 italic">No dietary restrictions noted.</p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="glass-card p-6 rounded-xl border border-neutral-100 bg-white flex flex-col">
                            <div class="flex items-center gap-2 mb-6 text-neutral-600">
                                <i data-lucide="bell-ring" class="text-neutral-400" style="width:16px;height:16px"></i>
                                <h3 class="text-sm font-medium">Recent RSVPs</h3>
                            </div>
                            <div class="space-y-4">
                                <template x-for="act in stats.recent_activity" :key="act.name">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full" :class="act.rsvp_status === 'attending' ? 'bg-green-500' : 'bg-red-500'"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-neutral-800 truncate" x-text="act.name"></p>
                                            <p class="text-xs text-neutral-500" x-text="act.rsvp_responded_at"></p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!stats.recent_activity || stats.recent_activity.length === 0">
                                    <p class="text-sm text-neutral-500 text-center py-4">No RSVP activity yet.</p>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-8 mt-4 lg:mt-6">
                        <!-- RSVP Distribution Pie Chart -->
                        <div class="glass-card p-6 rounded-xl border border-neutral-100 bg-white">
                            <div class="flex items-center gap-2 mb-4 text-neutral-600">
                                <i data-lucide="pie-chart" class="text-neutral-400" style="width:16px;height:16px"></i>
                                <h3 class="text-sm font-medium">RSVP Distribution</h3>
                            </div>
                            <div style="max-height:220px;" class="flex items-center justify-center">
                                <canvas id="rsvpPieChart"></canvas>
                            </div>
                        </div>

                        <!-- Meal Selections Bar Chart -->
                        <div class="glass-card p-6 rounded-xl border border-neutral-100 bg-white">
                            <div class="flex items-center gap-2 mb-4 text-neutral-600">
                                <i data-lucide="bar-chart-3" class="text-neutral-400" style="width:16px;height:16px"></i>
                                <h3 class="text-sm font-medium">Meal Breakdown</h3>
                            </div>
                            <div style="max-height:220px;" class="flex items-center justify-center">
                                <canvas id="mealBarChart"></canvas>
                            </div>
                        </div>

                        <!-- Check-in Status -->
                        <div class="glass-card p-6 rounded-xl border border-neutral-100 bg-white">
                            <div class="flex items-center gap-2 mb-4 text-neutral-600">
                                <i data-lucide="scan-line" class="text-neutral-400" style="width:16px;height:16px"></i>
                                <h3 class="text-sm font-medium">Check-in Status</h3>
                            </div>
                            <div class="text-center mb-4">
                                <span class="text-4xl font-semibold text-neutral-800" x-text="stats.checked_in || 0"></span>
                                <span class="text-lg text-neutral-400"> / </span>
                                <span class="text-lg text-neutral-500" x-text="stats.attending || 0"></span>
                                <p class="text-xs text-neutral-500 mt-1 uppercase tracking-wide font-medium">Guests Checked In</p>
                            </div>
                            <div class="w-full bg-neutral-100 rounded-full h-3 mb-4">
                                <div class="h-3 rounded-full bg-gradient-to-r from-green-400 to-emerald-500 transition-all duration-500" :style="'width:' + (stats.attending > 0 ? Math.round((stats.checked_in || 0) / stats.attending * 100) : 0) + '%'"></div>
                            </div>
                            <div class="space-y-2 max-h-[110px] overflow-y-auto pr-1 custom-scrollbar">
                                <template x-for="ci in (stats.recent_checkins || [])" :key="ci.name">
                                    <div class="flex items-center gap-2 text-sm">
                                        <i data-lucide="check-circle" text-green-500 text-xs"></i>
                                        <span class="text-neutral-700 truncate flex-1" x-text="ci.name"></span>
                                        <span class="text-xs text-neutral-400" x-text="ci.check_in_time ? ci.check_in_time.substring(11, 16) : ''"></span>
                                    </div>
                                </template>
                                <template x-if="!stats.recent_checkins || stats.recent_checkins.length === 0">
                                    <p class="text-sm text-neutral-500 text-center py-2 italic">No check-ins yet.</p>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. GUESTS -->
                <div x-show="currentTab === 'guests'" x-cloak>
                    <!-- Toolbar -->
                    <div class="space-y-3 mb-4 lg:mb-6">
                        <!-- Filter tabs -->
                        <div class="flex gap-1 bg-white rounded-lg p-1 border border-neutral-200 overflow-x-auto">
                            <button @click="guestFilter = 'all'" :class="{'bg-neutral-100 font-semibold shadow-sm': guestFilter === 'all'}" class="px-2.5 lg:px-3 py-1.5 text-xs lg:text-sm rounded-md text-neutral-600 transition whitespace-nowrap">All</button>
                            <button @click="guestFilter = 'attending'" :class="{'bg-green-50 text-green-700 font-semibold shadow-sm': guestFilter === 'attending'}" class="px-2.5 lg:px-3 py-1.5 text-xs lg:text-sm rounded-md text-neutral-600 transition whitespace-nowrap">Attending</button>
                            <button @click="guestFilter = 'pending'" :class="{'bg-orange-50 text-orange-700 font-semibold shadow-sm': guestFilter === 'pending'}" class="px-2.5 lg:px-3 py-1.5 text-xs lg:text-sm rounded-md text-neutral-600 transition whitespace-nowrap">Pending</button>
                            <button @click="guestFilter = 'declined'" :class="{'bg-red-50 text-red-700 font-semibold shadow-sm': guestFilter === 'declined'}" class="px-2.5 lg:px-3 py-1.5 text-xs lg:text-sm rounded-md text-neutral-600 transition whitespace-nowrap">Declined</button>
                        </div>
                        
                        <!-- Search + actions -->
                        <div class="flex flex-col lg:flex-row gap-2 lg:gap-3">
                            <div class="relative w-full lg:w-64">
                                <i data-lucide="search" class="absolute left-3 top-2.5 text-neutral-400" style="width:15px;height:15px"></i>
                                <input type="text" x-model="guestSearch" placeholder="Search guests..." class="pl-9 pr-4 py-2 text-sm border border-neutral-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none w-full">
                            </div>
                            <div class="flex gap-2 w-full lg:w-auto">
                                <button @click="showAddGuestModal = true" class="bg-primary hover:bg-primary/90 text-white px-3 lg:px-4 py-2 rounded-xl text-xs lg:text-sm font-medium transition shadow-sm flex items-center justify-center gap-1.5 flex-1 lg:flex-none"><i data-lucide="user-plus" style="width:14px;height:14px"></i> <span>Add Guest</span></button>
                                <button @click="showImportModal = true" class="bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-700 px-3 lg:px-4 py-2 rounded-xl text-xs lg:text-sm font-medium transition flex items-center justify-center gap-1.5 flex-1 lg:flex-none"><i data-lucide="upload" style="width:14px;height:14px"></i> <span>Import</span></button>
                                <a href="api.php?action=export_guests" class="bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-700 px-3 lg:px-4 py-2 rounded-xl text-xs lg:text-sm font-medium transition flex items-center justify-center gap-1.5 flex-1 lg:flex-none" download><i data-lucide="download" style="width:14px;height:14px"></i> <span>Export</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="glass-card rounded-xl border border-neutral-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-neutral-600">
                                <thead class="bg-neutral-50 text-neutral-500 uppercase text-[10px] lg:text-xs font-semibold">
                                    <tr>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4">Name & Email</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4 hidden md:table-cell">Group</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4">Status</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4 hidden lg:table-cell">Table</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4 hidden lg:table-cell">Seats</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4 text-center hidden md:table-cell">Check-in</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4 hidden xl:table-cell">Email</th>
                                        <th class="px-3 lg:px-6 py-3 lg:py-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-for="guest in filteredGuests" :key="guest.id">
                                        <tr class="hover:bg-neutral-50/50 transition">
                                            <td class="px-3 lg:px-6 py-3 lg:py-4">
                                                <div class="font-medium text-neutral-800 text-sm" x-text="guest.name"></div>
                                                <div class="text-[11px] text-neutral-500 mt-0.5 flex items-center gap-1 font-medium">
                                                    <i data-lucide="mail" class="text-neutral-400" style="width:11px;height:11px"></i>
                                                    <span x-text="guest.email || 'No email'" class="truncate max-w-[120px] lg:max-w-none"></span>
                                                </div>
                                                <div x-show="guest.phone" class="text-[11px] text-neutral-500 mt-0.5 flex items-center gap-1 font-medium">
                                                    <i data-lucide="smartphone" class="text-neutral-400" style="width:11px;height:11px"></i>
                                                    <span x-text="guest.phone"></span>
                                                </div>
                                            </td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4 hidden md:table-cell text-sm" x-text="guest.group_label || '-'"></td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4">
                                                <span x-show="guest.rsvp_status === 'attending'" class="bg-green-100 text-green-700 px-2 py-0.5 lg:px-2.5 lg:py-1 rounded-full text-[10px] lg:text-xs font-medium">Attending</span>
                                                <span x-show="guest.rsvp_status === 'declined'" class="bg-red-100 text-red-700 px-2 py-0.5 lg:px-2.5 lg:py-1 rounded-full text-[10px] lg:text-xs font-medium">Declined</span>
                                                <span x-show="guest.rsvp_status === 'pending'" class="bg-orange-100 text-orange-700 px-2 py-0.5 lg:px-2.5 lg:py-1 rounded-full text-[10px] lg:text-xs font-medium">Pending</span>
                                            </td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4 hidden lg:table-cell">
                                                <div x-show="guest.table_number" class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs font-semibold"><i data-lucide="armchair" style="width:10px;height:10px"></i> <span x-text="guest.table_number"></span></div>
                                                <span x-show="!guest.table_number" class="text-xs text-neutral-400">-</span>
                                            </td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4 hidden lg:table-cell text-sm" x-text="guest.seats_allocated"></td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4 text-center hidden md:table-cell">
                                                <button @click="toggleCheckIn(guest)" class="transition hover:scale-110" :title="guest.check_in_status == 1 ? 'Checked in at ' + guest.check_in_time : 'Click to check-in manually'">
                                                    <template x-if="guest.check_in_status == 1">
                                                        <span class="inline-flex items-center gap-1 bg-green-50 text-green-600 px-2 py-1 rounded-full text-xs font-semibold"><i data-lucide="check-circle" style="width:12px;height:12px"></i> In</span>
                                                    </template>
                                                    <template x-if="guest.check_in_status != 1">
                                                        <span class="inline-flex items-center gap-1 bg-neutral-100 text-neutral-400 px-2 py-1 rounded-full text-xs font-medium"><i data-lucide="circle" style="width:12px;height:12px"></i> -</span>
                                                    </template>
                                                </button>
                                            </td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4 text-xs text-neutral-500 hidden xl:table-cell">
                                                <div x-show="guest.latest_email_status" class="space-y-1">
                                                    <span x-show="guest.latest_email_status === 'sent'" class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded border border-blue-100 font-medium inline-flex items-center gap-1">
                                                        <i data-lucide="send" style="width:12px;height:12px;stroke-width:1.75"></i> Sent
                                                    </span>
                                                    <div x-show="guest.latest_email_status === 'sent' && guest.email_sent_at" class="text-[10px] text-neutral-400 ml-0.5" x-text="guest.email_sent_at"></div>
                                                    
                                                    <span x-show="guest.latest_email_status === 'opened'" class="bg-green-50 text-green-600 px-2 py-0.5 rounded border border-green-100 font-medium inline-flex items-center gap-1">
                                                        <i data-lucide="mail-open" style="width:12px;height:12px;stroke-width:1.75"></i> Opened
                                                    </span>
                                                    <div x-show="guest.latest_email_status === 'opened' && guest.email_opened_at" class="text-[10px] text-green-500 ml-0.5 font-medium" x-text="guest.email_opened_at"></div>
                                                    
                                                    <span x-show="guest.latest_email_status === 'clicked'" class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded border border-indigo-100 font-medium inline-flex items-center gap-1">
                                                        <i data-lucide="mouse-pointer-click" style="width:12px;height:12px;stroke-width:1.75"></i> Clicked
                                                    </span>
                                                    <div x-show="guest.latest_email_status === 'clicked' && guest.email_clicked_at" class="text-[10px] text-indigo-500 ml-0.5 font-medium" x-text="guest.email_clicked_at"></div>
                                                </div>
                                                <span x-show="!guest.latest_email_status" class="text-neutral-400 italic">No email sent</span>
                                            </td>
                                            <td class="px-3 lg:px-6 py-3 lg:py-4 text-right">
                                                <div class="flex justify-end gap-1">
                                                    <button @click="copyRsvp(guest.token)" class="p-1.5 text-neutral-400 hover:text-primary transition" title="Copy RSVP Link"><i data-lucide="link" style="width:14px;height:14px"></i></button>
                                                    <button @click="deleteGuest(guest.id)" class="p-1.5 text-neutral-400 hover:text-red-500 transition" title="Delete"><i data-lucide="trash-2" style="width:14px;height:14px"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredGuests.length === 0">
                                        <td colspan="8" class="px-6 py-12 text-center text-neutral-500">No guests found. Start adding some!</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2.5. RSVP RESPONSES -->
                <div x-show="currentTab === 'rsvp_responses'" x-cloak>
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <h3 class="text-base lg:text-lg font-semibold flex items-center gap-2"><i data-lucide="inbox" class="text-primary" style="width:18px;height:18px"></i> RSVP Responses</h3>
                        <div class="flex flex-wrap gap-2 w-full lg:w-auto">
                            <button @click="exportRsvpPDF()" class="flex-1 lg:flex-none bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-700 px-3 lg:px-4 py-2 rounded-xl text-xs lg:text-sm font-medium transition shadow-sm flex items-center justify-center gap-1.5">
                                <i data-lucide="file-text" class="text-red-500" style="width:14px;height:14px"></i> Export PDF
                            </button>
                            <a href="api.php?action=export_guests" class="flex-1 lg:flex-none bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-700 px-3 lg:px-4 py-2 rounded-xl text-xs lg:text-sm font-medium transition shadow-sm flex items-center justify-center gap-1.5" download>
                                <i data-lucide="download" style="width:14px;height:14px"></i> Export CSV
                            </a>
                        </div>
                    </div>

                    <div class="glass-card rounded-xl border border-neutral-100 shadow-sm overflow-hidden bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-neutral-600">
                                <thead class="bg-neutral-50 text-neutral-500 uppercase text-xs font-semibold">
                                    <tr>
                                        <th class="px-6 py-4">Main Guest & Accompanying</th>
                                        <th class="px-6 py-4">RSVP Status</th>
                                        <th class="px-6 py-4">Table</th>
                                        <th class="px-6 py-4">Guest Questions / Details</th>
                                        <th class="px-6 py-4">Contact Info</th>
                                        <th class="px-6 py-4 text-right">Responded At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-for="guest in respondedGuests" :key="guest.id">
                                        <tr class="hover:bg-neutral-50/50 transition">
                                            <!-- Main Guest & Accompanying -->
                                            <td class="px-6 py-4 align-top">
                                                <div class="font-medium text-neutral-800 text-base" x-text="guest.name"></div>
                                                <div x-show="guest.group_label" class="text-[11px] uppercase tracking-wider text-neutral-400 mt-0.5" x-text="'Group: ' + guest.group_label"></div>
                                                
                                                <template x-if="guest.accompanying_guests && guest.accompanying_guests !== '[]'">
                                                    <div class="mt-3 pl-3 border-l-2 border-neutral-200 space-y-2">
                                                        <template x-for="(acc, i) in (typeof guest.accompanying_guests === 'string' ? JSON.parse(guest.accompanying_guests) : guest.accompanying_guests)" :key="i">
                                                            <div>
                                                                <div class="text-sm font-medium text-neutral-700 flex items-center gap-1.5">
                                                                    <i data-lucide="user" class="text-neutral-400" style="width:10px;height:10px"></i>
                                                                    <span x-text="acc.name"></span>
                                                                </div>
                                                                <div class="text-xs text-neutral-500 ml-4 flex flex-wrap gap-2 mt-0.5">
                                                                    <span x-show="acc.meal" class="flex items-center gap-1"><i data-lucide="utensils" style="width:10px;height:10px"></i> <span x-text="acc.meal"></span></span>
                                                                    <span x-show="acc.dietary_notes" class="text-red-500 flex items-center gap-1"><i data-lucide="alert-triangle" style="width:10px;height:10px"></i> <span x-text="acc.dietary_notes"></span></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </td>
                                            
                                            <!-- Status -->
                                            <td class="px-6 py-4 align-top">
                                                <span x-show="guest.rsvp_status === 'attending'" class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold inline-block mb-2">Attending</span>
                                                <span x-show="guest.rsvp_status === 'declined'" class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold inline-block mb-2">Declined</span>
                                                <div class="text-xs text-neutral-500 whitespace-nowrap"><span class="font-medium text-neutral-700" x-text="guest.seats_allocated"></span> Seats Allocated</div>
                                            </td>

                                            <!-- Table -->
                                            <td class="px-6 py-4 align-top">
                                                <div x-show="guest.table_number" class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs font-semibold"><i data-lucide="armchair" style="width:10px;height:10px"></i> <span x-text="guest.table_number"></span></div>
                                                <span x-show="!guest.table_number" class="text-xs text-neutral-400">-</span>
                                            </td>
                                            
                                            <!-- Details / Questions -->
                                            <td class="px-6 py-4 align-top min-w-[250px]">
                                                <div x-show="guest.meal_choice" class="mb-2">
                                                    <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-widest block">Main Meal</span>
                                                    <span class="text-sm font-medium text-neutral-800" x-text="guest.meal_choice"></span>
                                                </div>
                                                <div x-show="guest.dietary_notes" class="mb-2">
                                                    <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest block">Dietary Options</span>
                                                    <span class="text-sm text-red-700" x-text="guest.dietary_notes"></span>
                                                </div>
                                                <div x-show="guest.events_attending && guest.events_attending !== '[]'" class="mb-2">
                                                    <span class="text-[10px] font-bold text-blue-400 uppercase tracking-widest block">Events Attending</span>
                                                    <ul class="list-disc pl-4 text-sm text-blue-700 space-y-0.5">
                                                        <template x-for="evt in (typeof guest.events_attending === 'string' ? JSON.parse(guest.events_attending) : guest.events_attending)">
                                                            <li x-text="evt"></li>
                                                        </template>
                                                    </ul>
                                                </div>
                                                <div x-show="guest.accommodation" class="mb-2">
                                                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest block">Accommodation</span>
                                                    <span class="text-sm text-indigo-700" x-text="guest.accommodation"></span>
                                                </div>
                                                <div x-show="guest.song_request" class="mb-2">
                                                    <span class="text-[10px] font-bold text-purple-400 uppercase tracking-widest block">Song Request</span>
                                                    <span class="text-sm text-purple-700" x-text="guest.song_request"></span>
                                                </div>
                                                <div x-show="guest.notes" class="mb-2">
                                                    <span class="text-[10px] font-bold text-amber-400 uppercase tracking-widest block">Event Notes</span>
                                                    <span class="text-sm text-amber-700 italic" x-text="'&quot;' + guest.notes + '&quot;'"></span>
                                                </div>
                                                <div x-show="!guest.meal_choice && !guest.dietary_notes && !guest.song_request && !guest.notes && !guest.accommodation && (!guest.events_attending || guest.events_attending === '[]')" class="text-sm text-neutral-400 italic">No extra details provided.</div>
                                            </td>
                                            
                                            <!-- Contact -->
                                            <td class="px-6 py-4 align-top">
                                                <div class="text-sm text-neutral-600 mb-1" x-show="guest.email"><i data-lucide="mail" text-neutral-400 w-4 text-center mr-1"></i> <span x-text="guest.email"></span></div>
                                                <div class="text-sm text-neutral-600 mb-1" x-show="guest.phone"><i data-lucide="smartphone" text-neutral-400 w-4 text-center mr-1"></i> <span x-text="guest.phone"></span></div>
                                            </td>

                                            <td class="px-6 py-4 text-right align-top">
                                                <div class="text-xs text-neutral-500 whitespace-nowrap" x-text="guest.rsvp_responded_at || '-'"></div>
                                                <button @click="currentTab = 'guests'; guestSearch = guest.name" class="mt-3 text-xs text-primary font-medium hover:underline">Edit Guest</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="respondedGuests.length === 0">
                                        <td colspan="5" class="px-6 py-12 text-center text-neutral-500">
                                            <i data-lucide="clock" text-4xl mb-3 text-neutral-300 block"></i>
                                            Waiting for RSVPs to arrive...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. CAMPAIGNS -->
                <div x-show="currentTab === 'campaigns'" x-cloak>
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <h3 class="text-lg font-semibold flex items-center gap-2"><i data-lucide="send" text-primary"></i> Your Campaigns</h3>
                        <button @click="showCreateCampaignModal = true" class="w-full lg:w-auto bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-xl text-sm font-medium transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="plus""></i> Create Campaign
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <template x-for="camp in campaigns" :key="camp.id">
                            <div class="glass-card p-4 lg:p-6 rounded-xl border border-neutral-100 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4 group">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2 lg:gap-3 mb-1">
                                        <h4 class="font-medium text-neutral-800 text-base" x-text="camp.subject"></h4>
                                        <span x-show="camp.status === 'draft'" class="bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded text-[10px] lg:text-xs font-semibold uppercase tracking-wider">Draft</span>
                                        <span x-show="camp.status === 'scheduled'" class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-[10px] lg:text-xs font-semibold uppercase tracking-wider">Scheduled</span>
                                        <span x-show="camp.status === 'sending'" class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded text-[10px] lg:text-xs font-semibold uppercase tracking-wider">Sending...</span>
                                        <span x-show="camp.status === 'sent'" class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] lg:text-xs font-semibold uppercase tracking-wider">Sent</span>
                                    </div>
                                    <p class="text-sm text-neutral-500">
                                        Type: <span x-text="camp.campaign_type.replace(/_/g, ' ')"></span> 
                                        | Audience: <span class="capitalize font-medium text-neutral-700" x-text="(camp.audience_filter || 'all').replace(/_/g, ' ')"></span> 
                                        | Created: <span x-text="camp.created_at"></span>
                                    </p>
                                    
                                    <div x-show="camp.status === 'sent' || camp.status === 'sending'" class="flex gap-6 mt-3 text-sm">
                                        <span class="text-neutral-600"><strong class="font-medium text-neutral-800" x-text="camp.total_sent"></strong> sent</span>
                                        <span class="text-neutral-600"><strong class="font-medium text-primary" x-text="camp.total_opened"></strong> opened</span>
                                    </div>

                                    <div x-show="camp.last_error" class="mt-3 bg-red-50 border border-red-100 rounded-lg p-3 text-xs text-red-600">
                                        <strong class="font-semibold block mb-1">Last Error:</strong>
                                        <span x-text="camp.last_error"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button x-show="camp.status === 'draft'" @click="sendCampaign(camp.id)" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-sm font-medium transition"><i data-lucide="send" mr-2"></i> Send Now</button>
                                    <button @click="deleteCampaign(camp.id)" class="p-2 text-neutral-400 hover:text-red-500 transition" title="Delete Campaign"><i data-lucide="trash-2""></i></button>
                                </div>
                            </div>
                        </template>
                        <div x-show="campaigns.length === 0" class="text-center py-12 text-neutral-500 bg-white rounded-xl border border-dashed border-neutral-300">
                            <i data-lucide="mail" text-4xl mb-4 text-neutral-300"></i>
                            <p>No campaigns yet. Connect with your guests!</p>
                        </div>
                    </div>
                </div>

                <!-- 4. TEMPLATES -->
                <div x-show="currentTab === 'templates'" x-cloak>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:h-[calc(100vh-140px)]">
                        <!-- Template List -->
                        <div class="flex flex-col gap-4 overflow-y-auto pr-2 pb-4 lg:pb-0">
                            <template x-for="tmpl in templatesList" :key="tmpl.id">
                                <button @click="selectTemplate(tmpl.id)" 
                                        :class="{'border-primary bg-primary/5': previewTemplateId === tmpl.id, 'border-neutral-200 bg-white hover:border-primary/50': previewTemplateId !== tmpl.id}"
                                        class="p-4 lg:p-5 rounded-xl border text-left transition-all relative overflow-hidden text-neutral-800 w-full shadow-sm">
                                    <div class="flex items-start gap-3 lg:gap-4">
                                        <div class="text-xl lg:text-2xl text-neutral-400 mt-0.5"><i :class="tmpl.icon"></i></div>
                                        <div>
                                            <h4 class="font-semibold text-sm lg:text-base mb-1" x-text="tmpl.name"></h4>
                                            <p class="text-xs lg:text-sm text-neutral-500" x-text="tmpl.desc"></p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                        
                        <!-- Right Panel: Wedding Template (Edit/Preview Toggle) -->
                        <div class="glass-card rounded-xl border border-neutral-100 shadow-sm flex flex-col overflow-hidden bg-neutral-50 min-h-[600px] lg:min-h-0">
                            <!-- Toggle Header -->
                            <div class="p-4 bg-white border-b border-neutral-200 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <div class="flex bg-neutral-100 rounded-lg p-0.5">
                                        <button @click="templateMode = 'edit'" 
                                                :class="templateMode === 'edit' ? 'bg-white shadow-sm text-neutral-800' : 'text-neutral-500 hover:text-neutral-700'"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition">
                                            <i data-lucide="pencil" style="width:13px;height:13px;stroke-width:2"></i> Edit
                                        </button>
                                        <button @click="templateMode = 'preview'; loadPreview()" 
                                                :class="templateMode === 'preview' ? 'bg-white shadow-sm text-neutral-800' : 'text-neutral-500 hover:text-neutral-700'"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition">
                                            <i data-lucide="eye" style="width:13px;height:13px;stroke-width:2"></i> Preview
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span x-show="templateMode === 'edit'" class="text-xs bg-amber-50 text-amber-600 px-2 py-1 rounded font-medium" x-cloak>Editing</span>
                                    <span x-show="templateMode === 'preview'" class="text-xs bg-neutral-100 text-neutral-500 px-2 py-1 rounded" x-cloak>Desktop &amp; Mobile</span>
                                </div>
                            </div>

                            <!-- EDIT MODE -->
                            <div x-show="templateMode === 'edit'" class="flex-1 overflow-y-auto p-5 space-y-4" x-cloak>
                                <template x-if="templateTextsLoaded && editingTexts[previewTemplateId]">
                                    <div class="space-y-3">
                                        <template x-for="key in getTemplateTextKeys(previewTemplateId)" :key="key">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-neutral-500 uppercase tracking-wider mb-1.5" x-text="formatFieldLabel(key)"></label>
                                                <template x-if="isLongField(key)">
                                                    <textarea x-model="editingTexts[previewTemplateId][key]" rows="4"
                                                        class="w-full border border-neutral-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none resize-none leading-relaxed"
                                                        :placeholder="defaultTexts[previewTemplateId] ? defaultTexts[previewTemplateId][key] : ''"></textarea>
                                                </template>
                                                <template x-if="!isLongField(key)">
                                                    <input type="text" x-model="editingTexts[previewTemplateId][key]"
                                                        class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none"
                                                        :placeholder="defaultTexts[previewTemplateId] ? defaultTexts[previewTemplateId][key] : ''">
                                                </template>
                                                <p x-show="defaultTexts[previewTemplateId] && editingTexts[previewTemplateId][key] !== defaultTexts[previewTemplateId][key]"
                                                   class="text-[10px] text-neutral-400 mt-1 truncate" 
                                                   x-text="'Default: ' + (defaultTexts[previewTemplateId] ? defaultTexts[previewTemplateId][key] : '')"></p>
                                            </div>
                                        </template>

                                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 mt-4">
                                            <p class="text-xs text-blue-600"><strong>Merge Tags:</strong> Use <code class="bg-blue-100 px-1 rounded">{{guest_name}}</code>, <code class="bg-blue-100 px-1 rounded">{{event_names}}</code>, <code class="bg-blue-100 px-1 rounded">{{event_date}}</code>, <code class="bg-blue-100 px-1 rounded">{{venue_name}}</code>, etc.</p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!templateTextsLoaded">
                                    <div class="flex items-center justify-center py-12 text-neutral-400">
                                        <i data-lucide="loader" class="animate-spin mr-2" style="width:16px;height:16px"></i> Loading...
                                    </div>
                                </template>
                            </div>

                            <!-- Action Buttons (Edit Mode) -->
                            <div x-show="templateMode === 'edit'" class="p-4 bg-white border-t border-neutral-200 space-y-3" x-cloak>
                                <div class="flex gap-2">
                                    <button @click="saveTemplateTexts()" :disabled="isSavingTexts"
                                            class="flex-1 bg-primary hover:bg-primary/90 disabled:opacity-50 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition shadow-sm flex items-center justify-center gap-2">
                                        <i data-lucide="save" style="width:14px;height:14px"></i>
                                        <span x-text="isSavingTexts ? 'Saving...' : 'Save Changes'"></span>
                                    </button>
                                    <button @click="resetTemplateTexts()" :disabled="isSavingTexts"
                                            class="bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-600 px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
                                        <i data-lucide="rotate-ccw" style="width:14px;height:14px"></i> Reset
                                    </button>
                                </div>
                                <p class="text-xs text-neutral-500 text-center">Save changes, then click "Preview" to see the result.</p>
                            </div>

                            <!-- PREVIEW MODE -->
                            <div x-show="templateMode === 'preview'" class="flex-1 p-0 relative h-[calc(100%-130px)]" x-cloak>
                                <div x-show="isLoadingPreview" class="absolute inset-0 bg-white/80 flex items-center justify-center z-10"><i data-lucide="loader" class="animate-spin text-primary" style="width:24px;height:24px"></i></div>
                                <iframe :src="'api.php?action=preview_template&type=' + previewTemplateId" class="w-full h-full border-0 bg-white" id="previewFrame"></iframe>
                            </div>
                            
                            <!-- Test Email Section (Preview Mode) -->
                            <div x-show="templateMode === 'preview'" class="p-4 bg-white border-t border-neutral-200 mt-auto" x-cloak>
                                <h4 class="text-sm font-semibold text-neutral-700 mb-2">Send Test Email</h4>
                                <div class="flex gap-2">
                                    <input type="email" x-model="testEmailAddress" placeholder="Enter test email (e.g., your@email.com)" @keydown.enter="sendTestEmail"
                                           class="flex-1 border border-neutral-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 outline-none w-full shadow-inner"
                                           :disabled="isSendingTest">
                                    <button @click="sendTestEmail" :disabled="isSendingTest"
                                            class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium transition shadow-sm whitespace-nowrap flex items-center gap-2">
                                        <template x-if="isSendingTest">
                                            <i data-lucide="loader" class="animate-spin" style="width:14px;height:14px"></i>
                                        </template>
                                        <template x-if="!isSendingTest">
                                            <i data-lucide="send" style="width:14px;height:14px"></i>
                                        </template>
                                        <span x-text="isSendingTest ? 'Sending...' : 'Send Test'"></span>
                                    </button>
                                </div>
                                <p class="text-xs text-neutral-500 mt-2">Before sending this to all guests, you can test how the email looks in your own inbox.</p>
                            </div>
                        </div>


                    </div>
                </div>


                <!-- 5. SETTINGS -->
                <div x-show="currentTab === 'settings'" x-cloak>
                    <div class="max-w-3xl mx-auto space-y-6">

                        <!-- Client Info (Integration) -->
                        <div class="bg-indigo-50 border border-indigo-200 p-6 rounded-xl shadow-sm flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-indigo-900 mb-1">Mail Marketing Client ID</h3>
                                <p class="text-xs text-indigo-700">This is your unique Client ID. Copy and use this in the <strong>Wedding RSVP Embed</strong> WordPress plugin to connect this event.</p>
                            </div>
                            <div class="bg-white border border-indigo-100 px-5 py-2.5 rounded-lg font-mono font-bold text-indigo-700 shadow-sm text-xl select-all">
                                <?php echo htmlspecialchars($clientId); ?>
                            </div>
                        </div>
                        
                        <!-- Main Settings -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <h3 class="text-lg font-semibold mb-6">Event Details</h3>
                            <form @submit.prevent="saveSettings" class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Event Name</label>
                                        <input type="text" x-model="settings.couple_names" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Contact Email</label>
                                        <input type="email" x-model="settings.email" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1"><i data-lucide="globe" mr-1"></i> RSVP Language</label>
                                        <select x-model="settings.language" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                            <option value="en">English</option>
                                            <option value="id">Bahasa Indonesia</option>
                                        </select>
                                        <p class="text-xs text-neutral-500 mt-1">This sets the language used on the RSVP page for guests.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Event Date</label>
                                        <input type="date" x-model="settings.event_date" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Event Time</label>
                                        <input type="time" x-model="settings.event_time" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Venue Name</label>
                                        <input type="text" x-model="settings.venue_name" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Venue Address</label>
                                        <input type="text" x-model="settings.venue_address" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save Changes</button>
                                </div>
                            </form>
                        </div>

                        <!-- Brand & RSVP settings (Simplified for snippet length) -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <h3 class="text-lg font-semibold mb-6">Brand & Styling</h3>
                            <form @submit.prevent="saveSettings" class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Primary Color (Hex)</label>
                                        <div class="flex gap-2">
                                            <input type="color" x-model="settings.color_primary" class="w-12 h-10 p-1 border border-neutral-200 rounded-lg cursor-pointer">
                                            <input type="text" x-model="settings.color_primary" class="flex-1 border border-neutral-200 rounded-lg px-4 py-2 outline-none uppercase font-mono text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Hero Image URL (Header)</label>
                                        <input type="text" x-model="settings.hero_image_url" placeholder="https://..." class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none w-full">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Event Website URL</label>
                                        <input type="text" x-model="settings.website_url" placeholder="https://..." class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none w-full">
                                    </div>
                                    <div class="md:col-span-2 mt-2 p-4 bg-neutral-50 border border-neutral-200 rounded-lg">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="checkbox" x-model="settings.site_password_enabled" x-bind:checked="settings.site_password_enabled == 1" class="w-5 h-5 mt-0.5 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <div>
                                                <span class="text-sm font-semibold text-neutral-800">Proteksi Password Website (Login dengan Email)</span>
                                                <p class="text-xs text-neutral-500 mt-1">Jika aktif, tamu harus memasukkan email berdaftar untuk melihat website. Jika nonaktif, website terbuka untuk umum, namun tombol dan link RSVP akan disembunyikan otomatis.</p>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">WordPress RSVP URL (Opsional)</label>
                                        <input type="text" x-model="settings.embed_rsvp_url" placeholder="https://domain-anda.com/rsvp/" class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none w-full">
                                        <p class="text-[11px] text-neutral-500 mt-1 block">Isi jika Anda menggunakan plugin WordPress "Wedding RSVP Embed". Link ke tamu akan menggunakan URL ini (ex: domain-anda.com/rsvp/?token=xxx)</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Timezone / Wilayah Waktu</label>
                                        <select x-model="settings.timezone" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                            <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                                            <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                                            <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                                            <option value="Asia/Kuala_Lumpur">Malaysia Time (Asia/Kuala_Lumpur)</option>
                                            <option value="Asia/Singapore">Singapore Time (Asia/Singapore)</option>
                                            <option value="Australia/Sydney">Sydney Time (Australia/Sydney)</option>
                                            <option value="Europe/London">UK Time (Europe/London)</option>
                                            <option value="America/New_York">US Eastern Time (America/New_York)</option>
                                            <option value="UTC">UTC (Universal Time)</option>
                                        </select>
                                        <p class="text-xs text-neutral-500 mt-1 block">Waktu ini digunakan untuk data jadwal Campaign dan laporan kapan email dibuka/dikirim.</p>
                                    </div>
                                    <div class="md:col-span-2 pt-4 border-t border-neutral-100 mt-2">
                                        <h4 class="text-sm font-semibold text-neutral-800 mb-4">Email Typography</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            <div>
                                                <label class="block text-sm font-medium text-neutral-700 mb-1">Title Font</label>
                                                <select x-model="settings.email_title_font" class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none">
                                                    <option value="Playfair Display">Playfair Display (Elegant Serif)</option>
                                                    <option value="Cinzel">Cinzel (Classic Serif)</option>
                                                    <option value="Lora">Lora (Soft Serif)</option>
                                                    <option value="Cormorant Garamond">Cormorant Garamond (Classic Serif)</option>
                                                    <option value="Montserrat">Montserrat (Modern Sans)</option>
                                                    <option value="Inter">Inter (Clean Sans)</option>
                                                </select>
                                                <p class="text-xs text-neutral-500 mt-1 block">Used for couple names and main headings.</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-neutral-700 mb-1">Body Font</label>
                                                <select x-model="settings.email_body_font" class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none">
                                                    <option value="Inter">Inter (Clean Sans)</option>
                                                    <option value="Roboto">Roboto (Classic Sans)</option>
                                                    <option value="Lato">Lato (Warm Sans)</option>
                                                    <option value="Poppins">Poppins (Geometric Sans)</option>
                                                    <option value="Lora">Lora (Soft Serif)</option>
                                                </select>
                                                <p class="text-xs text-neutral-500 mt-1 block">Used for paragraphs and general text.</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-neutral-700 mb-1">Title Text Transform</label>
                                                <select x-model="settings.email_title_transform" class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none">
                                                    <option value="none">Normal (As typed)</option>
                                                    <option value="uppercase">UPPERCASE</option>
                                                    <option value="lowercase">lowercase</option>
                                                    <option value="capitalize">Capitalize (First Letters)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-neutral-700 mb-1">Title Font Size</label>
                                                <select x-model="settings.email_title_size" class="w-full border border-neutral-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary/20 outline-none">
                                                    <option value="24px">Small (24px)</option>
                                                    <option value="28px">Normal (28px)</option>
                                                    <option value="32px">Large (32px)</option>
                                                    <option value="36px">Extra Large (36px)</option>
                                                    <option value="42px">Huge (42px)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save Brand Settings</button>
                                </div>
                            </form>
                        </div>

                        <!-- White-label & System Branding -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <h3 class="text-lg font-semibold mb-6">White-label & System Branding</h3>
                            <form @submit.prevent="saveSettings" class="space-y-5">
                                <div class="grid grid-cols-1 gap-5">
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">System Name (App Name)</label>
                                        <input type="text" x-model="settings.app_name" placeholder="<?= htmlspecialchars(APP_NAME) ?>" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        <p class="text-[11px] text-neutral-500 mt-1 block">Displays on page titles, generated PDFs, and calendar PRODID. Leave blank to use default.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Email Footer Text</label>
                                        <input type="text" x-model="settings.email_footer_text" placeholder="<?= htmlspecialchars(EMAIL_FOOTER_TEXT) ?>" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        <p class="text-[11px] text-neutral-500 mt-1 block">Brand text that appears at the bottom of standard emails and RSVP page. Leave blank to use default.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">Email Footer Link</label>
                                        <input type="url" x-model="settings.email_footer_link" placeholder="<?= htmlspecialchars(EMAIL_FOOTER_LINK) ?>" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        <p class="text-[11px] text-neutral-500 mt-1 block">The URL that the footer text will link to. Leave blank to use default.</p>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save White-label</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Digital Envelope / Wishing Well -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <h3 class="text-lg font-semibold mb-6">Digital Envelope (Wishing Well)</h3>
                            <form @submit.prevent="saveSettings" class="space-y-5">
                                <div>
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" x-model="settings.wishing_well_enable" x-bind:checked="settings.wishing_well_enable == 1" class="w-5 h-5 text-primary rounded border-neutral-300 focus:ring-primary">
                                        <span class="text-sm font-semibold text-neutral-800">Enable Digital Envelope</span>
                                    </label>
                                    <p class="text-xs text-neutral-500 mt-1 ml-8">If enabled, your bank details or QRIS info will appear in the Week-of Details and Thank You emails.</p>
                                </div>
                                <div x-show="settings.wishing_well_enable == 1" x-collapse x-cloak>
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-neutral-700 mb-2">Account Details</label>
                                        <textarea x-model="settings.wishing_well_details" rows="4" placeholder="e.g. Bank BCA: 1234567890 (a.n. James)&#10;Or scan the QRIS attached to the email..." class="w-full border border-neutral-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/20 outline-none text-sm"></textarea>
                                        <p class="text-xs text-neutral-500 mt-2 block">If you have a QRIS image, please upload it to your media library and include the link here, or just write the bank details.</p>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save Wishing Well</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 mt-6">
                            <h3 class="text-lg font-semibold mb-2 flex items-center gap-2"><i data-lucide="badge" class="text-primary" style="width:18px;height:18px"></i> Receptionist Accounts</h3>
                            <p class="text-sm text-neutral-500 mb-6">Create accounts for your reception staff. They can use a simple PIN to login and check-in guests via QR scanner.</p>
                            
                            <!-- Create Form -->
                            <form @submit.prevent="createReceptionist()" class="mb-6 bg-neutral-50 rounded-xl p-5 border border-neutral-100">
                                <h4 class="text-sm font-semibold text-neutral-700 mb-4 flex items-center gap-1.5"><i data-lucide="plus" style="width:14px;height:14px"></i> Add Receptionist</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                    <div>
                                        <label class="block text-xs font-medium text-neutral-600 mb-1">Name</label>
                                        <input type="text" x-model="newReceptionist.name" placeholder="e.g. Sarah" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-neutral-600 mb-1">Username</label>
                                        <input type="text" x-model="newReceptionist.username" placeholder="e.g. sarah_rec" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-neutral-600 mb-1">PIN (4-6 digits)</label>
                                        <input type="text" x-model="newReceptionist.pin" placeholder="1234" maxlength="6" pattern="[0-9]{4,6}" inputmode="numeric" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none font-mono">
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap flex items-center gap-1.5"><i data-lucide="plus" style="width:14px;height:14px"></i> Add</button>
                                </div>
                            </form>

                            <!-- List -->
                            <div class="space-y-2">
                                <template x-for="rec in receptionists" :key="rec.id">
                                    <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-neutral-100 hover:border-teal-200 transition">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 font-bold text-sm" x-text="rec.name.charAt(0).toUpperCase()"></div>
                                            <div>
                                                <div class="text-sm font-semibold text-neutral-800" x-text="rec.name"></div>
                                                <div class="text-xs text-neutral-500">@<span x-text="rec.username"></span> Â· Created <span x-text="new Date(rec.created_at).toLocaleDateString()"></span></div>
                                            </div>
                                        </div>
                                        <button @click="deleteReceptionist(rec)" class="p-2 text-neutral-400 hover:text-red-500 transition" title="Delete"><i data-lucide="trash-2""></i></button>
                                    </div>
                                </template>
                                <div x-show="receptionists.length === 0" class="text-center py-6 text-neutral-400 text-sm">
                                    <i data-lucide="badge" text-2xl mb-2 block"></i>
                                    No receptionists yet. Add one above.
                                </div>
                            </div>
                        </div>

                        <!-- Email Provider Settings -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm mt-6">
                            <h3 class="text-lg font-semibold mb-2 flex items-center gap-2"><i data-lucide="mail" class="text-primary"></i> Email Provider Settings</h3>
                            <p class="text-sm text-neutral-500 mb-6">Choose how you want to send emails to your guests. You can use SendGrid API or your own Custom SMTP server.</p>
                            
                            <form @submit.prevent="saveSettings" class="space-y-5">
                                <!-- Provider Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-neutral-700 mb-2">Email Provider</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label @click="settings.email_provider = 'sendgrid'" 
                                               :class="settings.email_provider === 'sendgrid' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-neutral-200 bg-white hover:border-neutral-300'"
                                               class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                                 :class="settings.email_provider === 'sendgrid' ? 'bg-blue-500 text-white' : 'bg-neutral-100 text-neutral-400'">
                                                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M2 8l10 6 10-6M2 8v8l10 6 10-6V8M2 8l10-6 10 6"/></svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-neutral-800">SendGrid</div>
                                                <div class="text-[11px] text-neutral-500">API-based delivery</div>
                                            </div>
                                        </label>
                                        <label @click="settings.email_provider = 'smtp'" 
                                               :class="settings.email_provider === 'smtp' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200' : 'border-neutral-200 bg-white hover:border-neutral-300'"
                                               class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                                 :class="settings.email_provider === 'smtp' ? 'bg-emerald-500 text-white' : 'bg-neutral-100 text-neutral-400'">
                                                <i data-lucide="server" style="width:20px;height:20px"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-neutral-800">Custom SMTP</div>
                                                <div class="text-[11px] text-neutral-500">Your own mail server</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- SendGrid Fields -->
                                <div x-show="settings.email_provider === 'sendgrid'" x-transition class="space-y-4">
                                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 flex items-start gap-2">
                                        <i data-lucide="info" class="text-blue-500 flex-shrink-0 mt-0.5" style="width:16px;height:16px"></i>
                                        <p class="text-xs text-blue-700">SendGrid provides reliable, high-deliverability email sending via API. You need a <a href="https://sendgrid.com" target="_blank" class="underline font-medium">SendGrid account</a> and an API Key with <strong>Mail Send</strong> permission.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-700 mb-1">SendGrid API Key <span class="text-red-500">*</span></label>
                                        <input type="password" x-model="settings.sendgrid_api_key" placeholder="SG.xxxxxxxxxxxx..." class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 outline-none font-mono text-sm">
                                        <p class="text-xs text-neutral-500 mt-1">Found in SendGrid Dashboard → Settings → API Keys.</p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">From Email <span class="text-red-500">*</span></label>
                                            <input type="email" x-model="settings.sendgrid_from_email" placeholder="hello@yourdomain.com" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 outline-none">
                                            <p class="text-xs text-neutral-500 mt-1">Must be a verified Sender Identity in SendGrid.</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">From Name</label>
                                            <input type="text" x-model="settings.sendgrid_from_name" placeholder="Default: Couple Names" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 outline-none">
                                        </div>
                                    </div>
                                </div>

                                <!-- SMTP Fields -->
                                <div x-show="settings.email_provider === 'smtp'" x-transition class="space-y-4">
                                    <div class="bg-emerald-50 border border-emerald-100 rounded-lg p-3 flex items-start gap-2">
                                        <i data-lucide="info" class="text-emerald-500 flex-shrink-0 mt-0.5" style="width:16px;height:16px"></i>
                                        <p class="text-xs text-emerald-700">Use your own SMTP server to send emails. This is typically provided by your domain hosting (e.g., Hostinger, cPanel, Zoho Mail). Leave blank to use default server configuration.</p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">SMTP Host</label>
                                            <input type="text" x-model="settings.smtp_host" placeholder="mail.yourdomain.com" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">SMTP Port</label>
                                            <input type="number" x-model="settings.smtp_port" placeholder="465" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                            <p class="text-xs text-neutral-500 mt-1">Usually 465 for SSL, or 587 for TLS.</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">SMTP Username</label>
                                            <input type="text" x-model="settings.smtp_user" placeholder="hello@yourdomain.com" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">SMTP Password</label>
                                            <input type="password" x-model="settings.smtp_pass" placeholder="Your email password" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">From Email (Optional)</label>
                                            <input type="email" x-model="settings.smtp_from_email" placeholder="Default: Same as Username" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-neutral-700 mb-1">From Name (Optional)</label>
                                            <input type="text" x-model="settings.smtp_from_name" placeholder="Default: Couple Names" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save Email Provider</button>
                                </div>
                            </form>
                        </div>

                        <!-- Gift Registry -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <div class="flex items-center gap-2 mb-4">
                                <i data-lucide="gift" text-primary"></i>
                                <h3 class="text-lg font-semibold">Gift Registry / Wishlist</h3>
                            </div>
                            <p class="text-sm text-neutral-500 mb-4">Add a link to your gift registry or wishlist. This will appear on the RSVP confirmation page.</p>
                            <form @submit.prevent="saveSettings" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-neutral-700 mb-1">Registry URL</label>
                                    <input type="url" x-model="settings.gift_registry_url" placeholder="https://your-registry-link.com" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-neutral-700 mb-1">Custom Button Label</label>
                                    <input type="text" x-model="settings.gift_registry_label" placeholder="Gift Registry" class="w-full border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 outline-none">
                                </div>
                                <template x-if="settings.gift_registry_url">
                                    <div class="p-3 bg-neutral-50 rounded-lg border border-neutral-200">
                                        <p class="text-xs text-neutral-500 mb-1">Preview:</p>
                                        <a :href="settings.gift_registry_url" target="_blank" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline">
                                            ðŸŽ  <span x-text="settings.gift_registry_label || 'Gift Registry'"></span> <i data-lucide="external-link" text-[10px]"></i>
                                        </a>
                                    </div>
                                </template>
                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save Gift Registry</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 6. RSVP FORM -->
                <div x-show="currentTab === 'rsvp_form'" x-cloak>
                    <div class="max-w-3xl mx-auto space-y-6">
                        <!-- RSVP Settings -->
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <h3 class="text-lg font-semibold mb-6">RSVP Questions & Preferences</h3>
                            <form @submit.prevent="saveSettings" class="space-y-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-semibold text-neutral-800 mb-1">RSVP Deadline Date (Optional)</label>
                                        <input type="date" x-model="settings.rsvp_deadline" class="w-full sm:w-1/2 border border-neutral-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                                        <p class="text-xs text-neutral-500 mt-1 block">If set, the RSVP form will automatically close and block new submissions after this date.</p>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold text-neutral-800 mb-3 block">Guest Questions</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm bg-neutral-50 border-neutral-300">
                                            <input type="checkbox" x-model="settings.rsvp_fields.events" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-800 font-medium">Multiple Events Selection</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.meal_choice" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">Meal Preference</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.dietary_notes" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">Dietary Restrictions/Allergies</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.plus_one" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">Accompanying Guests Names & Meals</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.song_request" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">Song Request</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.phone" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">Phone Number</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.accommodation" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">Accommodation / Hotel Location</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 border border-neutral-200 rounded-xl cursor-pointer hover:bg-neutral-50 transition shadow-sm">
                                            <input type="checkbox" x-model="settings.rsvp_fields.notes" class="w-4 h-4 text-primary rounded border-neutral-300 focus:ring-primary">
                                            <span class="text-sm text-neutral-700 font-medium">General Notes (Textarea)</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div x-show="settings.rsvp_fields.events" class="bg-neutral-50 p-5 rounded-xl border border-neutral-200" x-transition>
                                    <h4 class="text-sm font-semibold text-neutral-800 mb-2">Events List (for RSVP Checkboxes)</h4>
                                    <p class="text-xs text-neutral-500 mb-4">Add events so guests can choose which ones they will attend. Leave empty for a simple Attending/Declined RSVP.</p>
                                    
                                    <div class="flex flex-col gap-2 mb-4">
                                        <template x-for="(event, index) in settings.events_list" :key="index">
                                            <div class="bg-white border border-neutral-200 rounded-lg p-3 flex items-center justify-between gap-3 shadow-sm">
                                                <div class="flex-1">
                                                    <span class="text-sm font-medium text-neutral-800" x-text="event"></span>
                                                </div>
                                                <button type="button" @click="removeEvent(index)" class="text-neutral-400 hover:text-red-500 transition px-2"><i data-lucide="trash-2"></i></button>
                                            </div>
                                        </template>
                                        <div x-show="!settings.events_list || settings.events_list.length === 0" class="text-sm text-neutral-500 italic py-2">No specific events added.</div>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <input type="text" x-model="newEventName" @keydown.enter.prevent="addEvent" placeholder="e.g. Akad Nikah (08:00 - 10:00)" class="flex-1 w-full border border-neutral-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                                        <button type="button" @click="addEvent" class="bg-primary hover:bg-primary/90 text-white px-5 py-2 rounded-lg text-sm font-medium transition shadow-sm whitespace-nowrap flex items-center gap-1.5"><i data-lucide="plus" style="width:14px;height:14px"></i> Add Event</button>
                                    </div>
                                </div>
                                
                                <div x-show="settings.rsvp_fields.meal_choice" class="bg-neutral-50 p-5 rounded-xl border border-neutral-200" x-transition>
                                    <h4 class="text-sm font-semibold text-neutral-800 mb-2">Meal Options</h4>
                                    <p class="text-xs text-neutral-500 mb-4">Add the meal choices guests can choose from.</p>
                                    
                                    <div class="flex flex-wrap gap-2 mb-4 relative">
                                        <template x-for="(meal, index) in settings.meal_options" :key="index">
                                            <span class="bg-white border border-neutral-200 text-neutral-700 text-xs px-3 py-1.5 rounded-full shadow-[0_2px_4px_rgba(0,0,0,0.02)] flex items-center gap-2">
                                                <span x-text="meal" class="font-medium"></span>
                                                <button type="button" @click="removeMealOption(index)" class="text-neutral-400 hover:text-red-500 transition"><i data-lucide="x""></i></button>
                                            </span>
                                        </template>
                                        <span x-show="settings.meal_options.length === 0" class="text-xs text-neutral-400 italic py-1.5 block">No meal options added yet.</span>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <input type="text" x-model="newMealOption" @keydown.enter.prevent="addMealOption" placeholder="e.g. Beef Steak" class="flex-1 w-full sm:w-auto border border-neutral-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                                        <button type="button" @click="addMealOption" class="bg-primary hover:bg-primary/90 text-white px-5 py-2 rounded-lg text-sm font-medium transition shadow-sm">Add</button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-neutral-700 mb-1">RSVP Deadline</label>
                                    <input type="date" x-model="settings.rsvp_deadline" class="w-full sm:w-1/2 border border-neutral-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                                </div>

                                <div class="pt-4 border-t border-neutral-100 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm transition">Save RSVP Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 5.5 AUTOMATIONS -->
                <div x-show="currentTab === 'automations'" x-cloak>
                    <div class="max-w-3xl mx-auto space-y-6">
                        
                        <div class="glass-card p-8 rounded-xl border border-neutral-100 shadow-sm">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-1">Automated Emails</h3>
                                <p class="text-sm text-neutral-500">Configure rules to automatically send emails based on the guest's status and event date. <strong>Requires a reliable Cron Job pointing to `scheduler.php`.</strong></p>
                            </div>

                            <form @submit.prevent="_apiCall('update_settings', settings).then(res => showToast(res.message, res.status ? 'success' : 'error'))">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                                    
                                    <!-- Invites -->
                                    <div class="bg-white p-6 rounded-xl border border-neutral-100 shadow-sm relative overflow-hidden group">
                                        <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-bl-full -z-10 transition-transform group-hover:scale-110"></div>
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg shadow-sm"><i data-lucide="mail"-open-text"></i></div>
                                                <div>
                                                    <h4 class="font-semibold text-neutral-800">Formal Invitations</h4>
                                                    <p class="text-xs text-neutral-500">Auto-send to new Pending guests</p>
                                                </div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" x-model="settings.auto_send_invites" class="sr-only peer">
                                                <div class="w-11 h-6 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                                            </label>
                                        </div>
                                        <p class="text-sm text-neutral-600 mb-0">When enabled, the scheduler will automatically send the "Formal Invitation" template to any guest whose RSVP status is <span class="badge bg-neutral-100 px-1 rounded">Pending</span> and who hasn't received an email yet.</p>
                                    </div>

                                    <!-- Reminders -->
                                    <div class="bg-white p-6 rounded-xl border border-neutral-100 shadow-sm relative overflow-hidden group">
                                        <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50 rounded-bl-full -z-10 transition-transform group-hover:scale-110"></div>
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg shadow-sm"><i data-lucide="bell"></i></div>
                                                <div>
                                                    <h4 class="font-semibold text-neutral-800">RSVP Reminders</h4>
                                                    <p class="text-xs text-neutral-500">Auto-send before RSVP Deadline</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="text-sm font-medium text-neutral-700">Send reminder</span>
                                            <input type="number" min="0" max="30" x-model.number="settings.auto_send_reminders_days" class="w-16 border rounded text-center py-1 outline-none focus:border-amber-400">
                                            <span class="text-sm font-medium text-neutral-700">days before deadline.</span>
                                        </div>
                                        <p class="text-sm text-neutral-600">Set to <code>0</code> to disable. Sends the "RSVP Reminder" template to guests who are still Pending.</p>
                                    </div>

                                    <!-- Week-of Details -->
                                    <div class="bg-white p-6 rounded-xl border border-neutral-100 shadow-sm relative overflow-hidden group">
                                        <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-50 rounded-bl-full -z-10 transition-transform group-hover:scale-110"></div>
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg shadow-sm"><i data-lucide="map-pin"tion-dot"></i></div>
                                                <div>
                                                    <h4 class="font-semibold text-neutral-800">Week-Of Details</h4>
                                                    <p class="text-xs text-neutral-500">Auto-send to Attending guests</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="text-sm font-medium text-neutral-700">Send details</span>
                                            <input type="number" min="0" max="30" x-model.number="settings.auto_send_week_of_days" class="w-16 border rounded text-center py-1 outline-none focus:border-emerald-400">
                                            <span class="text-sm font-medium text-neutral-700">days before Wedding date.</span>
                                        </div>
                                        <p class="text-sm text-neutral-600">Set to <code>0</code> to disable. Includes Check-in QR codes and map links.</p>
                                    </div>

                                </div>
                                
                                <div class="flex justify-end pt-4 border-t border-neutral-200">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-xl text-sm font-semibold transition shadow-md hover:shadow-lg hover:-translate-y-0.5">Save Automation Rules</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ACTIVITY LOG -->
                <div x-show="currentTab === 'activity_log'" x-cloak x-init="$watch('currentTab', v => { if (v === 'activity_log') loadActivityLogs(); })">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <h3 class="text-lg font-semibold flex items-center gap-2"><i data-lucide="history" text-primary"></i> Activity Log</h3>
                        <button @click="loadActivityLogs()" class="bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-700 px-4 py-2 rounded-xl text-sm font-medium transition shadow-sm flex items-center gap-2">
                            <i data-lucide="refresh-cw"" :class="activityLoading ? 'animate-spin' : ''"></i> Refresh
                        </button>
                    </div>

                    <div class="glass-card rounded-xl border border-neutral-100 shadow-sm overflow-hidden bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-neutral-50 text-neutral-500 uppercase text-xs font-semibold">
                                    <tr>
                                        <th class="px-6 py-3">Time</th>
                                        <th class="px-6 py-3">User</th>
                                        <th class="px-6 py-3">Action</th>
                                        <th class="px-6 py-3">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-for="log in activityLogs" :key="log.id">
                                        <tr class="hover:bg-neutral-50/50 transition">
                                            <td class="px-6 py-3 text-xs text-neutral-500 whitespace-nowrap" x-text="log.created_at"></td>
                                            <td class="px-6 py-3 font-medium text-neutral-700 text-sm" x-text="log.user_name || 'System'"></td>
                                            <td class="px-6 py-3">
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="{
                                                    'bg-green-50 text-green-700': log.action === 'add_guest' || log.action === 'create',
                                                    'bg-blue-50 text-blue-700': log.action === 'update_guest' || log.action === 'update_settings',
                                                    'bg-red-50 text-red-700': log.action === 'delete_guest' || log.action === 'delete',
                                                    'bg-purple-50 text-purple-700': log.action === 'send_campaign' || log.action === 'send_whatsapp',
                                                    'bg-neutral-100 text-neutral-600': !['add_guest','update_guest','delete_guest','send_campaign','send_whatsapp','update_settings','create','delete'].includes(log.action)
                                                }" x-text="log.action.replace(/_/g, ' ')"></span>
                                            </td>
                                            <td class="px-6 py-3 text-neutral-600 text-xs max-w-xs truncate" x-text="log.details || '-'"></td>
                                        </tr>
                                    </template>
                                    <template x-if="activityLogs.length === 0">
                                        <tr><td colspan="4" class="px-6 py-12 text-center text-neutral-500">
                                            <i data-lucide="history" text-3xl mb-3 text-neutral-300 block"></i>
                                            No activity recorded yet.
                                        </td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 6. SUPER ADMIN -->
                <div x-show="currentTab === 'super_admin'" x-cloak>
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <h3 class="text-lg font-semibold flex items-center gap-2"><i data-lucide="shield" text-primary"></i> Super Admin Panel</h3>
                        <button @click="showAddAdminModal = true" class="w-full lg:w-auto bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-xl text-sm font-medium transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="plus""></i> Create Client Account
                        </button>
                    </div>

                    <div class="glass-card rounded-xl border border-neutral-100 overflow-hidden shadow-sm bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-neutral-50/50 text-neutral-500 text-[11px] uppercase tracking-wider border-b border-neutral-100">
                                        <th class="px-6 py-4 font-semibold">ID</th>
                                        <th class="px-6 py-4 font-semibold">Organiser Names</th>
                                        <th class="px-6 py-4 font-semibold">Username / Email</th>
                                        <th class="px-6 py-4 font-semibold">Joined At</th>
                                        <th class="px-6 py-4 font-semibold">Role</th>
                                        <th class="px-6 py-4 font-semibold text-center">WhatsApp</th>
                                        <th class="px-6 py-4 font-semibold text-right">Admin Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-if="systemAdmins.length === 0">
                                        <tr><td colspan="7" class="px-6 py-8 text-center text-neutral-500 text-sm">No clients found.</td></tr>
                                    </template>
                                    </template>
                                    <template x-for="sysClient in systemAdmins" :key="sysClient.id">
                                        <tr class="hover:bg-neutral-50/50 transition">
                                            <td class="px-6 py-4 text-sm text-neutral-500" x-text="'#' + sysClient.id"></td>
                                            <td class="px-6 py-4 text-sm font-medium text-neutral-800" x-text="sysClient.couple_names"></td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-neutral-800" x-text="sysClient.username"></div>
                                                <div class="text-xs text-neutral-500" x-text="sysClient.email"></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-neutral-500" x-text="sysClient.created_at.substring(0, 10)"></td>
                                            <td class="px-6 py-4">
                                                <span x-show="sysClient.is_admin == 1" class="px-2 py-1 bg-purple-50 text-purple-600 rounded text-xs font-semibold">Super Admin</span>
                                                <span x-show="sysClient.is_admin == 0" class="px-2 py-1 bg-neutral-50 text-neutral-600 rounded text-xs font-semibold">Client</span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" :checked="sysClient.whatsapp_enabled == 1" @change="toggleWhatsApp(sysClient.id, $event.target.checked)" class="sr-only peer">
                                                    <div class="w-9 h-5 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                                                </label>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <button @click="loginAsClient(sysClient.id)" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded text-xs font-semibold transition" title="Login as this account">Login As</button>
                                                    <button x-show="sysClient.id != <?php echo $clientId; ?>" @click="deleteAdminClient(sysClient.id)" class="p-1.5 text-neutral-400 hover:text-red-500 transition" title="Delete Client Permanently"><i data-lucide="trash-2""></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 7. WHATSAPP -->
                <div x-show="currentTab === 'whatsapp'" x-cloak>
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <h3 class="text-lg font-semibold flex items-center gap-2"><i data-lucide="message-circle" text-green-500"></i> WhatsApp Blast</h3>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Composer -->
                        <div class="lg:col-span-2 glass-card p-6 rounded-xl border border-neutral-100 shadow-sm bg-white">
                            <h4 class="text-sm font-semibold text-neutral-600 uppercase tracking-wider mb-4">Compose Message</h4>
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-neutral-700 mb-1">Audience</label>
                                <select x-model="waAudience" class="w-full border border-neutral-200 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-green-200 focus:border-green-400 outline-none">
                                    <option value="all">All Guests with Phone</option>
                                    <option value="attending">Attending Only</option>
                                    <option value="pending">Pending Only</option>
                                    <option value="declined">Declined Only</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-neutral-700 mb-1">Message</label>
                                <textarea x-model="waMessage" rows="6" class="w-full border border-neutral-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-green-200 focus:border-green-400 outline-none resize-none" placeholder="Type your WhatsApp message here..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mb-4">
                                <span class="text-[10px] text-neutral-500 uppercase tracking-wider font-semibold mr-1 self-center">Merge Tags:</span>
                                <button type="button" @click="waMessage += '{{guest_name}}'" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-md hover:bg-green-100 transition font-medium">{{guest_name}}</button>
                                <button type="button" @click="waMessage += '{{event_names}}'" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-md hover:bg-green-100 transition font-medium">{{event_names}}</button>
                                <button type="button" @click="waMessage += '{{event_date}}'" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-md hover:bg-green-100 transition font-medium">{{event_date}}</button>
                                <button type="button" @click="waMessage += '{{event_time}}'" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-md hover:bg-green-100 transition font-medium">{{event_time}}</button>
                                <button type="button" @click="waMessage += '{{venue_name}}'" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-md hover:bg-green-100 transition font-medium">{{venue_name}}</button>
                            </div>
                            <button @click="sendWhatsAppBlast()" :disabled="waSending" class="w-full bg-green-500 hover:bg-green-600 disabled:bg-green-300 text-white px-6 py-3 rounded-xl text-sm font-semibold transition shadow-sm flex items-center justify-center gap-2">
                                <i data-lucide="message-circle""></i>
                                <span x-text="waSending ? 'Sending...' : 'Send WhatsApp Blast'"></span>
                            </button>
                        </div>

                        <!-- Settings & Info -->
                        <div class="space-y-4">
                            <div class="glass-card p-5 rounded-xl border border-neutral-100 shadow-sm bg-white">
                                <h4 class="text-sm font-semibold text-neutral-600 uppercase tracking-wider mb-3">Fonnte Configuration</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-neutral-500 mb-1">API Token</label>
                                        <input type="password" x-model="settings.fonnte_token" class="w-full border border-neutral-200 rounded-lg p-2 text-sm" placeholder="Your Fonnte API token">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-neutral-500 mb-1">Device ID (optional)</label>
                                        <input type="text" x-model="settings.fonnte_device" class="w-full border border-neutral-200 rounded-lg p-2 text-sm" placeholder="e.g. abc123">
                                    </div>
                                    <button @click="saveSettings()" class="w-full bg-neutral-100 hover:bg-neutral-200 text-neutral-700 px-4 py-2 rounded-lg text-xs font-semibold transition">Save Fonnte Settings</button>
                                </div>
                            </div>
                            <div class="glass-card p-5 rounded-xl border border-neutral-100 shadow-sm bg-green-50/50">
                                <h4 class="text-sm font-semibold text-green-700 mb-2"><i data-lucide="info" mr-1"></i> Tips</h4>
                                <ul class="text-xs text-green-800 space-y-1.5 leading-relaxed">
                                    <li>â€¢ Get your API token from <a href="https://fonnte.com" target="_blank" class="underline font-semibold">fonnte.com</a></li>
                                    <li>â€¢ Only guests with phone numbers will receive messages</li>
                                    <li>â€¢ Use merge tags to personalize each message</li>
                                    <li>â€¢ Messages are sent one by one for reliability</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- History -->
                    <div class="glass-card rounded-xl border border-neutral-100 shadow-sm overflow-hidden bg-white mt-6">
                        <div class="px-6 py-4 border-b border-neutral-100"><h4 class="text-sm font-semibold text-neutral-600 uppercase tracking-wider">Blast History</h4></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-neutral-50 text-neutral-500 uppercase text-xs font-semibold">
                                    <tr>
                                        <th class="px-6 py-3">Date</th>
                                        <th class="px-6 py-3">Audience</th>
                                        <th class="px-6 py-3">Message</th>
                                        <th class="px-6 py-3">Sent</th>
                                        <th class="px-6 py-3">Failed</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    <template x-for="log in waLogs" :key="log.id">
                                        <tr class="hover:bg-neutral-50/50 transition">
                                            <td class="px-6 py-3 text-neutral-500 text-xs whitespace-nowrap" x-text="log.created_at"></td>
                                            <td class="px-6 py-3"><span class="bg-neutral-100 px-2 py-0.5 rounded text-xs font-semibold capitalize" x-text="log.audience_filter"></span></td>
                                            <td class="px-6 py-3 text-neutral-700 max-w-xs truncate" x-text="log.message.substring(0, 80) + (log.message.length > 80 ? '...' : '')"></td>
                                            <td class="px-6 py-3 text-green-600 font-semibold" x-text="log.total_sent"></td>
                                            <td class="px-6 py-3 text-red-500 font-semibold" x-text="log.total_failed"></td>
                                        </tr>
                                    </template>
                                    <template x-if="waLogs.length === 0">
                                        <tr><td colspan="5" class="px-6 py-8 text-center text-neutral-500 text-sm">No WhatsApp blasts sent yet.</td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Add Guest Modal -->
    <div x-show="showAddGuestModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
        <div class="absolute inset-0 bg-neutral-900/40 backdrop-blur-sm" @click="showAddGuestModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-semibold text-neutral-800 mb-4">Add Guest</h3>
            <form @submit.prevent="addGuest">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-neutral-700 mb-1">Full Name *</label><input type="text" x-model="newGuest.name" required class="w-full border p-2 rounded-lg"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-neutral-700 mb-1">Email</label><input type="email" x-model="newGuest.email" class="w-full border p-2 rounded-lg"></div>
                        <div x-effect="if (showAddGuestModal && !itiAddGuest) {
                            $nextTick(() => {
                                itiAddGuest = window.intlTelInput($refs.phoneInput, {
                                    initialCountry: 'id',
                                    strictMode: true,
                                    separateDialCode: true,
                                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/js/utils.js'
                                });
                            });
                        }">
                            <label class="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
                            <input type="tel" x-ref="phoneInput" x-model="newGuest.phone" placeholder="812 3456 7890" class="w-full border py-2 pr-2 rounded-lg iti-input">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-neutral-700 mb-1">Group Label</label><input type="text" x-model="newGuest.group_label" placeholder="e.g. Mr & Mrs Smith" class="w-full border p-2 rounded-lg w-full"></div>
                        <div><label class="block text-sm font-medium text-neutral-700 mb-1">Table Number</label><input type="text" x-model="newGuest.table_number" placeholder="e.g. VIP-1" class="w-full border p-2 rounded-lg uppercase w-full"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-neutral-700 mb-1">Seats Given</label><input type="number" min="1" x-model="newGuest.seats_allocated" class="w-full border p-2 rounded-lg"></div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showAddGuestModal = false" class="px-4 py-2 border rounded-lg hover:bg-neutral-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Save Guest</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Campaign Modal -->
    <div x-show="showCreateCampaignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
        <div class="absolute inset-0 bg-neutral-900/40 backdrop-blur-sm" @click="showCreateCampaignModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
            <h3 class="text-lg font-semibold text-neutral-800 mb-4">Create Campaign</h3>
            <form @submit.prevent="createCampaign">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Campaign Type</label>
                        <select x-model="newCampaign.type" class="w-full border p-2.5 rounded-lg outline-none cursor-pointer">
                            <template x-for="tmpl in templatesList" :key="tmpl.id">
                                <option :value="tmpl.id" x-text="tmpl.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Subject Line</label>
                        <input type="text" x-model="newCampaign.subject" placeholder="Leave empty for default subject" class="w-full border p-2.5 rounded-lg text-sm">
                        <p class="text-xs text-neutral-500 mt-1">Supports merge tags like {{guest_name}}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Target Audience</label>
                        <select x-model="newCampaign.audience_filter" class="w-full border p-2.5 rounded-lg outline-none cursor-pointer">
                            <option value="all">All Guests</option>
                            <option value="attending">Attending Only</option>
                            <option value="pending">Pending Only</option>
                            <option value="declined">Declined Only</option>
                        </select>
                        <p class="text-xs text-neutral-500 mt-1">Select who should receive this campaign.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3 flex-wrap">
                    <button type="button" @click="showCreateCampaignModal = false" class="px-4 py-2 border rounded-lg hover:bg-neutral-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Create Draft</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div x-show="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
        <div class="absolute inset-0 bg-neutral-900/40 backdrop-blur-sm" @click="showImportModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-semibold text-neutral-800 mb-4">Import CSV</h3>
            <p class="text-sm text-neutral-500 mb-4">Upload a CSV file without headers. Format: <code>Name,Email,Phone,Group Label,Seats</code></p>
            <form @submit.prevent="importCsv" id="importForm" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-neutral-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 mb-4 cursor-pointer">
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showImportModal = false" class="px-4 py-2 border rounded-lg hover:bg-neutral-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Import Data</button>
                </div>
            </form>
        </div>
    </div>
        </div>
    </div>

    <!-- Create Client Account Modal (Super Admin) -->
    <div x-show="showAddAdminModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
        <div class="absolute inset-0 bg-neutral-900/40 backdrop-blur-sm" @click="showAddAdminModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-semibold text-neutral-800 mb-4">Create New Client Account</h3>
            <form @submit.prevent="createAdminClient">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Organiser Names *</label>
                        <input type="text" x-model="newAdmin.couple_names" required placeholder="Emma & James" class="w-full border p-2 rounded-lg outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Email *</label>
                        <input type="email" x-model="newAdmin.email" required placeholder="client@wedding.com" class="w-full border p-2 rounded-lg outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Username *</label>
                        <input type="text" x-model="newAdmin.username" required placeholder="emmajames26" class="w-full border p-2 rounded-lg outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Temporary Password *</label>
                        <input type="password" x-model="newAdmin.password" required minlength="6" placeholder="Min 6 characters" class="w-full border p-2 rounded-lg outline-none focus:border-primary">
                        <p class="text-xs text-neutral-500 mt-1">Provide this password to the client.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showAddAdminModal = false" class="px-4 py-2 border rounded-lg hover:bg-neutral-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- App Logic -->
    <script>
        const DEFAULT_GENERAL_TEMPLATE = `<div style="font-family: inherit; color: #334155;">
    <p>Hi <strong>{{guest_name}}</strong>,</p>
    
    <p>You are invited to attend <strong>{{event_name}}</strong>.</p>
    
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
        <h3 style="margin-top: 0; color: #0f172a; font-size: 16px;">Event Details</h3>
        <p style="margin: 8px 0; font-size: 15px;"><strong>Date:</strong> {{event_date}}</p>
        <p style="margin: 8px 0; font-size: 15px;"><strong>Time:</strong> {{event_time}}</p>
        <p style="margin: 8px 0; font-size: 15px;"><strong>Location:</strong> {{event_location}}</p>
    </div>
    
    <p>We would appreciate your response at your earliest convenience.</p>
    
    <p style="margin-bottom: 24px;">Please confirm your attendance by clicking below:</p>
    
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{rsvp_link}}" style="display: inline-block; background-color: {{color_primary}}; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; letter-spacing: 0.5px; border: 1px solid {{color_primary}};">RSVP NOW</a>
    </div>
    
    <p>We look forward to hosting you.</p>
    
    <p style="margin-bottom: 0;">Warm regards,<br>
    <strong>{{host_name}}</strong></p>
</div>`;

        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardApp', () => ({
                isAdmin: <?php echo json_encode((bool)($client['is_admin'] ?? false)); ?>,
                waEnabled: <?php echo json_encode((bool)($client['whatsapp_enabled'] ?? false)); ?>,
                mobileMenuOpen: false,
                currentTab: 'overview',
                toasts: [],
                stats: { total: 0, attending: 0, declined: 0, pending: 0, emails_sent: 0, emails_opened: 0, campaigns_total: 0, recent_activity: [] },

                // Lucide SVG helper for Alpine dynamic rendering
                lucideIcon(name, size = 17) {
                    return `<i data-lucide="${name}" style="width:${size}px;height:${size}px;stroke-width:1.75"></i>`;
                },

                // Guests
                guests: [],
                guestFilter: 'all',
                guestSearch: '',
                showAddGuestModal: false,
                showImportModal: false,
                newGuest: { name: '', email: '', phone: '', group_label: '', seats_allocated: 1 },
                
                // Super Admin
                systemAdmins: [],
                showAddAdminModal: false,
                newAdmin: { username: '', password: '', couple_names: '', email: '' },
                
                // WhatsApp
                waMessage: '',
                waAudience: 'all',
                waLogs: [],
                waSending: false,

                // Activity Log
                activityLogs: [],
                activityLoading: false,
                
                // Campaigns
                campaigns: [],
                showCreateCampaignModal: false,
                newCampaign: { type: 'save_the_date', subject: '', audience_filter: 'all' },
                
                // Templates
                previewTemplateId: 'save_the_date',
                templateMode: 'preview', // 'preview' or 'edit'
                isLoadingPreview: false,
                templateTextsLoaded: false,
                isSavingTexts: false,
                editingTexts: {},
                defaultTexts: {},
                templatesList: [
                    { id: 'save_the_date', name: 'Save the Date', desc: 'Sent 6-8 months before. Let guests know to mark their calendars.', icon: 'calendar' },
                    { id: 'formal_invitation', name: 'Formal Invitation', desc: 'The main invitation with RSVP link.', icon: 'mail-open' },
                    { id: 'rsvp_reminder', name: 'RSVP Reminder', desc: 'Gentle reminder for non-responders.', icon: 'bell' },
                    { id: 'week_of_details', name: 'Week-of Details', desc: 'Final timeline and venue details.', icon: 'map-pin' },
                    { id: 'thank_you', name: 'Thank You', desc: 'Sent after the wedding.', icon: 'heart' },
                    { id: 'general', name: 'General Email', desc: 'Custom email for any purpose — write your own content.', icon: 'file-edit' }
                ],
                
                // Settings
                settings: (function() {
                    let s = <?php echo json_encode(array_diff_key($client, array_flip(['password_hash', 'id']))); ?>;
                    try { s.rsvp_fields = s.rsvp_fields ? JSON.parse(s.rsvp_fields) : {}; } catch(e) { s.rsvp_fields = {}; }
                    try { s.meal_options = s.meal_options ? JSON.parse(s.meal_options) : []; } catch(e) { s.meal_options = []; }
                    try { s.events_list = s.events_list ? JSON.parse(s.events_list) : []; } catch(e) { s.events_list = []; }
                    if (!s.email_provider) s.email_provider = 'sendgrid';
                    return s;
                })(),
                
                newMealOption: '',
                addMealOption() {
                    const val = this.newMealOption.trim();
                    if (val && !this.settings.meal_options.includes(val)) {
                        this.settings.meal_options.push(val);
                        this.newMealOption = '';
                    }
                },
                removeMealOption(index) {
                    this.settings.meal_options.splice(index, 1);
                },

                newEventName: '',
                addEvent() {
                    const val = this.newEventName.trim();
                    if (val && !this.settings.events_list.includes(val)) {
                        this.settings.events_list.push(val);
                        this.newEventName = '';
                    }
                },
                removeEvent(index) {
                    this.settings.events_list.splice(index, 1);
                },

                menuItems: [
                    { id: 'overview', label: 'Overview', lucide: 'layout-dashboard' },
                    { id: 'guests', label: 'Guest List', lucide: 'users-round' },
                    { id: 'rsvp_responses', label: 'RSVP Responses', lucide: 'inbox' },
                    { id: 'campaigns', label: 'Campaigns', lucide: 'megaphone' },
                    { id: 'templates', label: 'Email Templates', lucide: 'palette' },
                    { id: 'rsvp_form', label: 'RSVP Form', lucide: 'file-text' },
                    { id: 'automations', label: 'Automations', lucide: 'zap' },
                    { id: 'settings', label: 'Settings', lucide: 'sliders-horizontal' }
                ],

                receptionists: [],
                newReceptionist: { name: '', username: '', pin: '' },

                init() {
                    if (this.isAdmin) {
                        this.menuItems.push({ id: 'super_admin', label: 'System Admins', lucide: 'shield-check' });
                        this.loadSystemAdmins();
                    }
                    if (this.waEnabled) {
                        this.menuItems.splice(this.menuItems.findIndex(m => m.id === 'settings'), 0, { id: 'whatsapp', label: 'WhatsApp', lucide: 'message-circle' });
                        this.loadWhatsAppLogs();
                    }
                    // Add Activity Log tab before settings
                    this.menuItems.splice(this.menuItems.findIndex(m => m.id === 'settings'), 0, { id: 'activity_log', label: 'Activity Log', lucide: 'clock' });
                    this.loadStats();
                    this.loadGuests();
                    this.loadCampaigns();
                    this.loadReceptionists();
                    this.loadTemplateTexts();
                    
                    // Simple polling for updates when on overview
                    setInterval(() => {
                        if (this.currentTab === 'overview') this.loadStats();
                    }, 30000);

                    // Re-render icons and charts when tab changes
                    this.$watch('currentTab', (val) => {
                        window.renderLucide();
                        if (val === 'overview') {
                            setTimeout(() => this.renderCharts(), 150);
                        }
                        if (val === 'templates' && !this.templateTextsLoaded) {
                            this.loadTemplateTexts();
                        }
                    });
                },

                get filteredGuests() {
                    return this.guests.filter(g => {
                        const matchFilter = this.guestFilter === 'all' || g.rsvp_status === this.guestFilter;
                        const matchSearch = this.guestSearch === '' || 
                                          g.name.toLowerCase().includes(this.guestSearch.toLowerCase()) || 
                                          (g.email && g.email.toLowerCase().includes(this.guestSearch.toLowerCase()));
                        return matchFilter && matchSearch;
                    });
                },

                get respondedGuests() {
                    let responded = this.guests.filter(g => g.rsvp_status !== 'pending');
                    // Sort descending by responded_at
                    return responded.sort((a, b) => new Date(b.rsvp_responded_at || 0) - new Date(a.rsvp_responded_at || 0));
                },

                showToast(msg, type = 'success') {
                    const id = Date.now();
                    this.toasts.push({ id, message: msg, type, visible: true });
                    setTimeout(() => {
                        const toast = this.toasts.find(t => t.id === id);
                        if (toast) toast.visible = false;
                        setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 300);
                    }, 3000);
                },

                async _apiCall(action, formData = null) {
                    try {
                        const url = 'api.php?action=' + action;
                        const opts = { method: formData ? 'POST' : 'GET' };
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        
                        if (formData && !(formData instanceof FormData)) {
                            const params = new URLSearchParams();
                            Object.entries(formData).forEach(([key, value]) => {
                                params.append(key, value === null ? '' : value);
                            });
                            params.append('csrf_token', csrfToken);
                            opts.body = params;
                            opts.headers = { 
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-Token': csrfToken
                            };
                        } else if (formData instanceof FormData) {
                            formData.append('csrf_token', csrfToken);
                            opts.body = formData;
                            opts.headers = { 'X-CSRF-Token': csrfToken };
                        }
                        
                        const res = await fetch(url, opts);
                        
                        // Handle forbidden / session timeout gracefully
                        if (res.status === 401 || res.status === 403) {
                            window.location.href = 'login.php';
                            return { status: false, message: 'Session expired or forbidden' };
                        }
                        
                        return await res.json();
                    } catch (e) {
                        this.showToast('Network error', 'error');
                        return { status: false };
                    }
                },

                async loadStats() {
                    const res = await this._apiCall('get_stats');
                    if (res.status) {
                        this.stats = res.data;
                        window.renderLucide();
                        if (this.currentTab === 'overview') {
                            setTimeout(() => this.renderCharts(), 150);
                        }
                    }
                },

                _rsvpChart: null,
                _mealChart: null,
                renderCharts() {
                    const rsvpData = [this.stats.attending || 0, this.stats.declined || 0, this.stats.pending || 0];

                    // RSVP Pie — update if exists, create if not
                    if (this._rsvpChart) {
                        this._rsvpChart.data.datasets[0].data = rsvpData;
                        this._rsvpChart.update();
                    } else {
                        const pieCtx = document.getElementById('rsvpPieChart');
                        if (pieCtx) {
                            this._rsvpChart = new Chart(pieCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: ['Attending', 'Declined', 'Pending'],
                                    datasets: [{ data: rsvpData, backgroundColor: ['#22c55e', '#ef4444', '#f59e0b'], borderWidth: 0, hoverOffset: 8 }]
                                },
                                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, font: { size: 11, family: 'Inter' } } } }, cutout: '60%' }
                            });
                        }
                    }

                    // Meal Bar — update if exists, create if not
                    const colors = ['#4A6741', '#6B8F65', '#8BB584', '#A0C49D', '#C7DFC3', '#7C9A6E'];
                    if (this.stats.meals && this.stats.meals.length > 0) {
                        const mealLabels = this.stats.meals.map(m => m.meal_choice || 'N/A');
                        const mealData = this.stats.meals.map(m => m.cnt);
                        const mealColors = this.stats.meals.map((_, i) => colors[i % colors.length]);

                        if (this._mealChart) {
                            this._mealChart.data.labels = mealLabels;
                            this._mealChart.data.datasets[0].data = mealData;
                            this._mealChart.data.datasets[0].backgroundColor = mealColors;
                            this._mealChart.update();
                        } else {
                            const barCtx = document.getElementById('mealBarChart');
                            if (barCtx) {
                                this._mealChart = new Chart(barCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: mealLabels,
                                        datasets: [{ label: 'Guests', data: mealData, backgroundColor: mealColors, borderRadius: 6, barPercentage: 0.7 }]
                                    },
                                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10, family: 'Inter' } } }, x: { ticks: { font: { size: 9, family: 'Inter' }, maxRotation: 45 } } } }
                                });
                            }
                        }
                    }
                },

                async loadGuests() {
                    this.guestsLoading = true;
                    const res = await this._apiCall('get_guests');
                    if (res.status) {
                        this.guests = res.data;
                        window.renderLucide();
                    }
                    this.guestsLoading = false;
                },
                
                async addGuest() {
                    // Get formatted intl number before submitting
                    if (typeof itiAddGuest !== 'undefined' && itiAddGuest !== null) {
                        this.newGuest.phone = itiAddGuest.getNumber();
                    }
                    
                    const res = await this._apiCall('add_guest', this.newGuest);
                    if (res.status) {
                        this.showToast('Guest added');
                        this.showAddGuestModal = false;
                        this.newGuest = { name: '', email: '', phone: '', group_label: '', seats_allocated: 1 };
                        if (typeof itiAddGuest !== 'undefined' && itiAddGuest !== null) {
                            itiAddGuest.setNumber('');
                        }
                        this.loadGuests();
                        this.loadStats();
                    } else this.showToast(res.message, 'error');
                },
                
                async deleteGuest(id) {
                    if (!confirm('Are you sure you want to delete this guest?')) return;
                    const res = await this._apiCall('delete_guest', { guest_id: id });
                    if (res.status) {
                        this.showToast('Guest deleted');
                        this.loadGuests();
                        this.loadStats();
                    }
                },

                async importCsv(e) {
                    const fd = new FormData(e.target);
                    const res = await this._apiCall('import_guests', fd);
                    if (res.status) {
                        this.showToast(res.message);
                        this.showImportModal = false;
                        this.loadGuests();
                        this.loadStats();
                    } else {
                        this.showToast(res.message || 'Import failed', 'error');
                    }
                },

                copyRsvp(token) {
                    let url = '';
                    if (this.settings && this.settings.embed_rsvp_url) {
                        url = this.settings.embed_rsvp_url.replace(/\/$/, '') + '/?token=' + token;
                    } else {
                        url = window.location.origin + window.location.pathname.replace('dashboard.php', '') + 'rsvp.php?token=' + token;
                    }
                    navigator.clipboard.writeText(url).then(() => this.showToast('RSVP link copied to clipboard'));
                },

                async toggleCheckIn(guest) {
                    const newStatus = guest.check_in_status == 1 ? 0 : 1;
                    const action = newStatus ? 'check-in' : 'undo check-in';
                    if (newStatus === 0 && !confirm('Undo check-in for ' + guest.name + '?')) return;
                    const res = await this._apiCall('toggle_checkin', { guest_id: guest.id, status: newStatus });
                    if (res.status) {
                        guest.check_in_status = newStatus;
                        guest.check_in_time = newStatus ? new Date().toLocaleString() : null;
                        this.showToast(guest.name + ': ' + (newStatus ? 'âœ… Checked in' : 'Check-in undone'));
                        this.loadStats();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                async loadCampaigns() {
                    const res = await this._apiCall('get_campaigns');
                    if (res.status) {
                        this.campaigns = res.data;
                        window.renderLucide();
                    }
                },

                // -------------------------------------------------------------------------------------------------
                // RECEPTIONIST MANAGEMENT
                // -------------------------------------------------------------------------------------------------
                async loadReceptionists() {
                    const res = await this._apiCall('get_receptionists');
                    if (res.status) {
                        this.receptionists = res.data;
                        window.renderLucide();
                    }
                },
                async createReceptionist() {
                    const { name, username, pin } = this.newReceptionist;
                    if (!name || !username || !pin) { this.showToast('All fields required', 'error'); return; }
                    const res = await this._apiCall('create_receptionist', { name, username, pin });
                    if (res.status) {
                        this.showToast('Receptionist "' + name + '" created!');
                        this.newReceptionist = { name: '', username: '', pin: '' };
                        this.loadReceptionists();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                async deleteReceptionist(rec) {
                    if (!confirm('Delete receptionist "' + rec.name + '"?')) return;
                    const res = await this._apiCall('delete_receptionist', { receptionist_id: rec.id });
                    if (res.status) {
                        this.showToast('Receptionist deleted');
                        this.loadReceptionists();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                // -------------------------------------------------------------------------------------------------
                // SUPER ADMIN FUNCTIONS
                // -------------------------------------------------------------------------------------------------
                async loadSystemAdmins() {
                    if (!this.isAdmin) return;
                    const res = await this._apiCall('admin_get_clients');
                    if (res.status) this.systemAdmins = res.data;
                },
                
                async createAdminClient() {
                    const res = await this._apiCall('admin_create_client', this.newAdmin);
                    if (res.status) {
                        this.showToast('Client account created successfully!');
                        this.showAddAdminModal = false;
                        this.newAdmin = { username: '', password: '', couple_names: '', email: '' };
                        this.loadSystemAdmins();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                
                async deleteAdminClient(id) {
                    if (!confirm('WARNING: PERMANENT DELETION.\nAre you absolutely sure you want to delete this client? ALL their guests, campaigns, and settings will be permanently destroyed.')) return;
                    const res = await this._apiCall('admin_delete_client', { client_id: id });
                    if (res.status) {
                        this.showToast(res.message);
                        this.loadSystemAdmins();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                
                async loginAsClient(id) {
                    if (!confirm('This will switch your session to this client account. Continue?')) return;
                    const res = await this._apiCall('admin_login_as', { client_id: id });
                    if (res.status) {
                        window.location.reload();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                // -------------------------------------------------------------------------------------------------
                // WHATSAPP FUNCTIONS
                // -------------------------------------------------------------------------------------------------
                async toggleWhatsApp(clientId, enabled) {
                    const res = await this._apiCall('admin_toggle_whatsapp', { client_id: clientId, enable: enabled ? '1' : '0' });
                    if (res.status) {
                        this.showToast(res.message);
                        // Update local state
                        const client = this.systemAdmins.find(c => c.id == clientId);
                        if (client) client.whatsapp_enabled = enabled ? 1 : 0;
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                async sendWhatsAppBlast() {
                    if (!this.waMessage.trim()) return this.showToast('Please enter a message', 'error');
                    if (!confirm('Send WhatsApp blast to ' + this.waAudience + ' guests?')) return;
                    this.waSending = true;
                    const res = await this._apiCall('send_whatsapp_blast', { message: this.waMessage, audience: this.waAudience });
                    this.waSending = false;
                    if (res.status) {
                        this.showToast(res.message);
                        this.waMessage = '';
                        this.loadWhatsAppLogs();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                async loadWhatsAppLogs() {
                    const res = await this._apiCall('get_whatsapp_logs');
                    if (res.status) this.waLogs = res.data;
                },

                // -------------------------------------------------------------------------------------------------
                // ACTIVITY LOG FUNCTIONS
                // -------------------------------------------------------------------------------------------------
                async loadActivityLogs() {
                    this.activityLoading = true;
                    const res = await this._apiCall('get_activity_logs');
                    this.activityLoading = false;
                    if (res.status) this.activityLogs = res.data;
                },

                // -------------------------------------------------------------------------------------------------
                // TEMPLATE EDITOR FUNCTIONS
                // -------------------------------------------------------------------------------------------------
                selectTemplate(id) {
                    this.previewTemplateId = id;
                    // Reset to preview mode by default when switching, or keep current mode
                    if (this.templateMode === 'preview') {
                        this.loadPreview();
                    } else {
                        if (!this.templateTextsLoaded) this.loadTemplateTexts();
                    }
                },
                
                async loadTemplateTexts() {
                    this.templateTextsLoaded = false;
                    const res = await this._apiCall('get_template_texts', {}, 'GET');
                    if (res.status) {
                        // Deep clone to separate editing state from defaults
                        this.editingTexts = JSON.parse(JSON.stringify(res.data));
                        this.defaultTexts = res.defaults;
                    }
                    this.templateTextsLoaded = true;
                },

                getTemplateTextKeys(typeId) {
                    if (!this.editingTexts || !this.editingTexts[typeId]) return [];
                    return Object.keys(this.editingTexts[typeId]);
                },

                formatFieldLabel(key) {
                    // Convert "hero_title" to "Hero Title", "venue_name_label" to "Venue Name Label"
                    return key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                },

                isLongField(key) {
                    // Determine if a field should be a textarea instead of input
                    const longFields = ['message', 'body', 'details', 'wishing_well_text', 'closing', 'intro', 'description'];
                    return longFields.some(lf => key.includes(lf));
                },

                async saveTemplateTexts() {
                    if (!this.editingTexts[this.previewTemplateId]) return;
                    
                    this.isSavingTexts = true;
                    const textsJson = JSON.stringify(this.editingTexts[this.previewTemplateId]);
                    
                    const res = await this._apiCall('save_template_texts', {
                        template_type: this.previewTemplateId,
                        texts: textsJson
                    });
                    
                    this.isSavingTexts = false;
                    if (res.status) {
                        this.showToast(res.message);
                        // Optionally refresh preview iframe if it's visible or next time it's loaded
                        const iframe = document.getElementById('previewFrame');
                        if (iframe && iframe.src) {
                            iframe.src = iframe.src;
                        }
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                async resetTemplateTexts() {
                    if (!confirm("Are you sure you want to reset all texts for this template back to the system defaults? This cannot be undone.")) return;
                    
                    this.isSavingTexts = true;
                    const res = await this._apiCall('reset_template_texts', {
                        template_type: this.previewTemplateId
                    });
                    
                    this.isSavingTexts = false;
                    if (res.status) {
                        this.showToast(res.message);
                        this.editingTexts[this.previewTemplateId] = JSON.parse(JSON.stringify(res.data));
                        
                        const iframe = document.getElementById('previewFrame');
                        if (iframe && iframe.src) {
                            iframe.src = iframe.src;
                        }
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },

                testEmailAddress: '',
                isSendingTest: false,
                async sendTestEmail() {
                    const email = this.testEmailAddress.trim();
                    if (!email) {
                        this.showToast('Please enter an email address', 'error');
                        return;
                    }
                    if (!/^\S+@\S+\.\S+$/.test(email)) {
                        this.showToast('Please enter a valid email address', 'error');
                        return;
                    }

                    this.isSendingTest = true;
                    try {
                        const fd = new FormData();
                        fd.append('type', this.previewTemplateId);
                        fd.append('test_email', email);
                        
                        const res = await this._apiCall('send_test_email', fd);
                        if (res.status) {
                            this.showToast(res.message, 'success');
                            this.testEmailAddress = ''; // clear input on success
                        } else {
                            this.showToast(res.message || 'Failed to send test email', 'error');
                        }
                    } catch (e) {
                        this.showToast('Network error while sending test email', 'error');
                    } finally {
                        this.isSendingTest = false;
                    }
                },

                currentLogStatus: 'all',

                async createCampaign() {
                    const campaignData = { 
                        campaign_type: this.newCampaign.type, 
                        subject: this.newCampaign.subject,
                        audience_filter: this.newCampaign.audience_filter
                    };
                    const res = await this._apiCall('create_campaign', campaignData);
                    if (res.status) {
                        this.showToast('Campaign drafted');
                        this.showCreateCampaignModal = false;
                        this.newCampaign.subject = '';
                        this.newCampaign.html_body = '';
                        this.loadCampaigns();
                        this.loadStats();
                    } else this.showToast(res.message, 'error');
                },

                async sendCampaign(id) {
                    if (!confirm('Are you sure you want to start sending this campaign?')) return;
                    this.showToast('Sending started...');
                    // Optimistic update
                    const c = this.campaigns.find(x => x.id === id);
                    if (c) c.status = 'sending';
                    
                    const res = await this._apiCall('send_campaign', { campaign_id: id });
                    if (res.status) {
                        this.showToast(res.message);
                        this.loadCampaigns();
                        this.loadStats();
                    } else this.showToast(res.message || 'Send failed', 'error');
                },
                
                async deleteCampaign(id) {
                    if (!confirm('Delete this campaign?')) return;
                    const res = await this._apiCall('delete_campaign', { campaign_id: id });
                    if (res.status) {
                        this.showToast('Campaign deleted');
                        this.loadCampaigns();
                    }
                },

                loadPreview() {
                    this.isLoadingPreview = true;
                    setTimeout(() => {
                        const frame = document.getElementById('previewFrame');
                        if (frame) {
                            frame.onload = () => { this.isLoadingPreview = false; };
                            // Fallback if onload doesn't fire
                            setTimeout(() => { this.isLoadingPreview = false; }, 1000);
                        } else this.isLoadingPreview = false;
                    }, 10);
                },

                async saveSettings() {
                    const data = { ...this.settings };
                    data.rsvp_fields = JSON.stringify(data.rsvp_fields || {});
                    data.meal_options = JSON.stringify(data.meal_options || []);
                    data.events_list = JSON.stringify(data.events_list || []);
                    // Handle empty values
                    if (!data.event_date) delete data.event_date;
                    const res = await this._apiCall('update_settings', data);
                    if (res.status) {
                        this.showToast('Settings saved successfully');
                        // Reload page to apply theme colors if changed
                        if (this.settings.color_primary) {
                            setTimeout(() => window.location.reload(), 1000);
                        }
                    } else this.showToast(res.message || 'Error saving', 'error');
                },

                exportCateringPDF() {
                    const coupleName = this.settings.couple_names || 'Wedding';
                    const totalGuests = this.stats.total_actual_attending || this.stats.attending || 0;
                    const meals = this.stats.meals || [];
                    const dietary = this.stats.dietary_notes || [];
                    const now = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });

                    let mealRows = '';
                    let totalMealCount = 0;
                    meals.forEach(m => {
                        totalMealCount += parseInt(m.cnt);
                        mealRows += `<tr><td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#334155;">${m.meal_choice || 'Not Specified'}</td><td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;font-weight:600;text-align:center;color:#1e293b;">${m.cnt}</td></tr>`;
                    });
                    if (meals.length === 0) {
                        mealRows = '<tr><td colspan="2" style="padding:16px;text-align:center;color:#94a3b8;font-style:italic;">No meal data recorded yet.</td></tr>';
                    }

                    let dietaryRows = '';
                    dietary.forEach(d => {
                        dietaryRows += `<tr><td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;font-weight:500;color:#1e293b;">${d.name}</td><td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#dc2626;">${d.dietary_notes}</td></tr>`;
                    });
                    if (dietary.length === 0) {
                        dietaryRows = '<tr><td colspan="2" style="padding:16px;text-align:center;color:#94a3b8;font-style:italic;">No dietary restrictions noted.</td></tr>';
                    }

                    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Catering Report - ${coupleName}</title>
                    <style>
                        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600&display=swap');
                        * { margin:0; padding:0; box-sizing:border-box; }
                        body { font-family:'Inter',sans-serif; color:#334155; padding:40px; background:#fff; }
                        .header { text-align:center; margin-bottom:32px; padding-bottom:24px; border-bottom:2px solid #e2e8f0; }
                        .header h1 { font-family:'Playfair Display',serif; font-size:28px; color:#1e293b; margin-bottom:6px; }
                        .header p { font-size:13px; color:#64748b; }
                        .summary { display:flex; gap:16px; margin-bottom:32px; }
                        .summary-card { flex:1; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; text-align:center; }
                        .summary-card .num { font-size:32px; font-weight:700; color:#1e293b; }
                        .summary-card .label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
                        h2 { font-size:16px; font-weight:600; color:#1e293b; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
                        h2 .dot { width:8px; height:8px; border-radius:50%; }
                        table { width:100%; border-collapse:collapse; margin-bottom:32px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
                        thead th { background:#f8fafc; padding:12px 16px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#64748b; font-weight:600; border-bottom:2px solid #e2e8f0; }
                        thead th:last-child { text-align:center; }
                        .footer { margin-top:40px; text-align:center; font-size:11px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:16px; }
                        @media print { body { padding:20px; } .no-print { display:none; } }
                    </style></head><body>
                    <div class="no-print" style="text-align:center;margin-bottom:24px;">
                        <button onclick="window.print()" style="background:#4A6741;color:#fff;border:none;padding:12px 32px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:Inter,sans-serif;">ðŸ–¨ï¸ Print / Save as PDF</button>
                    </div>
                    <div class="header">
                        <h1>Catering Report</h1>
                        <p>${coupleName} &mdash; Generated on ${now}</p>
                    </div>
                    <div class="summary">
                        <div class="summary-card"><div class="num">${totalGuests}</div><div class="label">Total Guests</div></div>
                        <div class="summary-card"><div class="num">${meals.length}</div><div class="label">Meal Options</div></div>
                        <div class="summary-card"><div class="num">${dietary.length}</div><div class="label">Dietary Notes</div></div>
                    </div>
                    <h2><span class="dot" style="background:#22c55e;"></span> Meal Selections</h2>
                    <table><thead><tr><th>Meal Choice</th><th style="text-align:center;">Quantity</th></tr></thead><tbody>${mealRows}
                    <tr style="background:#f0fdf4;"><td style="padding:10px 16px;font-size:14px;font-weight:700;color:#1e293b;">TOTAL</td><td style="padding:10px 16px;font-size:14px;font-weight:700;text-align:center;color:#1e293b;">${totalMealCount}</td></tr>
                    </tbody></table>
                    <h2><span class="dot" style="background:#f59e0b;"></span> Dietary Requirements & Allergies</h2>
                    <table><thead><tr><th>Guest Name</th><th>Dietary Notes / Allergies</th></tr></thead><tbody>${dietaryRows}</tbody></table>
                    <div class="footer">This document is auto-generated from ${this.settings.app_name || '<?= addslashes(htmlspecialchars(APP_NAME)) ?>'} &mdash; ${now}</div>
                    </body></html>`;

                    const w = window.open('', '_blank', 'width=800,height=600');
                    w.document.write(html);
                    w.document.close();
                },

                exportRsvpPDF() {
                    const coupleName = this.settings.couple_names || 'Wedding';
                    const now = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
                    const responded = this.respondedGuests;

                    let rows = '';
                    responded.forEach((g, idx) => {
                        const acc = g.accompanying_guests ? (typeof g.accompanying_guests === 'string' ? JSON.parse(g.accompanying_guests) : g.accompanying_guests) : [];
                        const events = g.events_attending ? (typeof g.events_attending === 'string' ? JSON.parse(g.events_attending) : g.events_attending) : [];
                        const statusColor = g.rsvp_status === 'attending' ? '#16a34a' : '#dc2626';
                        const statusLabel = g.rsvp_status === 'attending' ? 'Attending' : 'Declined';
                        const accNames = acc.filter(a => a.name).map(a => a.name).join(', ') || '-';

                        rows += `<tr style="${idx % 2 === 0 ? '' : 'background:#f8fafc;'}">
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:13px;font-weight:500;color:#1e293b;">${g.name}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;"><span style="background:${statusColor}15;color:${statusColor};padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">${statusLabel}</span></td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">${g.meal_choice || '-'}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#dc2626;">${g.dietary_notes || '-'}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">${events.length > 0 ? events.join(', ') : '-'}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">${g.accommodation || '-'}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">${g.song_request || '-'}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#475569;">${accNames}</td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:11px;color:#94a3b8;">${g.rsvp_responded_at || '-'}</td>
                        </tr>`;
                    });

                    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>RSVP Report - ${coupleName}</title>
                    <style>
                        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600&display=swap');
                        * { margin:0; padding:0; box-sizing:border-box; }
                        body { font-family:'Inter',sans-serif; color:#334155; padding:30px; background:#fff; }
                        .header { text-align:center; margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid #e2e8f0; }
                        .header h1 { font-family:'Playfair Display',serif; font-size:24px; color:#1e293b; margin-bottom:4px; }
                        .header p { font-size:12px; color:#64748b; }
                        .summary { display:flex; gap:12px; margin-bottom:24px; }
                        .summary-card { flex:1; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center; }
                        .summary-card .num { font-size:24px; font-weight:700; color:#1e293b; }
                        .summary-card .label { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-top:2px; }
                        table { width:100%; border-collapse:collapse; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; font-size:12px; }
                        thead th { background:#f8fafc; padding:10px 12px; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; font-weight:600; border-bottom:2px solid #e2e8f0; }
                        .footer { margin-top:30px; text-align:center; font-size:10px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:12px; }
                        @media print { body { padding:15px; font-size:10px; } .no-print { display:none; } }
                    </style></head><body>
                    <div class="no-print" style="text-align:center;margin-bottom:16px;">
                        <button onclick="window.print()" style="background:#4A6741;color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:Inter,sans-serif;">ðŸ–¨ï¸ Print / Save as PDF</button>
                    </div>
                    <div class="header">
                        <h1>RSVP Summary Report</h1>
                        <p>${coupleName} &mdash; Generated on ${now}</p>
                    </div>
                    <div class="summary">
                        <div class="summary-card"><div class="num">${this.stats.attending || 0}</div><div class="label">Attending</div></div>
                        <div class="summary-card"><div class="num">${this.stats.declined || 0}</div><div class="label">Declined</div></div>
                        <div class="summary-card"><div class="num">${this.stats.pending || 0}</div><div class="label">Pending</div></div>
                        <div class="summary-card"><div class="num">${this.stats.total_actual_attending || this.stats.attending || 0}</div><div class="label">Total Pax</div></div>
                    </div>
                    <table><thead><tr>
                        <th>Guest Name</th><th>Status</th><th>Meal</th><th>Dietary</th><th>Events</th><th>Hotel</th><th>Song</th><th>Accompanying</th><th>Responded</th>
                    </tr></thead><tbody>${rows || '<tr><td colspan="9" style="padding:20px;text-align:center;color:#94a3b8;">No responses yet.</td></tr>'}</tbody></table>
                    <div class="footer">Auto-generated from ${this.settings.app_name || '<?= addslashes(htmlspecialchars(APP_NAME)) ?>'} &mdash; ${now}</div>
                    </body></html>`;

                    const w = window.open('', '_blank', 'width=900,height=600');
                    w.document.write(html);
                    w.document.close();
                }
            }));
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/js/intlTelInput.min.js"></script>
    <script>
        // Global var to hold the ITI instance
        let itiAddGuest = null;
        // Init Lucide icons for static elements
        lucide.createIcons();
        window.renderLucide = () => { setTimeout(() => lucide.createIcons(), 50); };
    </script>
</body>
</html>
