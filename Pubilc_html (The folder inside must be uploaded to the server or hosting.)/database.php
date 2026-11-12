<?php
require_once __DIR__ . '/config.php';

class Database
{
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    public $pdo;

    public function __construct()
    {
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, $this->user, $this->pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        catch (PDOException $e) {
            if ($e->getCode() == 1049) {
                $this->createDatabase();
            }
            else {
                error_log("DB Connection Failed: " . $e->getMessage());
                die("A database error occurred: " . $e->getMessage() . " <br><br><b>Perhatikan:</b> Pastikan di cPanel Anda sudah mengklik 'Add User To Database' dan mencentang 'ALL PRIVILEGES'.");
            }
        }
    }

    private function createDatabase()
    {
        try {
            $dsn = "mysql:host=" . $this->host . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $this->user, $this->pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $this->dbname . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, $this->user, $this->pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        catch (PDOException $e) {
            error_log("DB Create Failed: " . $e->getMessage());
            die("A database error occurred during creation: " . $e->getMessage());
        }
    }
}
