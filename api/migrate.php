<?php
require_once 'db.php';
$pdo = getDbConnection();
$results = [];

// ── Step 1: Add admin_feedback column if not exists ──────────────────
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN admin_feedback VARCHAR(500) DEFAULT NULL");
    $results[] = "✅ Added admin_feedback column to orders table.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $results[] = "ℹ️ admin_feedback column already exists — skipped.";
    } else {
        $results[] = "❌ Error adding column: " . $e->getMessage();
    }
}

// ── Step 2: Fix trigger — remove auto stock deduction on order insert ─
// The new flow: stock deducts ONLY when admin ACCEPTS (PATCH → Processing)
// So we modify trg_after_order_item_insert to only update order total + log
// but NOT deduct stock_qty.
try {
    $pdo->exec("DROP TRIGGER IF EXISTS trg_after_order_item_insert");
    $results[] = "✅ Dropped old auto-deduct trigger.";
} catch (Exception $e) {
    $results[] = "⚠️ Drop trigger warning: " . $e->getMessage();
}

try {
    $pdo->exec("
CREATE TRIGGER trg_after_order_item_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    -- Only update the order total (stock deduction handled by admin accept action)
    UPDATE orders
       SET total_amount = total_amount + NEW.line_total
     WHERE order_id = NEW.order_id;
END
    ");
    $results[] = "✅ Recreated trigger: order total updated only (stock deducted on admin accept).";
} catch (Exception $e) {
    $results[] = "❌ Error creating trigger: " . $e->getMessage();
}

// ── Step 3: Also fix the delete trigger (remove stock restore on item delete) ─
try {
    $pdo->exec("DROP TRIGGER IF EXISTS trg_after_order_item_delete");
    $results[] = "✅ Dropped old item-delete restore trigger.";
} catch (Exception $e) {
    $results[] = "⚠️ Drop delete trigger: " . $e->getMessage();
}

try {
    $pdo->exec("
CREATE TRIGGER trg_after_order_item_delete
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    -- Only adjust the order total
    UPDATE orders
       SET total_amount = total_amount - OLD.line_total
     WHERE order_id = OLD.order_id;
END
    ");
    $results[] = "✅ Recreated item-delete trigger (only adjusts order total).";
} catch (Exception $e) {
    $results[] = "❌ Error: " . $e->getMessage();
}

sendJson([
    'migration' => 'complete',
    'steps'     => count($results),
    'results'   => $results
]);
