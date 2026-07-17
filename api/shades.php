<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $search   = isset($_GET['search'])   ? trim($_GET['search'])   : '';

    $sql   = "SELECT s.shade_id    AS id_num,
                     s.shade_code  AS id,
                     s.shade_name  AS name,
                     s.formula_code AS code,
                     s.category    AS category,
                     s.rgb_value   AS rgb,
                     s.mix_formula AS mix,
                     s.finish_type AS finish
              FROM paint_shades s
              WHERE 1=1";
    $binds = [];

    if ($category !== '') {
        $sql .= " AND s.category = :cat";
        $binds['cat'] = $category;
    }

    if ($search !== '') {
        $sql .= " AND (LOWER(s.shade_name)  LIKE CONCAT('%', :search, '%')
                    OR LOWER(s.shade_code) LIKE CONCAT('%', :search, '%'))";
        $binds['search'] = strtolower($search);
    }

    $sql .= " ORDER BY s.shade_id ASC";

    $shades = fetchAll($sql, $binds);

    // Fetch all specs and map them to shades
    $allSpecs = fetchAll("SELECT shade_id, spec_tag FROM shade_specs ORDER BY spec_id ASC");
    $specsMap = [];
    foreach ($allSpecs as $row) {
        $sid = $row['shade_id'];
        if (!isset($specsMap[$sid])) $specsMap[$sid] = [];
        $specsMap[$sid][] = $row['spec_tag'];
    }

    // Build result
    $result = [];
    foreach ($shades as $shade) {
        $sid            = $shade['id_num'];
        $shade['specs'] = isset($specsMap[$sid]) ? $specsMap[$sid] : [];
        unset($shade['id_num']); // Remove internal auto-increment id
        $result[]       = $shade;
    }

    sendJson($result);

} else {
    sendError("Method not allowed", 405);
}
