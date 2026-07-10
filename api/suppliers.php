<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sql = "SELECT supplier_id, supplier_name, contact_phone, contact_email, address, created_at
            FROM suppliers
            ORDER BY supplier_name ASC";
    sendJson(fetchAll($sql));

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['supplier_name']) || trim($data['supplier_name']) === '') {
        sendError("supplier_name is required");
    }

    $out = executeInsertWithReturn(
        "INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address)
         VALUES (:name, :phone, :email, :addr)",
        [
            'name'  => $data['supplier_name'],
            'phone' => isset($data['contact_phone']) ? $data['contact_phone'] : null,
            'email' => isset($data['contact_email']) ? $data['contact_email'] : null,
            'addr'  => isset($data['address'])       ? $data['address']       : null,
        ]
    );
    sendJson(['success' => true, 'supplier_id' => $out['new_id'], 'message' => 'Supplier created']);

} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['supplier_id'])) {
        sendError("supplier_id is required");
    }
    executeQuery(
        "UPDATE suppliers
            SET supplier_name  = :name,
                contact_phone  = :phone,
                contact_email  = :email,
                address        = :addr
          WHERE supplier_id = :sid",
        [
            'name'  => $data['supplier_name'],
            'phone' => isset($data['contact_phone']) ? $data['contact_phone'] : null,
            'email' => isset($data['contact_email']) ? $data['contact_email'] : null,
            'addr'  => isset($data['address'])       ? $data['address']       : null,
            'sid'   => $data['supplier_id']
        ]
    );
    sendJson(['success' => true, 'message' => 'Supplier updated']);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['supplier_id'])) {
        sendError("supplier_id is required");
    }
    executeQuery("DELETE FROM suppliers WHERE supplier_id = :sid", ['sid' => $data['supplier_id']]);
    sendJson(['success' => true, 'message' => 'Supplier deleted']);

} else {
    sendError("Method not allowed", 405);
}
