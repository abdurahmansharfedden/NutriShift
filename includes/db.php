<?php
// db.php - Database Connection File
// =================================================================

// 1. Load the secret constants from config.php
require_once __DIR__ . '/../config.php'; 

// 2. Set the connection options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// 3. Connect
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log this to a file instead of echoing
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection failed. Check logs.");
}