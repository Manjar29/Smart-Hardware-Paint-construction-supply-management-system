<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    try {
        $pdo = getDbConnection();
        
        // You can optionally pass the restock amount in the request body
        $data = json_decode(file_get_contents('php://input'), true);
        $restockAmount = isset($data['amount']) ? (int)$data['amount'] : 50;
        
        // Call the stored procedure to bulk restock low inventory items
        $stmt = $pdo->prepare("CALL sp_restock_low_inventory(:amount)");
        $stmt->execute(['amount' => $restockAmount]);
        
        sendJson([
            'success' => true, 
            'message' => "Bulk restock of $restockAmount units completed successfully for low-stock items."
        ]);
        
    } catch (Exception $e) {
        sendError('Restock failed: ' . $e->getMessage(), 500);
    }
} else {
    sendError("Method not allowed", 405);
}
