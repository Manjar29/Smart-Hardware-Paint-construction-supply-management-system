<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['logs'])) {
        // Return inventory movement logs
        $sql = "SELECT l.log_id, l.movement_date, p.product_name, p.sku, l.movement_type, 
                       l.quantity, l.reference_type, l.reference_id, l.notes
                FROM inventory_logs l
                JOIN products p ON l.product_id = p.product_id
                ORDER BY l.movement_date DESC, l.log_id DESC";
        $logs = fetchAll($sql);
        sendJson($logs);
    } else {
        // Return stock overview
        $sql = "SELECT product_id, product_name, sku, stock_qty as stock, safety_level
                FROM products
                ORDER BY product_name ASC";
        $inventory = fetchAll($sql);
        sendJson($inventory);
    }
} else {
    sendError("Method not allowed", 405);
}
