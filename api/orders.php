<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ================================================================
//  PATCH — Admin: Update order status (Accept / Deliver / Reject)
// ================================================================
if ($method === 'PATCH') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['order_id']) || !isset($data['status'])) {
        sendError("order_id and status are required");
    }

    $orderId  = (int)$data['order_id'];
    $status   = $data['status'];
    $feedback = isset($data['feedback']) ? trim($data['feedback']) : null;

    $allowed = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($status, $allowed)) {
        sendError("Invalid status. Must be: " . implode(', ', $allowed));
    }

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // Get current order status
        $current = fetchAll("SELECT order_status FROM orders WHERE order_id = :oid", ['oid' => $orderId]);
        if (empty($current)) sendError("Order not found", 404);

        $currentStatus = $current[0]['order_status'];

        // If accepting (Processing) AND previously was Placed — deduct stock
        if ($status === 'Processing' && $currentStatus === 'Placed') {
            $items = fetchAll(
                "SELECT product_id, quantity FROM order_items WHERE order_id = :oid",
                ['oid' => $orderId]
            );

            $orderRow = fetchAll("SELECT order_number FROM orders WHERE order_id = :oid", ['oid' => $orderId]);
            $orderNum = $orderRow[0]['order_number'] ?? 'N/A';

            foreach ($items as $item) {
                // Check available stock
                $prod = fetchAll("SELECT stock_qty FROM products WHERE product_id = :pid", ['pid' => $item['product_id']]);
                if (!empty($prod) && $prod[0]['stock_qty'] >= $item['quantity']) {
                    // Deduct stock
                    $pdo->prepare("UPDATE products SET stock_qty = stock_qty - :qty WHERE product_id = :pid")
                        ->execute(['qty' => $item['quantity'], 'pid' => $item['product_id']]);

                    // Log inventory movement
                    $pdo->prepare(
                        "INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes)
                         VALUES (:pid, 'OUT', :qty, 'Admin Accepted', :ref, 'Stock deducted on order acceptance')"
                    )->execute(['pid' => $item['product_id'], 'qty' => $item['quantity'], 'ref' => $orderNum]);
                }
            }
        }

        // Update order status + feedback
        $pdo->prepare(
            "UPDATE orders SET order_status = :status, admin_feedback = :feedback WHERE order_id = :oid"
        )->execute(['status' => $status, 'feedback' => $feedback, 'oid' => $orderId]);

        $pdo->commit();
        sendJson(['success' => true, 'order_id' => $orderId, 'new_status' => $status]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        sendError('Action failed: ' . $e->getMessage(), 500);
    }
}

// ================================================================
//  GET — Routing
// ================================================================
if ($method === 'GET') {

    // ---- Customer: Track by order number ----
    if (isset($_GET['order_number'])) {
        $onum = trim($_GET['order_number']);

        $order = fetchAll(
            "SELECT o.order_id, o.order_number, o.order_date, o.total_amount,
                    o.order_status, o.admin_feedback,
                    c.full_name AS customer_name, c.phone, c.address
             FROM orders o
             JOIN customers c ON o.customer_id = c.customer_id
             WHERE o.order_number = :onum",
            ['onum' => $onum]
        );

        if (empty($order)) {
            sendError("Order not found. Please check your Order ID.", 404);
        }

        $orderId = $order[0]['order_id'];
        $items = fetchAll(
            "SELECT oi.quantity, oi.unit_price, oi.line_total, oi.custom_shade_name,
                    p.product_name, p.sku,
                    ps.shade_name
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             LEFT JOIN paint_shades ps ON oi.shade_id = ps.shade_id
             WHERE oi.order_id = :oid",
            ['oid' => $orderId]
        );

        sendJson(['order' => $order[0], 'items' => $items]);
    }

    // ---- Admin: Search by customer name ----
    if (isset($_GET['admin']) && isset($_GET['customer'])) {
        $name = trim($_GET['customer']);
        $rows = fetchAll(
            "SELECT o.order_id, o.order_number, o.order_date, o.total_amount,
                    o.order_status, o.admin_feedback,
                    c.full_name AS customer_name, c.phone, c.address, c.customer_type
             FROM orders o
             JOIN customers c ON o.customer_id = c.customer_id
             WHERE c.full_name LIKE CONCAT('%', :name, '%')
             ORDER BY o.order_date ASC",
            ['name' => $name]
        );
        sendJson($rows);
    }

    // ---- Admin: Filter orders by category + time ----
    if (isset($_GET['admin']) && (isset($_GET['days']) || isset($_GET['category']))) {
        $days     = isset($_GET['days'])     ? (int)$_GET['days']     : 0;
        $category = isset($_GET['category']) ? trim($_GET['category']) : '';

        $binds = [];
        $where = [];

        if ($days > 0) {
            $where[]         = "o.order_date >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            $binds['days']   = $days;
        }
        if (!empty($category) && $category !== 'all') {
            $where[]             = "c2.category_name LIKE CONCAT('%', :cat, '%')";
            $binds['cat']        = $category;
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT DISTINCT o.order_id, o.order_number, o.order_date, o.total_amount,
                       o.order_status, o.admin_feedback,
                       c.full_name AS customer_name, c.phone
                FROM orders o
                JOIN customers c  ON o.customer_id = c.customer_id
                JOIN order_items oi ON oi.order_id = o.order_id
                JOIN products p    ON oi.product_id = p.product_id
                JOIN categories c2 ON p.category_id = c2.category_id
                {$whereClause}
                ORDER BY o.order_date DESC";

        sendJson(fetchAll($sql, $binds));
    }

    // ---- Admin: All orders list ----
    if (isset($_GET['admin'])) {
        $sql = "SELECT o.order_id, o.order_number, o.order_date, o.total_amount,
                       o.order_status, o.admin_feedback,
                       c.full_name AS customer_name, c.phone
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                ORDER BY o.order_date DESC";
        sendJson(fetchAll($sql));
    }

    // ---- Default: recent orders ----
    $sql = "SELECT o.order_id, o.order_number, c.full_name AS customer_name,
                   o.order_date, o.total_amount, o.order_status
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            ORDER BY o.order_date DESC";
    sendJson(fetchAll($sql));
}

// ================================================================
//  POST — Customer: Place a new order
// ================================================================
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['customer']) || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        sendError("Invalid order data");
    }

    $customer = $data['customer'];
    $items    = $data['items'];

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // 1. Get or Create Customer
        $customerId = null;
        $existing = fetchAll("SELECT customer_id FROM customers WHERE phone = :phone", ['phone' => $customer['phone']]);

        if (count($existing) > 0) {
            $customerId = $existing[0]['customer_id'];
        } else {
            try {
                $sql = "INSERT INTO customers (full_name, phone, address, customer_type)
                        VALUES (:name, :phone, :address, 'Retail')";
                $out = executeInsertWithReturn($sql, [
                    'name'    => $customer['name'],
                    'phone'   => $customer['phone'],
                    'address' => isset($customer['address']) ? $customer['address'] : ''
                ]);
                $customerId = $out['new_id'];
            } catch (Exception $dupEx) {
                $existing = fetchAll("SELECT customer_id FROM customers WHERE phone = :phone", ['phone' => $customer['phone']]);
                if (count($existing) > 0) {
                    $customerId = $existing[0]['customer_id'];
                } else {
                    throw $dupEx;
                }
            }
        }

        // 2. Create Order with temp number
        $tmpNum = 'TMP-' . uniqid('', true);
        $stmt = $pdo->prepare(
            "INSERT INTO orders (customer_id, total_amount, order_status, order_number)
             VALUES (:cid, 0, 'Placed', :onum)"
        );
        $stmt->execute(['cid' => $customerId, 'onum' => $tmpNum]);
        $orderId = (int)$pdo->lastInsertId();

        if (!$orderId) {
            $pdo->rollBack();
            sendError("Failed to create order");
        }

        // Update to real order number
        $orderNumber = generateOrderNumber($orderId);
        $pdo->prepare("UPDATE orders SET order_number = :onum WHERE order_id = :oid")
            ->execute(['onum' => $orderNumber, 'oid' => $orderId]);

        // 3. Insert Order Items (triggers auto-deduct stock & update total)
        foreach ($items as $item) {
            $prod = fetchAll("SELECT product_id, unit_price FROM products WHERE product_id = :pid", ['pid' => $item['id']]);
            if (count($prod) === 0) {
                $prod = fetchAll("SELECT product_id, unit_price FROM products WHERE sku = :sku", ['sku' => $item['id']]);
            }
            if (count($prod) === 0) continue;

            $prodId = $prod[0]['product_id'];
            $price  = $prod[0]['unit_price'];

            $shadeId = null;
            if (!empty($item['shade'])) {
                $shades = fetchAll(
                    "SELECT shade_id FROM paint_shades WHERE shade_name = :sname OR shade_code = :scode",
                    ['sname' => $item['shade'], 'scode' => $item['shade']]
                );
                if (count($shades) > 0) {
                    $shadeId = $shades[0]['shade_id'];
                }
            }

            $sql = "INSERT INTO order_items (order_id, product_id, shade_id, quantity, unit_price, custom_shade_name, line_total)
                    VALUES (:oid, :pid, :sid, :qty, :price, :csname, :lt)";
            executeQuery($sql, [
                'oid'    => $orderId,
                'pid'    => $prodId,
                'sid'    => $shadeId,
                'qty'    => $item['qty'],
                'price'  => $price,
                'csname' => isset($item['shade']) ? $item['shade'] : null,
                'lt'     => $item['qty'] * $price
            ]);
        }

        $pdo->commit();

        sendJson([
            'success'      => true,
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'message'      => 'Order placed successfully. Track your order with: ' . $orderNumber
        ]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendError('Order failed: ' . $e->getMessage(), 500);
    }
}

if ($method !== 'GET' && $method !== 'POST' && $method !== 'PATCH') {
    sendError("Method not allowed", 405);
}
