<?php
/**
 * Create Admin — Halaman instalasi pertama untuk membuat akun Super Admin
 * Keamanan: Hanya bisa digunakan JIKA tabel clients masih kosong (0 pengguna).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

Auth::startSession();

$error = '';
$success = '';
$canCreate = false;

try {
    $db = new Database();
    $pdo = $db->pdo;
    
    // Cek apakah tabel clients ada dan berapa jumlah usernya
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        $canCreate = true;
    } else {
        $error = "Akses Ditolak: Sistem sudah memiliki pengguna terdaftar. Untuk alasan keamanan, fitur pembuatan Admin pertama telah dinonaktifkan. Silakan login ke Dashboard.";
    }
} catch (Exception $e) {
    $error = "Database belum disetup atau tabel tidak ditemukan. Silakan akses setup.php terlebih dahulu.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin']) && $canCreate) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Auth::verifyCsrfToken($csrf)) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $coupleNames = trim($_POST['couple_names'] ?? 'Super Admin');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password)) {
            $error = "Username, Email, dan Password wajib diisi.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                // Force is_admin = 1 untuk pengguna pertama ini
                $stmt = $pdo->prepare("INSERT INTO clients (username, password_hash, couple_names, email, is_admin) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$username, $hash, $coupleNames, $email]);
                
                $success = "Selamat! Akun Super Admin berhasil dibuat. Anda sekarang bisa login.";
                $canCreate = false; // Block form after success
            } catch (Exception $e) {
                $error = "Terjadi kesalahan saat membuat admin: " . $e->getMessage();
            }
        }
    }
}

$loginAppName = defined('APP_SHORT_NAME') ? APP_SHORT_NAME : 'Mail Marketing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin — <?= htmlspecialchars($loginAppName) ?></title>
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

        .card {
            background: #fff;
            border-radius: 16px;
            max-width: 450px;
            width: 100%;
            padding: 40px 36px;
            border: 1px solid #f0eeeb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo .icon-wrap {
            width: 48px; height: 48px;
            background: #f5f3f0;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            color: #4A6741;
        }
        .logo h1 {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
        }
        .logo p {
            font-size: 13px;
            color: #a3a3a3;
            margin-top: 6px;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #737373;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s;
        }
        .form-group input:focus { border-color: #4A6741; }

        .btn-submit {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #4A6741;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-submit:hover { background: #3d5636; }

        .error-msg {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .success-msg {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .back-link a {
            color: #4A6741;
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="icon-wrap"><i data-lucide="shield-check"></i></div>
        <h1>Setup Admin Pertama</h1>
        <p>Buat akun pemilik / Super Admin</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($canCreate): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCsrfToken(); ?>">
        
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Contoh: admin_utama">
        </div>
        
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required placeholder="admin@example.com">
        </div>
        
        <div class="form-group">
            <label>Nama Pengguna / Bisnis</label>
            <input type="text" name="couple_names" required placeholder="Contoh: My Wedding Planner">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Buat password yang kuat">
        </div>

        <button type="submit" name="create_admin" class="btn-submit">Buat Akun Admin</button>
    </form>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="login.php">Pergi ke Halaman Login</a>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
