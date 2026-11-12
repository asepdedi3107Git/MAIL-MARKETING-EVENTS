<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class Auth
{
    private static $db = null;

    private static function getDB()
    {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->pdo;
        }
        return self::$db;
    }

    /**
     * Start session if not already started
     */
    public static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'httponly' => true,
                'secure' => $isHttps,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    /**
     * Register a new client account
     */
    public static function register($username, $password, $coupleNames, $email)
    {
        $pdo = self::getDB();

        // Cek username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['status' => false, 'message' => 'Username already exists'];
        }

        // Cek email sudah ada
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['status' => false, 'message' => 'Email already registered'];
        }

        // Hash password
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Default RSVP fields config
        $defaultRsvpFields = json_encode([
            'meal_choice' => true,
            'dietary_notes' => true,
            'plus_one' => true,
            'song_request' => true,
            'table_number' => false,
            'phone' => false,
            'notes' => false
        ]);

        // Default meal options
        $defaultMealOptions = json_encode([
            'Beef Tenderloin with Red Wine Jus',
            'Pan-Seared Salmon with Lemon Butter',
            'Wild Mushroom Risotto (Vegetarian)',
            'Grilled Chicken with Rosemary (Gluten-Free)'
        ]);

        $stmt = $pdo->prepare("INSERT INTO clients (username, password_hash, couple_names, email, rsvp_fields, meal_options) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $coupleNames, $email, $defaultRsvpFields, $defaultMealOptions]);

        return ['status' => true, 'message' => 'Registration successful', 'client_id' => $pdo->lastInsertId()];
    }

    /**
     * Login client
     */
    public static function login($username, $password)
    {
        self::startSession();
        $pdo = self::getDB();

        $stmt = $pdo->prepare("SELECT * FROM clients WHERE username = ?");
        $stmt->execute([$username]);
        $client = $stmt->fetch();

        if (!$client) {
            return ['status' => false, 'message' => 'Invalid username or password'];
        }

        if (!password_verify($password, $client['password_hash'])) {
            return ['status' => false, 'message' => 'Invalid username or password'];
        }

        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client_username'] = $client['username'];
        $_SESSION['client_name'] = $client['couple_names'];
        $_SESSION['is_admin'] = (bool)$client['is_admin'];

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        return ['status' => true, 'message' => 'Login successful', 'client' => $client];
    }

    /**
     * Check if client is logged in, returns client data or false
     */
    public static function check()
    {
        self::startSession();

        if (!isset($_SESSION['client_id'])) {
            return false;
        }

        $pdo = self::getDB();
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$_SESSION['client_id']]);
        $client = $stmt->fetch();

        if (!$client) {
            self::logout();
            return false;
        }

        return $client;
    }

    /**
     * Get current client ID
     */
    public static function getClientId()
    {
        self::startSession();
        return $_SESSION['client_id'] ?? null;
    }

    /**
     * Logout
     */
    public static function logout()
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Change password
     */
    public static function changePassword($clientId, $oldPassword, $newPassword)
    {
        $pdo = self::getDB();

        $stmt = $pdo->prepare("SELECT password_hash FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        if (!$client || !password_verify($oldPassword, $client['password_hash'])) {
            return ['status' => false, 'message' => 'Current password is incorrect'];
        }

        if (strlen($newPassword) < 8) {
            return ['status' => false, 'message' => 'New password must be at least 8 characters'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $clientId]);

        return ['status' => true, 'message' => 'Password updated successfully'];
    }

    /**
     * Require authentication - redirect to login if not logged in
     */
    public static function requireLogin()
    {
        $client = self::check();
        if (!$client) {
            header("Location: login.php");
            exit;
        }
        return $client;
    }

    /**
     * Require Super Admin - block access if not admin
     */
    public static function requireAdmin()
    {
        $client = self::requireLogin();
        if (!$client['is_admin']) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                http_response_code(403);
                die(json_encode(['status' => false, 'message' => 'Forbidden: Super Admin access required']));
            }
            else {
                die("<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p><a href='dashboard.php'>Return to Dashboard</a>");
            }
        }
        return $client;
    }

    /**
     * CSRF Token Generation
     */
    public static function generateCsrfToken()
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF Token Verification
     */
    public static function verifyCsrfToken($token)
    {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Login as Receptionist (username + PIN)
     */
    public static function loginReceptionist($username, $pin)
    {
        self::startSession();
        $pdo = self::getDB();

        $stmt = $pdo->prepare("SELECT r.*, c.couple_names, c.color_primary FROM receptionists r JOIN clients c ON r.client_id = c.id WHERE r.username = ? AND r.is_active = 1");
        $stmt->execute([$username]);
        $receptionist = $stmt->fetch();

        if (!$receptionist) {
            return ['status' => false, 'message' => 'Invalid username or PIN'];
        }

        if (!password_verify($pin, $receptionist['pin_hash'])) {
            return ['status' => false, 'message' => 'Invalid username or PIN'];
        }

        $_SESSION['client_id'] = $receptionist['client_id'];
        $_SESSION['receptionist_id'] = $receptionist['id'];
        $_SESSION['receptionist_name'] = $receptionist['name'];
        $_SESSION['is_receptionist'] = true;

        session_regenerate_id(true);

        return ['status' => true, 'message' => 'Login successful', 'receptionist' => $receptionist];
    }

    /**
     * Check if current session is a receptionist
     */
    public static function checkReceptionist()
    {
        self::startSession();

        if (empty($_SESSION['is_receptionist']) || empty($_SESSION['receptionist_id'])) {
            return false;
        }

        $pdo = self::getDB();
        $stmt = $pdo->prepare("SELECT r.*, c.couple_names, c.color_primary, c.event_date, c.event_time, c.venue_name FROM receptionists r JOIN clients c ON r.client_id = c.id WHERE r.id = ? AND r.is_active = 1");
        $stmt->execute([$_SESSION['receptionist_id']]);
        $receptionist = $stmt->fetch();

        if (!$receptionist) {
            self::logout();
            return false;
        }

        return $receptionist;
    }
}
