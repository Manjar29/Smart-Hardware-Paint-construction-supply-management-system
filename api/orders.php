<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['customer']) || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        sendError("Invalid order data");
    }
    
    $customer = $data['customer'];
    $items = $data['items'];
    
    // 1. Get or Create Customer
    $customerId = null;
    $sql = "SELECT customer_id FROM customers WHERE phone = :phone";
    $existing = fetchAll($sql, ['phone' => $customer['phone']]);
    
    if (count($existing) > 0) {
        $customerId = $existing[0]['customer_id'];
    } else {
        $sql = "INSERT INTO customers (full_name, phone, address, customer_type) 
                VALUES (:name, :phone, :address, 'Retail') 
                RETURNING customer_id INTO :new_id";
        $binds = [
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'address' => isset($customer['address']) ? $customer['address'] : ''
        ];
        $out = executeInsertWithReturn($sql, $binds, ['new_id' => 10]);
        $customerId = $out['new_id'];
    }
    
    // 2. Create Order
    $sql = "INSERT INTO orders (customer_id, total_amount, order_status) 
            VALUES (:cid, 0, 'Placed') 
            RETURNING order_id INTO :oid";
    $out = executeInsertWithReturn($sql, ['cid' => $customerId], ['oid' => 10]);
    $orderId = $out['oid'];
    
    if (!$orderId) {
        sendError("Failed to create order");
    }
    
    // 3. Insert Order Items (Triggers will auto-update total_amount and deduct stock!)
    foreach ($items as $item) {
        // Need to get product internal ID from SKU/ID used in frontend
        // Currently frontend uses 'PNT-001' as product ID (which is actually SKU)
        $sql = "SELECT product_id, unit_price FROM products WHERE product_id = :pid";
        $prod = fetchAll($sql, ['pid' => $item['id']]);
        
        if (count($prod) == 0) continue;
        
        $prodId = $prod[0]['product_id'];
        $price = $prod[0]['unit_price']; // Overwrite frontend price for security
        
        // Find shade_id if custom shade provided
        $shadeId = null;
        if (isset($item['shade']) && $item['shade']) {
            $sql = "SELECT shade_id FROM paint_shades WHERE shade_name = :sname OR shade_code = :scode";
            $shades = fetchAll($sql, ['sname' => $item['shade'], 'scode' => $item['shade']]);
            if (count($shades) > 0) {
                $shadeId = $shades[0]['shade_id'];
            }
        }
        
        $sql = "INSERT INTO order_items (order_id, product_id, shade_id, quantity, unit_price, custom_shade_name) 
                VALUES (:oid, :pid, :sid, :qty, :price, :csname)";
        
        executeQuery($sql, [
            'oid' => $orderId,
            'pid' => $prodId,
            'sid' => $shadeId,
            'qty' => $item['qty'],
            'price' => $price,
            'csname' => isset($item['shade']) ? $item['shade'] : null
        ]);
    }
    
    sendJson(['success' => true, 'order_id' => $orderId, 'message' => 'Order placed successfully. Database triggers fired.']);
} elseif ($method === 'GET') {
    // List recent orders
    $sql = "SELECT o.order_id, o.order_number, c.full_name as customer_name, o.order_date, o.total_amount, o.order_status
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            ORDER BY o.order_date DESC";
    $orders = fetchAll($sql);
    sendJson($orders);
} else {
    sendError("Method not allowed", 405);
}
