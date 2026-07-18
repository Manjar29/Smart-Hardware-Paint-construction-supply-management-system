-- ============================================================
--  SMART HARDWARE & PAINT SUPPLY MANAGEMENT SYSTEM
--  MySQL Schema — Compatible with XAMPP / phpMyAdmin
--  Database: paint_hardware_db
-- ============================================================

CREATE DATABASE IF NOT EXISTS paint_hardware_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE paint_hardware_db;

-- ============================================================
--  DISABLE FK CHECKS DURING SETUP
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (in dependency order)
DROP TABLE IF EXISTS shade_specs;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS inventory_logs;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS paint_shades;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS suppliers;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  1. CATEGORIES
-- ============================================================
CREATE TABLE categories (
    category_id   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100)    NOT NULL,
    description   VARCHAR(500),
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_categories   PRIMARY KEY (category_id),
    CONSTRAINT uq_category_name UNIQUE (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
--  2. PRODUCTS
-- ============================================================
CREATE TABLE products (
    product_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    sku             VARCHAR(20)     NOT NULL,
    product_name    VARCHAR(200)    NOT NULL,
    category_id     INT UNSIGNED    NOT NULL,
    brand           VARCHAR(100),
    unit_price      DECIMAL(12,2)   NOT NULL,
    stock_qty       INT             NOT NULL DEFAULT 0,
    safety_level    INT             NOT NULL DEFAULT 10,
    unit_of_measure VARCHAR(50)     NOT NULL DEFAULT 'pcs',
    status          VARCHAR(20)     NOT NULL DEFAULT 'Active',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_products      PRIMARY KEY (product_id),
    CONSTRAINT uq_product_sku   UNIQUE (sku),
    CONSTRAINT fk_product_cat   FOREIGN KEY (category_id) REFERENCES categories(category_id),
    CONSTRAINT ck_product_price CHECK (unit_price >= 0),
    CONSTRAINT ck_product_stock CHECK (stock_qty >= 0),
    CONSTRAINT ck_product_status CHECK (status IN ('Active','Inactive','Discontinued'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_name     ON products(product_name);


-- ============================================================
--  3. PAINT_SHADES
-- ============================================================
CREATE TABLE paint_shades (
    shade_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    shade_code    VARCHAR(20)     NOT NULL,
    shade_name    VARCHAR(100)    NOT NULL,
    formula_code  VARCHAR(20)     NOT NULL,
    category      VARCHAR(50)     NOT NULL,
    rgb_value     VARCHAR(30),
    mix_formula   VARCHAR(500)    NOT NULL,
    finish_type   VARCHAR(50),
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_paint_shades   PRIMARY KEY (shade_id),
    CONSTRAINT uq_shade_code     UNIQUE (shade_code),
    CONSTRAINT uq_formula_code   UNIQUE (formula_code),
    CONSTRAINT ck_shade_category CHECK (category IN ('Synthetic','Acrylic','Coat','Steel'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_shade_category ON paint_shades(category);
CREATE INDEX idx_shade_name     ON paint_shades(shade_name);


-- ============================================================
--  4. SHADE_SPECS
-- ============================================================
CREATE TABLE shade_specs (
    spec_id   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    shade_id  INT UNSIGNED    NOT NULL,
    spec_tag  VARCHAR(100)    NOT NULL,
    CONSTRAINT pk_shade_specs    PRIMARY KEY (spec_id),
    CONSTRAINT fk_spec_shade     FOREIGN KEY (shade_id) REFERENCES paint_shades(shade_id) ON DELETE CASCADE,
    CONSTRAINT uq_shade_spec_tag UNIQUE (shade_id, spec_tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
--  5. CUSTOMERS
-- ============================================================
CREATE TABLE customers (
    customer_id   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(200)    NOT NULL,
    phone         VARCHAR(20)     NOT NULL,
    address       VARCHAR(500),
    customer_type VARCHAR(30)     NOT NULL DEFAULT 'Retail',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_customers      PRIMARY KEY (customer_id),
    CONSTRAINT uq_customer_phone UNIQUE (phone),
    CONSTRAINT ck_customer_type  CHECK (customer_type IN ('Retail','Contractor','Wholesale'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_customer_phone ON customers(phone);


-- ============================================================
--  6. ORDERS
-- ============================================================
CREATE TABLE orders (
    order_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    order_number    VARCHAR(20)     NOT NULL DEFAULT '',
    customer_id     INT UNSIGNED    NOT NULL,
    order_date      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount    DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
    order_status    VARCHAR(30)     NOT NULL DEFAULT 'Placed',
    admin_feedback  VARCHAR(500)    DEFAULT NULL,
    CONSTRAINT pk_orders         PRIMARY KEY (order_id),
    CONSTRAINT uq_order_number   UNIQUE (order_number),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT ck_order_status   CHECK (order_status IN ('Placed','Processing','Shipped','Delivered','Cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_date     ON orders(order_date);


-- ============================================================
--  7. ORDER_ITEMS
-- ============================================================
CREATE TABLE order_items (
    item_id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    order_id          INT UNSIGNED    NOT NULL,
    product_id        INT UNSIGNED    NOT NULL,
    shade_id          INT UNSIGNED,
    quantity          INT             NOT NULL,
    unit_price        DECIMAL(12,2)   NOT NULL,
    line_total        DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
    custom_shade_name VARCHAR(100),
    CONSTRAINT pk_order_items   PRIMARY KEY (item_id),
    CONSTRAINT fk_oi_order      FOREIGN KEY (order_id)   REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_oi_product    FOREIGN KEY (product_id) REFERENCES products(product_id),
    CONSTRAINT fk_oi_shade      FOREIGN KEY (shade_id)   REFERENCES paint_shades(shade_id),
    CONSTRAINT ck_oi_qty        CHECK (quantity > 0),
    CONSTRAINT ck_oi_price      CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_oi_order   ON order_items(order_id);
CREATE INDEX idx_oi_product ON order_items(product_id);

-- Trigger: Calculate line_total before insert
DELIMITER $$
CREATE TRIGGER trg_order_items_before_insert
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$
DELIMITER ;

-- Trigger: After item insert — ONLY update order total
-- (Stock deduction now handled by admin Accept action, not at order placement)
DELIMITER $$
CREATE TRIGGER trg_after_order_item_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    -- Update order total only
    UPDATE orders
       SET total_amount = total_amount + NEW.line_total
     WHERE order_id = NEW.order_id;
END$$
DELIMITER ;

-- Trigger: Recalculate line_total before update
DELIMITER $$
CREATE TRIGGER trg_order_items_before_update
BEFORE UPDATE ON order_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$
DELIMITER ;

-- Trigger: After item delete — restore stock and adjust order total
DELIMITER $$
CREATE TRIGGER trg_after_order_item_delete
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    -- Restore stock
    UPDATE products
       SET stock_qty = stock_qty + OLD.quantity
     WHERE product_id = OLD.product_id;

    -- Adjust order total
    UPDATE orders
       SET total_amount = total_amount - OLD.line_total
     WHERE order_id = OLD.order_id;
END$$
DELIMITER ;


-- ============================================================
--  8. INVENTORY_LOGS
-- ============================================================
CREATE TABLE inventory_logs (
    log_id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    product_id     INT UNSIGNED    NOT NULL,
    movement_type  VARCHAR(10)     NOT NULL,
    quantity       INT             NOT NULL,
    reference_type VARCHAR(50),
    reference_id   VARCHAR(30),
    notes          VARCHAR(500),
    movement_date  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_inventory_logs PRIMARY KEY (log_id),
    CONSTRAINT fk_invlog_product FOREIGN KEY (product_id) REFERENCES products(product_id),
    CONSTRAINT ck_movement_type  CHECK (movement_type IN ('IN','OUT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_invlog_product ON inventory_logs(product_id);
CREATE INDEX idx_invlog_date    ON inventory_logs(movement_date);


-- ============================================================
--  9. SUPPLIERS
-- ============================================================
CREATE TABLE suppliers (
    supplier_id    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    supplier_name  VARCHAR(200)    NOT NULL,
    contact_phone  VARCHAR(20),
    contact_email  VARCHAR(100),
    address        VARCHAR(500),
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_suppliers PRIMARY KEY (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
--  SEED DATA — Categories
-- ============================================================
INSERT INTO categories (category_name, description) VALUES
('Paints',       'All paint products including emulsion, weathercoat, and wood polish'),
('Hardware',     'General hardware supplies — nails, screws, fasteners'),
('Iron & Steel', 'Iron rods, steel plates, and structural metal'),
('Tools',        'Hand tools, power tool accessories, drill bits'),
('Construction', 'Cement, concrete, and construction material');


-- ============================================================
--  SEED DATA — Products
-- ============================================================
INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure) VALUES
('PNT-001', 'Premium Emulsion Paint (1L)',  1, 'Berger',       1250.00,  24, 10, 'litre'),
('PNT-002', 'Weathercoat Shield (4L)',       1, 'Berger',       3400.00,  15,  8, 'litre'),
('PNT-045', 'Gloss Wood Polish (500ml)',     1, 'Asian Paints',  320.00,   8, 10, 'ml'),
('HDW-011', 'Galvanized Nails (1kg)',        2, 'RFL',           180.00, 120, 25, 'kg'),
('HDW-012', 'Premium Screws Pack (100pc)',   2, 'RFL',           120.00, 250, 50, 'pack'),
('HDW-089', 'Drill Bit Set (13pcs)',         4, 'Bosch',         280.00,  15,  8, 'set'),
('TL-001',  'Heavy Duty Steel Hammer',       4, 'Bosch',         350.00,  35, 10, 'pcs'),
('IRN-210', 'BSRM Iron Rod 10mm (6m)',       3, 'BSRM',          450.00,  42, 20, 'pcs'),
('IRN-211', 'Steel Plate 4x8 ft',            3, 'BSRM',         2800.00,  10, 10, 'pcs'),
('CST-001', 'Ready Mix Concrete (25kg)',     5, 'Lafarge',        850.00,  50, 15, 'bag'),
('CST-002', 'Lafarge Cement (50kg)',          5, 'Lafarge',        650.00,   0, 15, 'bag');


-- ============================================================
--  SEED DATA — Paint Shades
-- ============================================================
INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type) VALUES
('SH-001', 'Crimson Blaze',         'CB-001', 'Acrylic', 'rgb(244,63,94)',   'Red 40% + Yellow 15% + White 45%',       'Matte'),
('SH-002', 'Sunset Gold',           'SG-002', 'Acrylic', 'rgb(245,158,11)',  'Yellow 60% + Red 15% + White 25%',       'Semi-Gloss'),
('SH-003', 'Mint Leaf',             'ML-003', 'Acrylic', 'rgb(16,185,129)',  'Green 50% + Yellow 20% + White 30%',     'Satin'),
('SH-004', 'Ocean Deep',            'OD-004', 'Acrylic', 'rgb(59,130,246)',  'Blue 70% + Black 15% + White 15%',       'Glossy'),
('SH-091', 'Industrial Steel',      'IS-901', 'Steel',   'rgb(112,128,144)', 'Silver 75% + Black 15% + Blue 10%',      'Metallic'),
('SH-092', 'Galvanized Gray',       'GG-902', 'Steel',   'rgb(142,145,143)', 'Zinc 80% + Black 12% + Yellow 8%',       'Matte Metallic'),
('SH-093', 'Brushed Chrome',        'BC-903', 'Steel',   'rgb(192,192,192)', 'Silver 85% + White 10% + Black 5%',      'Satin Metallic'),
('SH-094', 'Gunmetal Blue',         'GB-904', 'Steel',   'rgb(69,85,96)',    'Blue 60% + Black 30% + White 10%',       'Glossy Metallic'),
('SH-095', 'Rusty Iron',            'RI-905', 'Steel',   'rgb(183,115,87)',  'Red Oxide 50% + Brown 30% + Yellow 20%', 'Textured'),
('SH-096', 'Copper Glow',           'CG-906', 'Steel',   'rgb(184,115,51)',  'Gold 65% + Red 25% + Yellow 10%',        'Semi-Gloss Metallic'),
('SH-097', 'Antique Bronze',        'AB-907', 'Steel',   'rgb(102,93,76)',   'Yellow 45% + Brown 35% + Black 20%',     'Matte Bronze'),
('SH-098', 'Titanium Silver',       'TS-908', 'Steel',   'rgb(168,175,175)', 'Silver 90% + White 5% + Black 5%',       'High Gloss'),
('SH-099', 'Carbon Black Metallic', 'CB-909', 'Steel',   'rgb(43,44,46)',    'Black 85% + Silver 15%',                 'Satin Metallic'),
('SH-100', 'Stainless Steel',       'SS-910', 'Steel',   'rgb(219,222,224)', 'Silver 80% + Blue 10% + White 10%',      'High Gloss Metallic');


-- ============================================================
--  SEED DATA — Shade Specs
-- ============================================================
INSERT INTO shade_specs (shade_id, spec_tag) VALUES
(1, 'Matte Finish'),
(1, 'Eco-friendly'),
(1, 'Weather Resistant'),
(2, 'Semi-gloss finish'),
(2, 'Fade Resistant'),
(2, 'Interior/Exterior'),
(3, 'Satin finish'),
(3, 'UV Protected'),
(3, 'Low VOC'),
(4, 'Gloss finish'),
(4, 'Premium Quality'),
(4, 'Long-lasting');


-- ============================================================
--  SEED DATA — Suppliers
-- ============================================================
INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address) VALUES
('Berger Paints Bangladesh Ltd.', '01711-000001', 'supply@bergerbd.com',     'Dhaka, Bangladesh'),
('Asian Paints BD',               '01711-000002', 'orders@asianpaintsbd.com','Chattogram, Bangladesh'),
('BSRM Steels Ltd.',              '01711-000003', 'sales@bsrm.com',          'Chittagong, Bangladesh'),
('RFL Hardware Division',         '01711-000004', 'hardware@rfl.com.bd',     'Dhaka, Bangladesh'),
('Lafarge Holcim Bangladesh',     '01711-000005', 'orders@lafarge.com.bd',   'Dhaka, Bangladesh');


-- ============================================================
--  SEED DATA — Sample Customers
-- ============================================================
INSERT INTO customers (full_name, phone, address, customer_type) VALUES
('Md. Rahim Uddin',        '01712345678', '45 Mirpur Road, Dhaka-1216', 'Retail'),
('Karim Construction Ltd.','01898765432', 'Banani DOHS, Dhaka-1206',   'Contractor');


-- ============================================================
--  SEED DATA — Sample Inventory Logs (manual/supplier movements)
-- ============================================================
INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date) VALUES
(1, 'IN',  30, 'Supplier Restock', 'RS-221',   'Supplier restocking order #RS-221',  '2025-06-24 10:00:00'),
(1, 'OUT',  6, 'Customer Order',   'ORD-1024', 'Customer order #ORD-1024',           '2025-06-24 14:30:00'),
(4, 'IN',  50, 'Supplier Restock', NULL,        'Wholesale shipment',                 '2025-06-23 09:00:00'),
(8, 'OUT',  8, 'Contractor Order', 'CON-0055', 'Contractor order #CON-0055',         '2025-06-23 11:20:00'),
(6, 'IN',  20, 'Supplier Restock', NULL,        'New inventory arrival',              '2025-06-22 08:45:00'),
(3, 'OUT', 12, 'Customer Order',   'ORD-1019', 'Customer order #ORD-1019',           '2025-06-22 15:00:00'),
(9, 'OUT',  5, 'Customer Order',   NULL,        'Construction project delivery',      '2025-06-21 13:10:00');


-- ============================================================
--  VERIFICATION — Row counts
-- ============================================================
SELECT 'categories'    AS `table`, COUNT(*) AS `rows` FROM categories    UNION ALL
SELECT 'products',                  COUNT(*)           FROM products       UNION ALL
SELECT 'paint_shades',              COUNT(*)           FROM paint_shades   UNION ALL
SELECT 'shade_specs',               COUNT(*)           FROM shade_specs    UNION ALL
SELECT 'customers',                 COUNT(*)           FROM customers      UNION ALL
SELECT 'orders',                    COUNT(*)           FROM orders         UNION ALL
SELECT 'order_items',               COUNT(*)           FROM order_items    UNION ALL
SELECT 'inventory_logs',            COUNT(*)           FROM inventory_logs UNION ALL
SELECT 'suppliers',                 COUNT(*)           FROM suppliers;

-- ============================================================
--  10. STORED PROCEDURE (Cursor & Transactions)
-- ============================================================
-- Example showing Cursor, Transaction, Savepoint, Rollback, and Commit
DELIMITER $$
CREATE PROCEDURE sp_restock_low_inventory(IN p_restock_amount INT)
BEGIN
    -- Variables for cursor
    DECLARE v_product_id INT;
    DECLARE v_stock_qty INT;
    DECLARE v_safety_level INT;
    DECLARE v_done INT DEFAULT FALSE;
    
    -- 1. Declare Cursor
    DECLARE cur_low_stock CURSOR FOR 
        SELECT product_id, stock_qty, safety_level 
        FROM products 
        WHERE stock_qty < safety_level AND status = 'Active';
        
    -- Declare Continue Handler for Cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    
    -- 2. Start Transaction
    START TRANSACTION;
    
    OPEN cur_low_stock;
    
    read_loop: LOOP
        FETCH cur_low_stock INTO v_product_id, v_stock_qty, v_safety_level;
        
        IF v_done THEN
            LEAVE read_loop;
        END IF;
        
        -- 3. Create a Savepoint before modifying this specific product
        SAVEPOINT sp_before_restock;
        
        BEGIN
            DECLARE v_error INT DEFAULT FALSE;
            -- Handle potential errors inside the loop
            DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_error = TRUE;
            
            -- Update the product stock
            UPDATE products 
            SET stock_qty = stock_qty + p_restock_amount 
            WHERE product_id = v_product_id;
            
            -- Log the inventory movement
            INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, notes) 
            VALUES (v_product_id, 'IN', p_restock_amount, 'Bulk Restock SP', 'Restocked via stored procedure');
            
            -- 4. Rollback to Savepoint if an error occurred during update or insert for this item
            IF v_error THEN
                ROLLBACK TO sp_before_restock;
            END IF;
        END;
        
    END LOOP;
    
    CLOSE cur_low_stock;
    
    -- 5. Commit the entire transaction
    COMMIT;
    
END$$
DELIMITER ;

-- ============================================================
--  END OF SCHEMA
-- ============================================================
