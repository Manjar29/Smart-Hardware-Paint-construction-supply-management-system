<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $sql = "SELECT s.shade_id AS id_num, s.shade_code AS id, s.shade_name AS name, 
                   s.formula_code AS code, s.category AS cat, s.rgb_value AS rgb, 
                   s.mix_formula AS mix, s.finish_type AS finish
            FROM paint_shades s
            WHERE 1=1";
            
    $binds = [];
    
    if ($category !== '') {
        $sql .= " AND s.category = :cat";
        $binds['cat'] = $category;
    }
    
    if ($search !== '') {
        $sql .= " AND (LOWER(s.shade_name) LIKE '%' || :search || '%' OR LOWER(s.shade_code) LIKE '%' || :search || '%')";
        $binds['search'] = strtolower($search);
    }
    
    $sql .= " ORDER BY s.shade_id ASC";
    
    $shades = fetchAll($sql, $binds);
    
    // Fetch specs for these shades (in a real production app we'd group this better or join, 
    // but for simplicity with limited dataset, we'll just fetch all specs and map them)
    $specsSql = "SELECT shade_id, spec_tag FROM shade_specs";
    $allSpecs = fetchAll($specsSql);
    
    $specsMap = [];
    foreach ($allSpecs as $row) {
        $sid = $row['shade_id'];
        if (!isset($specsMap[$sid])) {
            $specsMap[$sid] = [];
        }
        $specsMap[$sid][] = $row['spec_tag'];
    }
    
    // Map specs to shades and format data to match frontend JS expectations
    $result = [];
    foreach ($shades as $shade) {
        $sid = $shade['id_num'];
        $shade['specs'] = isset($specsMap[$sid]) ? $specsMap[$sid] : [];
        unset($shade['id_num']); // Remove internal ID
        $result[] = $shade;
    }
    
    sendJson($result);
} else {
    sendError("Method not allowed", 405);
}
