<?php
require_once 'db.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'GET') {
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $sql = "SELECT p.product_id AS id, p.product_name AS name, p.sku, 
                   c.category_name AS category, p.brand, p.unit_price AS price, 
                   p.stock_qty AS stock, p.status, p.safety_level
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE 1=1";
            
    $binds = [];
    
    if ($category !== '') {
        $sql .= " AND LOWER(c.category_name) LIKE '%' || :cat || '%'";
        $binds['cat'] = strtolower($category);
    }
    
    if ($search !== '') {
        $sql .= " AND (LOWER(p.product_name) LIKE '%' || :search || '%' OR LOWER(p.sku) LIKE '%' || :search || '%')";
        $binds['search'] = strtolower($search);
    }
    
    $sql .= " ORDER BY p.product_id ASC";
    
    $products = fetchAll($sql, $binds);
    sendJson($products);
} elseif ($method === 'POST' || $method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['name']) || !isset($data['sku']) || !isset($data['category']) || !isset($data['price'])) {
        sendError("Missing required product fields");
    }
    
    // Resolve category_id
    $sqlCat = "SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(:cname)";
    $catRes = fetchAll($sqlCat, ['cname' => $data['category']]);
    if (count($catRes) === 0) {
        // Create category if not exists
        executeInsertWithReturn("INSERT INTO categories (category_name) VALUES (:cname) RETURNING category_id INTO :cid", ['cname' => $data['category']], ['cid' => 10]);
        $catRes = fetchAll($sqlCat, ['cname' => $data['category']]);
    }
    $catId = $catRes[0]['category_id'];
    
    $binds = [
        'sku' => $data['sku'],
        'name' => $data['name'],
        'cid' => $catId,
        'brand' => isset($data['brand']) ? $data['brand'] : 'N/A',
        'price' => $data['price'],
        'stock' => isset($data['stock']) ? $data['stock'] : 0,
        'safety' => isset($data['safety']) ? $data['safety'] : 10
    ];
    
    if ($method === 'POST') {
        $sql = "INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level) 
                VALUES (:sku, :name, :cid, :brand, :price, :stock, :safety)";
        executeQuery($sql, $binds);
        sendJson(['success' => true, 'message' => 'Product created']);
    } else {
        $binds['pid'] = $data['id'];
        $sql = "UPDATE products SET 
                sku = :sku, product_name = :name, category_id = :cid, brand = :brand, 
                unit_price = :price, stock_qty = :stock, safety_level = :safety 
                WHERE product_id = :pid";
        executeQuery($sql, $binds);
        sendJson(['success' => true, 'message' => 'Product updated']);
    }
} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id'])) {
        sendError("Missing product ID");
    }
    $sql = "DELETE FROM products WHERE product_id = :pid";
    executeQuery($sql, ['pid' => $data['id']]);
    sendJson(['success' => true, 'message' => 'Product deleted']);
} else {
    sendError("Method not allowed", 405);
}
