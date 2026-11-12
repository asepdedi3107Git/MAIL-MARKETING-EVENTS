<?php
/**
 * Login & Register — Halaman autentikasi klien
 */
require_once __DIR__ . '/auth.php';

Auth::startSession();

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    header("Location: login.php");
    exit;
}

// Redirect jika sudah login
if (Auth::checkReceptionist()) {
    header("Location: receptionist.php");
    exit;
}
if (Auth::check()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Basic Anti-Brute Force via Session (Delay after 3 fails)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_login_attempt'])) {
    $_SESSION['last_login_attempt'] = 0;
}

// Handle Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Auth::verifyCsrfToken($csrf)) {
        $error = "Invalid security token. Please refresh and try again.";
    }
    // Rate Limiting Check
    elseif ($_SESSION['login_attempts'] >= 3 && (time() - $_SESSION['last_login_attempt']) < 30) {
        $error = "Too many failed attempts. Please wait 30 seconds.";
    }
    else {
        // Process Login
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = Auth::login($username, $password);

        if ($result['status'] === true) {
            // Reset attempts on success
            $_SESSION['login_attempts'] = 0;
            header("Location: dashboard.php");
            exit;
        }
        else {
            $error = $result['message'];
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
        }
    }
}

// Handle Receptionist Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receptionist_login'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Auth::verifyCsrfToken($csrf)) {
        $error = "Invalid security token. Please refresh and try again.";
    }
    elseif ($_SESSION['login_attempts'] >= 3 && (time() - $_SESSION['last_login_attempt']) < 30) {
        $error = "Too many failed attempts. Please wait 30 seconds.";
    }
    else {
        $username = trim($_POST['username'] ?? '');
        $pin = $_POST['pin'] ?? '';
        $result = Auth::loginReceptionist($username, $pin);
        if ($result['status'] === true) {
            $_SESSION['login_attempts'] = 0;
            header("Location: receptionist.php");
            exit;
        }
        else {
            $error = $result['message'];
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
        }
    }
}

$activeTab = $_GET['tab'] ?? 'admin';

// Fetch white-label app name from DB
$loginAppName = APP_SHORT_NAME; // fallback to config default
try {
    $db = new Database();
    $stmtApp = $db->pdo->query("SELECT app_name FROM clients WHERE app_name IS NOT NULL AND app_name != '' LIMIT 1");
    $rowApp = $stmtApp->fetch();
    if ($rowApp && !empty($rowApp['app_name'])) {
        $loginAppName = $rowApp['app_name'];
    }
} catch (Exception $e) {
    // silently use default
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafaf9;
            padding: 20px;
        }

        .login-card {
            background: #fff;
            border-radius: 16px;
            max-width: 400px;
            width: 100%;
            padding: 48px 36px;
            border: 1px solid #f0eeeb;
        }

        .logo {
            text-align: center;
            margin-bottom: 36px;
        }
        .logo .icon-wrap {
            width: 48px; height: 48px;
            background: #f5f3f0;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            color: #4A6741;
        }
        .logo .icon-wrap svg { width: 22px; height: 22px; }
        .logo h1 {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        .logo p {
            font-size: 13px;
            color: #a3a3a3;
            margin-top: 6px;
            font-weight: 400;
        }

        .tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 28px;
            border-bottom: 1px solid #f0eeeb;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 10px 0;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            color: #a3a3a3;
            border-bottom: 2px solid transparent;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab svg { width: 15px; height: 15px; }
        .tab:hover { color: #525252; }
        .tab.active {
            color: #1a1a1a;
            border-bottom-color: #4A6741;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #737373;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #262626;
            transition: border-color 0.15s ease;
            outline: none;
            background: #fff;
        }
        .form-group input::placeholder { color: #d4d4d4; }
        .form-group input:focus {
            border-color: #4A6741;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #4A6741;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.15s ease;
            margin-top: 4px;
        }
        .btn-submit:hover {
            background: #3d5636;
        }
        .btn-submit.teal {
            background: #0f766e;
        }
        .btn-submit.teal:hover {
            background: #0d5f58;
        }

        .error-msg {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .error-msg svg { width: 16px; height: 16px; flex-shrink: 0; }

        .helper-text {
            text-align: center;
            font-size: 12px;
            color: #a3a3a3;
            margin-top: 20px;
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .login-card { padding: 36px 24px; }
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">
        <div class="icon-wrap"><i data-lucide="heart"></i></div>
        <h1><?= htmlspecialchars($loginAppName) ?></h1>
        <p>Manage your event invitations</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><i data-lucide="alert-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php
endif; ?>

    <div class="tabs">
        <a href="?tab=admin" class="tab <?php echo $activeTab === 'admin' ? 'active' : ''; ?>"><i data-lucide="user"></i> Admin</a>
        <a href="?tab=receptionist" class="tab <?php echo $activeTab === 'receptionist' ? 'active' : ''; ?>"><i data-lucide="scan-line"></i> Receptionist</a>
    </div>

    <?php if ($activeTab === 'admin'): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCsrfToken(); ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Enter your username" autocomplete="username">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
        </div>
        <button type="submit" name="login" class="btn-submit">Sign In</button>
    </form>
    <?php
else: ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCsrfToken(); ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Receptionist username" autocomplete="username">
        </div>
        <div class="form-group">
            <label>PIN</label>
            <input type="password" name="pin" required placeholder="4-6 digit PIN" autocomplete="current-password" inputmode="numeric" maxlength="6" pattern="[0-9]{4,6}">
        </div>
        <button type="submit" name="receptionist_login" class="btn-submit teal">Sign In as Receptionist</button>
    </form>
    <p class="helper-text">Ask your event organiser for your<br>login credentials.</p>
    <?php
endif; ?>
</div>
<script>lucide.createIcons();</script>
</body>
</html>

