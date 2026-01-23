<?php
// backend_php/db.php

// DEBUG MODE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Hardcoded for now based on typical setup and user context
        // Ideally should read from .env if we had a parser
        $host = '127.0.0.1';
        $db = 'main';
        $user = 'root';
        $pass = 'root'; // Updated to match local config
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            // Check if it's a "database not found" error
            try {
                // Try connecting without DB and check
                $dsnNoDB = "mysql:host=$host;charset=$charset";
                $pdoCheck = new PDO($dsnNoDB, $user, $pass, $options);
                // If this works but previous failed, DB is missing.
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            } catch (\PDOException $e2) {
                 die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}
