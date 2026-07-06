<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendError("Username and password required");
    }
    
    // Hardcoded simple check to match existing JS logic
    if ($data['username'] === 'admin' && $data['password'] === 'admin123') {
        sendJson(['success' => true]);
    } else {
        sendError("Invalid credentials", 401);
    }
} else {
    sendError("Method not allowed", 405);
}
