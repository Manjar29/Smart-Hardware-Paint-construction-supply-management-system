<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sql = "SELECT customer_id, full_name, phone, address, customer_type, created_at
            FROM customers
            ORDER BY created_at DESC";
    sendJson(fetchAll($sql));

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['name']) || !isset($data['phone'])) {
        sendError("Name and phone are required");
    }

    // Check if customer already exists by phone number
    $sql      = "SELECT customer_id FROM customers WHERE phone = :phone";
    $existing = fetchAll($sql, ['phone' => $data['phone']]);

    if (count($existing) > 0) {
        sendJson(['customer_id' => $existing[0]['customer_id'], 'message' => 'Customer found']);
    } else {
        // Create new customer
        $sql = "INSERT INTO customers (full_name, phone, address, customer_type)
                VALUES (:name, :phone, :address, :type)";
        $out = executeInsertWithReturn($sql, [
            'name'    => $data['name'],
            'phone'   => $data['phone'],
            'address' => isset($data['address'])       ? $data['address']       : '',
            'type'    => isset($data['customer_type']) ? $data['customer_type'] : 'Retail'
        ]);
        sendJson(['customer_id' => $out['new_id'], 'message' => 'Customer created']);
    }

} elseif ($method === 'PUT') {
    // Update existing customer
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['customer_id'])) {
        sendError("customer_id is required for update");
    }

    $sql = "UPDATE customers
               SET full_name     = :name,
                   phone         = :phone,
                   address       = :address,
                   customer_type = :type
             WHERE customer_id   = :cid";
    executeQuery($sql, [
        'name'    => $data['name'],
        'phone'   => $data['phone'],
        'address' => isset($data['address'])       ? $data['address']       : '',
        'type'    => isset($data['customer_type']) ? $data['customer_type'] : 'Retail',
        'cid'     => $data['customer_id']
    ]);
    sendJson(['success' => true, 'message' => 'Customer updated']);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['customer_id'])) {
        sendError("customer_id is required");
    }
    executeQuery("DELETE FROM customers WHERE customer_id = :cid", ['cid' => $data['customer_id']]);
    sendJson(['success' => true, 'message' => 'Customer deleted']);

} else {
    sendError("Method not allowed", 405);
}
