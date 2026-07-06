<?php
// Configuration (Replace with your actual Oracle DB details)
define('DB_USER', 'your_user');
define('DB_PASS', 'your_pass');
define('DB_HOST_PORT_SID', 'localhost:1521/XEPDB1');

$USE_SQLITE = !extension_loaded('oci8');
$SQLITE_FILE = __DIR__ . '/database.sqlite';

function initSqliteIfNeeded() {
    global $SQLITE_FILE;
    if (!file_exists($SQLITE_FILE)) {
        $db = new PDO('sqlite:' . $SQLITE_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $schema = "
        CREATE TABLE categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_name TEXT UNIQUE NOT NULL
        );
        CREATE TABLE products (
            product_id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku TEXT UNIQUE NOT NULL,
            product_name TEXT NOT NULL,
            category_id INTEGER NOT NULL,
            brand TEXT,
            unit_price REAL NOT NULL,
            stock_qty INTEGER DEFAULT 0,
            safety_level INTEGER DEFAULT 10,
            status TEXT DEFAULT 'Active'
        );
        CREATE TABLE customers (
            customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            phone TEXT UNIQUE NOT NULL,
            address TEXT,
            customer_type TEXT DEFAULT 'Retail'
        );
        CREATE TABLE orders (
            order_id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            order_number TEXT DEFAULT 'ORD-1000',
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_amount REAL DEFAULT 0,
            order_status TEXT DEFAULT 'Placed'
        );
        CREATE TABLE order_items (
            order_item_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            shade_id INTEGER,
            quantity INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            custom_shade_name TEXT,
            line_total REAL DEFAULT 0
        );
        CREATE TABLE paint_shades (
            shade_id INTEGER PRIMARY KEY AUTOINCREMENT,
            shade_code TEXT UNIQUE NOT NULL,
            shade_name TEXT NOT NULL,
            formula_code TEXT,
            category TEXT NOT NULL,
            rgb_value TEXT,
            mix_formula TEXT,
            finish_type TEXT
        );
        CREATE TABLE inventory_logs (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER,
            movement_type TEXT,
            quantity INTEGER,
            movement_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT
        );
        
        INSERT INTO categories (category_id, category_name) VALUES (1, 'Paints'), (2, 'Hardware'), (3, 'Tools'), (4, 'Iron & Steel'), (5, 'Construction');
        INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level) VALUES 
        ('PNT-001', 'Premium Emulsion Paint (1L)', 1, 'Berger', 1250, 24, 10),
        ('PNT-002', 'Weathercoat Shield (4L)', 1, 'Berger', 3400, 15, 5),
        ('PNT-045', 'Gloss Wood Polish (500ml)', 1, 'Asian Paints', 320, 8, 10),
        ('HDW-011', 'Galvanized Nails (1kg)', 2, 'RFL', 180, 120, 50),
        ('HDW-012', 'Premium Screws Pack (100pc)', 2, 'RFL', 120, 250, 50),
        ('HDW-089', 'Drill Bit Set (13pcs)', 3, 'Bosch', 280, 15, 5),
        ('TL-001', 'Heavy Duty Steel Hammer', 3, 'Bosch', 350, 35, 10),
        ('IRN-210', 'BSRM Iron Rod 10mm (6m)', 4, 'BSRM', 450, 42, 20),
        ('IRN-211', 'Steel Plate 4x8 ft', 4, 'BSRM', 2800, 10, 5),
        ('CST-001', 'Ready Mix Concrete (25kg)', 5, 'Lafarge', 850, 50, 20),
        ('CST-002', 'Lafarge Cement (50kg)', 5, 'Lafarge', 650, 0, 20);
        
        INSERT INTO paint_shades (shade_code, shade_name, category, rgb_value, mix_formula, finish_type) VALUES 
        ('CB-001', 'Crimson Blaze', 'Acrylic', 'rgb(244,63,94)', 'Red 40% + Yellow 15% + White 45%', 'Matte'),
        ('SG-002', 'Sunset Gold', 'Acrylic', 'rgb(245,158,11)', 'Yellow 60% + Red 15% + White 25%', 'Semi-Gloss'),
        ('ML-003', 'Mint Leaf', 'Acrylic', 'rgb(16,185,129)', 'Green 50% + Yellow 20% + White 30%', 'Satin');
        
        INSERT INTO inventory_logs (product_id, movement_type, quantity, notes) VALUES 
        (1, 'IN', 30, 'Supplier restocking'),
        (1, 'OUT', 6, 'Customer order'),
        (4, 'IN', 50, 'Wholesale shipment');
        ";
        $db->exec($schema);
    }
}

/**
 * Get DB connection (Oracle or PDO SQLite)
 */
function getDbConnection() {
    global $USE_SQLITE, $SQLITE_FILE;
    if ($USE_SQLITE) {
        initSqliteIfNeeded();
        $db = new PDO('sqlite:' . $SQLITE_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } else {
        $conn = @oci_connect(DB_USER, DB_PASS, DB_HOST_PORT_SID);
        if (!$conn) {
            $e = oci_error();
            sendError("Connection failed: " . $e['message'], 500);
        }
        return $conn;
    }
}

function processSqlForSqlite($sql) {
    // Replace NVL with IFNULL
    $sql = str_ireplace('NVL(', 'IFNULL(', $sql);
    // Remove FETCH FIRST n ROWS ONLY
    $sql = preg_replace('/FETCH\s+FIRST\s+(\d+)\s+ROWS\s+ONLY/i', 'LIMIT $1', $sql);
    // Remove || concatenations for LIKE
    $sql = str_replace("'%' || :cat || '%'", "('%' || :cat || '%')", $sql);
    $sql = str_replace("'%' || :search || '%'", "('%' || :search || '%')", $sql);
    return $sql;
}

function fetchAll($sql, $binds = []) {
    global $USE_SQLITE;
    $conn = getDbConnection();
    
    if ($USE_SQLITE) {
        $sql = processSqlForSqlite($sql);
        $stmt = $conn->prepare($sql);
        $stmt->execute($binds);
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $loweredRow = [];
            foreach($row as $k => $v) $loweredRow[strtolower($k)] = $v;
            $results[] = $loweredRow;
        }
        return $results;
    } else {
        $stid = oci_parse($conn, $sql);
        if (!$stid) { $e = oci_error($conn); sendError("Parse error: " . $e['message'], 500); }
        foreach ($binds as $key => $val) {
            oci_bind_by_name($stid, $key, $binds[$key]);
        }
        $r = oci_execute($stid);
        if (!$r) { $e = oci_error($stid); sendError("Execution error: " . $e['message'], 500); }
        $results = [];
        while ($row = oci_fetch_assoc($stid)) {
            $loweredRow = [];
            foreach($row as $k => $v) $loweredRow[strtolower($k)] = $v;
            $results[] = $loweredRow;
        }
        oci_free_statement($stid);
        oci_close($conn);
        return $results;
    }
}

function executeQuery($sql, $binds = []) {
    global $USE_SQLITE;
    $conn = getDbConnection();
    if ($USE_SQLITE) {
        $sql = processSqlForSqlite($sql);
        $stmt = $conn->prepare($sql);
        $stmt->execute($binds);
        return $stmt->rowCount();
    } else {
        $stid = oci_parse($conn, $sql);
        if (!$stid) { $e = oci_error($conn); sendError("Parse error: " . $e['message'], 500); }
        foreach ($binds as $key => $val) { oci_bind_by_name($stid, $key, $binds[$key]); }
        $r = oci_execute($stid);
        if (!$r) { $e = oci_error($stid); sendError("Execution error: " . $e['message'], 500); }
        $rowsAffected = oci_num_rows($stid);
        oci_free_statement($stid);
        oci_close($conn);
        return $rowsAffected;
    }
}

function executeInsertWithReturn($sql, $binds, $outBinds) {
    global $USE_SQLITE;
    $conn = getDbConnection();
    
    if ($USE_SQLITE) {
        // Strip out 'RETURNING id INTO :id'
        $sql = preg_replace('/RETURNING\s+\w+\s+INTO\s+:\w+/i', '', $sql);
        $stmt = $conn->prepare($sql);
        $stmt->execute($binds);
        $id = $conn->lastInsertId();
        
        $outVars = [];
        foreach ($outBinds as $key => $maxLen) {
            $outVars[$key] = $id; 
        }
        return $outVars;
    } else {
        $stid = oci_parse($conn, $sql);
        if (!$stid) { $e = oci_error($conn); sendError("Parse error: " . $e['message'], 500); }
        foreach ($binds as $key => $val) { oci_bind_by_name($stid, $key, $binds[$key]); }
        $outVars = [];
        foreach ($outBinds as $key => $maxLen) {
            $outVars[$key] = null;
            oci_bind_by_name($stid, $key, $outVars[$key], $maxLen);
        }
        $r = oci_execute($stid);
        if (!$r) { $e = oci_error($stid); sendError("Execution error: " . $e['message'], 500); }
        oci_free_statement($stid);
        oci_close($conn);
        return $outVars;
    }
}

function sendJson($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
