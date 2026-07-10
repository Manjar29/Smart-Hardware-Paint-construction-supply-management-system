<?php
require_once 'db.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'GET') {
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $search   = isset($_GET['search'])   ? trim($_GET['search'])   : '';

    $sql    = "SELECT p.product_id AS id, p.product_name AS name, p.sku,
                      c.category_name AS category, p.brand,
                      p.unit_price AS price, p.stock_qty AS stock,
                      p.status, p.safety_level, p.unit_of_measure
               FROM products p
               JOIN categories c ON p.category_id = c.category_id
               WHERE 1=1";
    $binds  = [];

    if ($category !== '') {
        $sql .= " AND LOWER(c.category_name) LIKE CONCAT('%', :cat, '%')";
        $binds['cat'] = strtolower($category);
    }

    if ($search !== '') {
        $sql .= " AND (LOWER(p.product_name) LIKE CONCAT('%', :search, '%')
                    OR LOWER(p.sku)          LIKE CONCAT('%', :search, '%'))";
        $binds['search'] = strtolower($search);
    }

    $sql .= " ORDER BY p.product_id ASC";
    sendJson(fetchAll($sql, $binds));

} elseif ($method === 'POST' || $method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['name']) || !isset($data['sku']) || !isset($data['category']) || !isset($data['price'])) {
        sendError("Missing required product fields: name, sku, category, price");
    }

    // -------------------------------------------------------
    // Resolve category_id — create category if not found
    // -------------------------------------------------------
    $sqlCat = "SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(:cname)";
    $catRes = fetchAll($sqlCat, ['cname' => $data['category']]);

    if (count($catRes) === 0) {
        $out    = executeInsertWithReturn(
            "INSERT INTO categories (category_name) VALUES (:cname)",
            ['cname' => $data['category']]
        );
        $catId  = $out['new_id'];
    } else {
        $catId  = $catRes[0]['category_id'];
    }

    $binds = [
        'sku'    => $data['sku'],
        'name'   => $data['name'],
        'cid'    => $catId,
        'brand'  => isset($data['brand'])  ? $data['brand']  : 'N/A',
        'price'  => $data['price'],
        'stock'  => isset($data['stock'])  ? $data['stock']  : 0,
        'safety' => isset($data['safety']) ? $data['safety'] : 10,
        'uom'    => isset($data['unit_of_measure']) ? $data['unit_of_measure'] : 'pcs',
        'status' => isset($data['status']) ? $data['status'] : 'Active'
    ];

    if ($method === 'POST') {
        $sql = "INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure, status)
                VALUES (:sku, :name, :cid, :brand, :price, :stock, :safety, :uom, :status)";
        $out = executeInsertWithReturn($sql, $binds);
        sendJson(['success' => true, 'product_id' => $out['new_id'], 'message' => 'Product created']);
    } else {
        // PUT — update
        if (!isset($data['id'])) {
            sendError("Product ID required for update");
        }
        $binds['pid'] = $data['id'];
        $sql = "UPDATE products
                   SET sku             = :sku,
                       product_name    = :name,
                       category_id     = :cid,
                       brand           = :brand,
                       unit_price      = :price,
                       stock_qty       = :stock,
                       safety_level    = :safety,
                       unit_of_measure = :uom,
                       status          = :status
                 WHERE product_id = :pid";
        executeQuery($sql, $binds);
        sendJson(['success' => true, 'message' => 'Product updated']);
    }

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id'])) {
        sendError("Missing product ID");
    }
    executeQuery("DELETE FROM products WHERE product_id = :pid", ['pid' => $data['id']]);
    sendJson(['success' => true, 'message' => 'Product deleted']);

} else {
    sendError("Method not allowed", 405);
}
