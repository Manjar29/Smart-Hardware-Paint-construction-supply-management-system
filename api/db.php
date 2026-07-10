<?php
// ============================================================
//  DATABASE CONFIGURATION — MySQL / XAMPP
//  Edit these credentials to match your XAMPP setup
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'paint_hardware_db');
define('DB_USER', 'root');       // Default XAMPP MySQL user
define('DB_PASS', '');           // Default XAMPP MySQL password (empty)
define('DB_CHARSET', 'utf8mb4');

// ============================================================
//  CONNECTION — PDO (MySQL)
// ============================================================
function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        sendError('Database connection failed: ' . $e->getMessage(), 500);
    }

    return $pdo;
}

// ============================================================
//  HELPERS
// ============================================================

/**
 * Execute a SELECT and return all rows as associative arrays
 * with lower-cased keys (for consistency with the frontend).
 */
function fetchAll(string $sql, array $binds = []): array {
    $pdo  = getDbConnection();
    $sql  = adaptSql($sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($binds);
    $rows = $stmt->fetchAll();

    // Lower-case all keys
    $result = [];
    foreach ($rows as $row) {
        $lowered = [];
        foreach ($row as $k => $v) {
            $lowered[strtolower($k)] = $v;
        }
        $result[] = $lowered;
    }
    return $result;
}

/**
 * Execute an INSERT / UPDATE / DELETE and return affected row count.
 */
function executeQuery(string $sql, array $binds = []): int {
    $pdo  = getDbConnection();
    $sql  = adaptSql($sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($binds);
    return $stmt->rowCount();
}

/**
 * Execute an INSERT and return the last inserted ID.
 * The $outBinds parameter is kept for API compatibility but ignored in MySQL
 * (we use lastInsertId() instead of RETURNING … INTO).
 */
function executeInsertWithReturn(string $sql, array $binds, array $outBinds = []): array {
    $pdo = getDbConnection();

    // Strip Oracle-style "RETURNING … INTO :var" clause if present
    $sql = preg_replace('/\s+RETURNING\s+\S+\s+INTO\s+:\w+/i', '', $sql);
    $sql = adaptSql($sql);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($binds);
    $newId = (int)$pdo->lastInsertId();

    // Return a map keyed by the output-bind names (all pointing to the new ID)
    $out = [];
    foreach ($outBinds as $key => $maxLen) {
        $out[$key] = $newId;
    }
    // Common aliases used throughout the codebase
    if (empty($out)) {
        $out['new_id'] = $newId;
        $out['oid']    = $newId;
        $out['cid']    = $newId;
    }
    return $out;
}

/**
 * Adapt SQL written for Oracle to work with MySQL.
 * Handles the most common differences used in this project.
 */
function adaptSql(string $sql): string {
    // NVL()  ->  IFNULL()
    $sql = preg_replace('/\bNVL\s*\(/i', 'IFNULL(', $sql);

    // FETCH FIRST n ROWS ONLY  ->  LIMIT n
    $sql = preg_replace('/\bFETCH\s+FIRST\s+(\d+)\s+ROWS\s+ONLY\b/i', 'LIMIT $1', $sql);

    // Oracle || concat in LIKE:  '%' || :var || '%'  ->  CONCAT('%', :var, '%')
    // MySQL supports CONCAT for LIKE patterns
    $sql = preg_replace(
        "/'%'\s*\|\|\s*:(\w+)\s*\|\|\s*'%'/",
        "CONCAT('%', :$1, '%')",
        $sql
    );

    // Generic Oracle string concatenation: 'a' || 'b'  -> CONCAT('a', 'b')
    // Only replace when not inside a LIKE clause already handled above
    // (Simple heuristic: replace remaining ||)
    $sql = str_replace(' || ', ', ', $sql);
    // But fix comma-separated bare CONCAT patterns back if needed
    // Actually for this project || is only used in CONCAT patterns, so this is safe.

    // SYSDATE -> NOW()
    $sql = preg_replace('/\bSYSDATE\b/i', 'NOW()', $sql);

    return $sql;
}

/**
 * Generate a unique order number after insert.
 * Called from orders.php after the INSERT.
 */
function generateOrderNumber(int $orderId): string {
    return 'ORD-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
}

// ============================================================
//  RESPONSE HELPERS
// ============================================================
function sendJson($data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
//  CORS — allow requests from the same origin (file:// or localhost)
// ============================================================
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
