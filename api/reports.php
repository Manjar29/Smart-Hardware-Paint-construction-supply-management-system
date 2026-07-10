<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $reports = [];

    // 1. Total Revenue
    $revData             = fetchAll("SELECT IFNULL(SUM(total_amount), 0) AS total_revenue FROM orders");
    $reports['revenue']  = (float)$revData[0]['total_revenue'];

    // 2. Total Orders
    $ordData               = fetchAll("SELECT COUNT(*) AS total_orders FROM orders");
    $reports['total_orders'] = (int)$ordData[0]['total_orders'];

    // 3. Average Order Value
    $reports['avg_order'] = $reports['total_orders'] > 0
        ? round($reports['revenue'] / $reports['total_orders'], 2)
        : 0;

    // 4. Total Customers
    $custData              = fetchAll("SELECT COUNT(*) AS total_customers FROM customers");
    $reports['total_customers'] = (int)$custData[0]['total_customers'];

    // 5. Low Stock Products (stock below safety level)
    $reports['low_stock'] = fetchAll(
        "SELECT product_id, sku, product_name, stock_qty, safety_level
         FROM products
         WHERE stock_qty < safety_level
         ORDER BY stock_qty ASC"
    );

    // 6. Top 10 Products by Revenue
    $reports['top_products'] = fetchAll(
        "SELECT p.product_name,
                SUM(oi.quantity)   AS units_sold,
                SUM(oi.line_total) AS revenue
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         GROUP BY p.product_id, p.product_name
         ORDER BY revenue DESC
         LIMIT 10"
    );

    // 7. Recent Orders (last 10)
    $reports['recent_orders'] = fetchAll(
        "SELECT o.order_number, c.full_name AS customer_name,
                o.order_date, o.total_amount, o.order_status
         FROM orders o
         JOIN customers c ON o.customer_id = c.customer_id
         ORDER BY o.order_date DESC
         LIMIT 10"
    );

    // 8. Sales by Category
    $reports['sales_by_category'] = fetchAll(
        "SELECT c.category_name,
                SUM(oi.quantity)   AS units_sold,
                SUM(oi.line_total) AS revenue
         FROM order_items oi
         JOIN products p  ON oi.product_id = p.product_id
         JOIN categories c ON p.category_id = c.category_id
         GROUP BY c.category_id, c.category_name
         ORDER BY revenue DESC"
    );

    sendJson($reports);

} else {
    sendError("Method not allowed", 405);
}
