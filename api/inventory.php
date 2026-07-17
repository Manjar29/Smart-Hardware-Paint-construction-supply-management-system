<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    // ---- Top Revenue Products (last 30 days) ----
    if (isset($_GET['top_revenue'])) {
        $sql = "SELECT p.product_id, p.product_name, p.sku,
                       c.category_name AS category,
                       SUM(oi.quantity) AS total_qty_sold,
                       SUM(oi.line_total) AS total_revenue
                FROM order_items oi
                JOIN products p  ON oi.product_id = p.product_id
                JOIN categories c ON p.category_id = c.category_id
                JOIN orders o    ON oi.order_id = o.order_id
                WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND o.order_status NOT IN ('Cancelled')
                GROUP BY p.product_id, p.product_name, p.sku, c.category_name
                ORDER BY total_revenue DESC
                LIMIT 10";
        sendJson(fetchAll($sql));
    }

    // ---- Inventory Logs ----
    if (isset($_GET['logs'])) {
        $sql = "SELECT l.log_id, l.movement_date, p.product_name, p.sku,
                       l.movement_type, l.quantity,
                       l.reference_type, l.reference_id, l.notes
                FROM inventory_logs l
                JOIN products p ON l.product_id = p.product_id
                ORDER BY l.movement_date DESC, l.log_id DESC
                LIMIT 50";
        sendJson(fetchAll($sql));
    }

    // ---- Stock overview ----
    $sql = "SELECT p.product_id, p.product_name, p.sku,
                   p.stock_qty AS stock, p.safety_level, p.unit_price,
                   c.category_name AS category,
                   IF(p.stock_qty < p.safety_level, 1, 0) AS is_low_stock
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            ORDER BY p.product_name ASC";
    sendJson(fetchAll($sql));

} elseif ($method === 'POST') {
    // Manual stock-IN (admin adds stock)
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['product_id']) || !isset($data['quantity']) || (int)$data['quantity'] <= 0) {
        sendError("product_id and a positive quantity are required");
    }

    $productId = (int)$data['product_id'];
    $qty       = (int)$data['quantity'];
    $refType   = isset($data['reference_type']) ? $data['reference_type'] : 'Admin Restock';
    $refId     = isset($data['reference_id'])   ? $data['reference_id']   : null;
    $notes     = isset($data['notes'])           ? $data['notes']          : 'Manual stock addition by admin';

    $affected = executeQuery(
        "UPDATE products SET stock_qty = stock_qty + :qty WHERE product_id = :pid",
        ['qty' => $qty, 'pid' => $productId]
    );

    if ($affected === 0) {
        sendError("Product not found", 404);
    }

    executeQuery(
        "INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes)
         VALUES (:pid, 'IN', :qty, :rtype, :rid, :notes)",
        ['pid' => $productId, 'qty' => $qty, 'rtype' => $refType, 'rid' => $refId, 'notes' => $notes]
    );

    // Return updated stock
    $updated = fetchAll("SELECT stock_qty FROM products WHERE product_id = :pid", ['pid' => $productId]);
    sendJson([
        'success'       => true,
        'message'       => "Stock updated. Added {$qty} unit(s).",
        'new_stock_qty' => $updated[0]['stock_qty'] ?? 0
    ]);

} else {
    sendError("Method not allowed", 405);
}
