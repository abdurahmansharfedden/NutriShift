<?php
// =============================================================================
// db.php — Database Connection File
// =============================================================================
// TEACHING NOTE: We define DB credentials as constants (define()) so they
// cannot be accidentally overwritten anywhere else in the program.
// Keep this file outside your web root in a real production project!
// =============================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'nutrishift');
define('DB_USER', 'ns_app');        // ← Change to your MySQL username
define('DB_PASS', 'collegeproject123');            // ← Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// TEACHING NOTE: PDO (PHP Data Objects) is a database abstraction layer.
// It lets us use "prepared statements" which protect against SQL Injection —
// the #1 web security vulnerability. The DSN (Data Source Name) string tells
// PDO which driver and database to connect to.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// TEACHING NOTE: These PDO options configure its behavior:
// - ERRMODE_EXCEPTION  → Throw a PHP Exception when a DB error occurs (catchable).
// - FETCH_ASSOC        → Return rows as associative arrays (e.g. $row['email']).
// - EMULATE_PREPARES   → Disable emulation so the DB engine does real preparation.
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// TEACHING NOTE: We wrap the connection in a try/catch block.
// If the connection fails (wrong password, DB not running, etc.),
// PHP throws a PDOException. We catch it here and show a safe error
// message — we don't reveal the raw error to the browser (security!).
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log $e->getMessage() to a file instead of displaying it.
    error_log('DB Connection Error: ' . $e->getMessage());
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed. Please try again later.']));
}
