<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $reports = [];
    
    // 1. Sales Performance (Monthly Revenue)
    // In a real app we'd filter by month, but using total for demonstration
    $sqlRevenue = "SELECT NVL(SUM(total_amount), 0) as total_revenue FROM orders";
    $revData = fetchAll($sqlRevenue);
    $reports['revenue'] = $revData[0]['total_revenue'];
    
    // 2. Customer Orders Count
    $sqlOrders = "SELECT COUNT(*) as total_orders FROM orders";
    $ordData = fetchAll($sqlOrders);
    $reports['total_orders'] = $ordData[0]['total_orders'];
    
    // 3. Average Order Value
    $reports['avg_order'] = $reports['total_orders'] > 0 ? 
        round($reports['revenue'] / $reports['total_orders'], 2) : 0;
        
    // 4. Top Products by Revenue
    $sqlTop = "SELECT p.product_name, SUM(oi.quantity) as units_sold, SUM(oi.line_total) as revenue
               FROM order_items oi
               JOIN products p ON oi.product_id = p.product_id
               GROUP BY p.product_id, p.product_name
               ORDER BY revenue DESC
               FETCH FIRST 10 ROWS ONLY";
    $reports['top_products'] = fetchAll($sqlTop);
    
    sendJson($reports);
} else {
    sendError("Method not allowed", 405);
}
