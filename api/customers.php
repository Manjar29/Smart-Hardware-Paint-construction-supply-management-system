<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sql = "SELECT customer_id, full_name, phone, address, customer_type, created_at FROM customers ORDER BY created_at DESC";
    $customers = fetchAll($sql);
    sendJson($customers);
} elseif ($method === 'POST') {
    // Basic Create/Find customer
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || !isset($data['phone'])) {
        sendError("Name and phone are required");
    }
    
    // Check if customer exists by phone
    $sql = "SELECT customer_id FROM customers WHERE phone = :phone";
    $existing = fetchAll($sql, ['phone' => $data['phone']]);
    
    if (count($existing) > 0) {
        sendJson(['customer_id' => $existing[0]['customer_id'], 'message' => 'Customer found']);
    } else {
        // Create new customer using sequence
        $sql = "INSERT INTO customers (full_name, phone, address, customer_type) 
                VALUES (:name, :phone, :address, 'Retail') 
                RETURNING customer_id INTO :new_id";
                
        $binds = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => isset($data['address']) ? $data['address'] : ''
        ];
        
        $outBinds = ['new_id' => 10]; // 10 chars max
        $out = executeInsertWithReturn($sql, $binds, $outBinds);
        
        sendJson(['customer_id' => $out['new_id'], 'message' => 'Customer created']);
    }
} else {
    sendError("Method not allowed", 405);
}
