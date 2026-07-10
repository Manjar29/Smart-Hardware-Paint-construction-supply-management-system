<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['customer']) || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        sendError("Invalid order data");
    }

    $customer = $data['customer'];
    $items    = $data['items'];

    // -------------------------------------------------------
    // 1. Get or Create Customer
    // -------------------------------------------------------
    $customerId = null;
    $sql = "SELECT customer_id FROM customers WHERE phone = :phone";
    $existing = fetchAll($sql, ['phone' => $customer['phone']]);

    if (count($existing) > 0) {
        $customerId = $existing[0]['customer_id'];
    } else {
        $sql = "INSERT INTO customers (full_name, phone, address, customer_type)
                VALUES (:name, :phone, :address, 'Retail')";
        $out = executeInsertWithReturn($sql, [
            'name'    => $customer['name'],
            'phone'   => $customer['phone'],
            'address' => isset($customer['address']) ? $customer['address'] : ''
        ]);
        $customerId = $out['new_id'];
    }

    // -------------------------------------------------------
    // 2. Create Order (with placeholder order_number)
    // -------------------------------------------------------
    $sql = "INSERT INTO orders (customer_id, total_amount, order_status, order_number)
            VALUES (:cid, 0, 'Placed', :onum)";

    // We need to pre-compute a temp order number — we'll update after insert
    $pdo      = getDbConnection();
    $tmpNum   = 'ORD-' . time(); // temporary unique placeholder
    $stmt     = $pdo->prepare($sql);
    $stmt->execute(['cid' => $customerId, 'onum' => $tmpNum]);
    $orderId  = (int)$pdo->lastInsertId();

    if (!$orderId) {
        sendError("Failed to create order");
    }

    // Now update with real order number based on actual ID
    $orderNumber = generateOrderNumber($orderId);
    $pdo->prepare("UPDATE orders SET order_number = :onum WHERE order_id = :oid")
        ->execute(['onum' => $orderNumber, 'oid' => $orderId]);

    // -------------------------------------------------------
    // 3. Insert Order Items
    //    Triggers will auto-update total_amount and deduct stock
    // -------------------------------------------------------
    foreach ($items as $item) {
        // Look up product by ID (integer product_id from frontend)
        $sql  = "SELECT product_id, unit_price FROM products WHERE product_id = :pid";
        $prod = fetchAll($sql, ['pid' => $item['id']]);

        if (count($prod) === 0) {
            // Try by SKU in case the frontend sends SKU string
            $sql  = "SELECT product_id, unit_price FROM products WHERE sku = :sku";
            $prod = fetchAll($sql, ['sku' => $item['id']]);
        }

        if (count($prod) === 0) continue; // Skip unknown products

        $prodId = $prod[0]['product_id'];
        $price  = $prod[0]['unit_price']; // Use DB price for security

        // Resolve shade_id if a shade was chosen
        $shadeId = null;
        if (!empty($item['shade'])) {
            $sql    = "SELECT shade_id FROM paint_shades WHERE shade_name = :sname OR shade_code = :scode";
            $shades = fetchAll($sql, ['sname' => $item['shade'], 'scode' => $item['shade']]);
            if (count($shades) > 0) {
                $shadeId = $shades[0]['shade_id'];
            }
        }

        // Insert item (trigger trg_order_items_before_insert computes line_total)
        $sql = "INSERT INTO order_items (order_id, product_id, shade_id, quantity, unit_price, custom_shade_name, line_total)
                VALUES (:oid, :pid, :sid, :qty, :price, :csname, :lt)";
        executeQuery($sql, [
            'oid'    => $orderId,
            'pid'    => $prodId,
            'sid'    => $shadeId,
            'qty'    => $item['qty'],
            'price'  => $price,
            'csname' => isset($item['shade']) ? $item['shade'] : null,
            'lt'     => $item['qty'] * $price   // computed here; trigger also sets it
        ]);
    }

    sendJson([
        'success'      => true,
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'message'      => 'Order placed successfully. Triggers fired: stock deducted & inventory logged.'
    ]);

} elseif ($method === 'GET') {
    // List recent orders (most recent first)
    $sql = "SELECT o.order_id, o.order_number, c.full_name AS customer_name,
                   o.order_date, o.total_amount, o.order_status
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            ORDER BY o.order_date DESC";
    sendJson(fetchAll($sql));

} else {
    sendError("Method not allowed", 405);
}
