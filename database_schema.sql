--
SET DEFINE OFF;
SET SERVEROUTPUT ON SIZE UNLIMITED;
SET FEEDBACK ON;

PROMPT ============================================================
PROMPT  STARTING BANGLADESH PAINTS & HARDWARE SCHEMA SETUP
PROMPT ============================================================

--
PROMPT Dropping existing tables...
DECLARE
   v_dropped_count NUMBER := 0;
BEGIN
   FOR t IN (
      SELECT table_name FROM user_tables
      WHERE table_name IN (
         'SHADE_SPECS','ORDER_ITEMS','INVENTORY_LOGS',
         'ORDERS','PAINT_SHADES','PRODUCTS','CUSTOMERS',
         'CATEGORIES','SUPPLIERS'
      )
      ORDER BY DECODE(table_name,
         'SHADE_SPECS',1,'ORDER_ITEMS',2,'INVENTORY_LOGS',3,
         'ORDERS',4,'PAINT_SHADES',5,'PRODUCTS',6,
         'CUSTOMERS',7,'CATEGORIES',8,'SUPPLIERS',9)
   ) LOOP
      EXECUTE IMMEDIATE 'DROP TABLE ' || t.table_name || ' CASCADE CONSTRAINTS';
      DBMS_OUTPUT.PUT_LINE('Dropped table: ' || t.table_name);
      v_dropped_count := v_dropped_count + 1;
   END LOOP;
   IF v_dropped_count = 0 THEN
      DBMS_OUTPUT.PUT_LINE('No existing tables to drop.');
   END IF;
END;
/

-- Drop sequences if they exist
PROMPT Dropping existing sequences...
DECLARE
   v_dropped_count NUMBER := 0;
BEGIN
   FOR s IN (
      SELECT sequence_name FROM user_sequences
      WHERE sequence_name IN (
         'SEQ_CATEGORY_ID','SEQ_PRODUCT_ID','SEQ_SHADE_ID',
         'SEQ_CUSTOMER_ID','SEQ_ORDER_ID','SEQ_ORDER_ITEM_ID',
         'SEQ_INV_LOG_ID','SEQ_SUPPLIER_ID','SEQ_SHADE_SPEC_ID'
      )
   ) LOOP
      EXECUTE IMMEDIATE 'DROP SEQUENCE ' || s.sequence_name;
      DBMS_OUTPUT.PUT_LINE('Dropped sequence: ' || s.sequence_name);
      v_dropped_count := v_dropped_count + 1;
   END LOOP;
   IF v_dropped_count = 0 THEN
      DBMS_OUTPUT.PUT_LINE('No existing sequences to drop.');
   END IF;
END;
/


-- ========================
--  1. CATEGORIES
-- ========================
PROMPT Creating table CATEGORIES...
CREATE TABLE categories (
   category_id   NUMBER(10)     NOT NULL,
   category_name VARCHAR2(100)  NOT NULL,
   description   VARCHAR2(500),
   created_at    DATE           DEFAULT SYSDATE NOT NULL,
   CONSTRAINT pk_categories PRIMARY KEY (category_id),
   CONSTRAINT uq_category_name UNIQUE (category_name)
 );
 
CREATE SEQUENCE seq_category_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
 
CREATE OR REPLACE TRIGGER trg_categories_bi
BEFORE INSERT ON categories
FOR EACH ROW
WHEN (NEW.category_id IS NULL)
BEGIN
   :NEW.category_id := seq_category_id.NEXTVAL;
END;
/
 
 
-- ========================
--  2. PRODUCTS
-- ========================
PROMPT Creating table PRODUCTS...
CREATE TABLE products (
   product_id    NUMBER(10)     NOT NULL,
   sku           VARCHAR2(20)   NOT NULL,
   product_name  VARCHAR2(200)  NOT NULL,
   category_id   NUMBER(10)     NOT NULL,
   brand         VARCHAR2(100),
   unit_price    NUMBER(12,2)   NOT NULL,
   stock_qty     NUMBER(10)     DEFAULT 0 NOT NULL,
   safety_level  NUMBER(10)     DEFAULT 10 NOT NULL,
   unit_of_measure VARCHAR2(50) DEFAULT 'pcs',
   status        VARCHAR2(20)   DEFAULT 'Active',
   created_at    DATE           DEFAULT SYSDATE NOT NULL,
   updated_at    DATE           DEFAULT SYSDATE NOT NULL,
   CONSTRAINT pk_products      PRIMARY KEY (product_id),
   CONSTRAINT uq_product_sku   UNIQUE (sku),
   CONSTRAINT fk_product_cat   FOREIGN KEY (category_id) REFERENCES categories(category_id),
   CONSTRAINT ck_product_price CHECK (unit_price >= 0),
   CONSTRAINT ck_product_stock CHECK (stock_qty >= 0),
   CONSTRAINT ck_product_status CHECK (status IN ('Active','Inactive','Discontinued'))
);

CREATE SEQUENCE seq_product_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_products_bi
BEFORE INSERT ON products
FOR EACH ROW
WHEN (NEW.product_id IS NULL)
BEGIN
   :NEW.product_id := seq_product_id.NEXTVAL;
END;
/

CREATE OR REPLACE TRIGGER trg_products_bu
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
   :NEW.updated_at := SYSDATE;
END;
/

-- Indexes for common lookups
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_name     ON products(product_name);


-- ========================
--  3. PAINT_SHADES
-- ========================
PROMPT Creating table PAINT_SHADES...
CREATE TABLE paint_shades (
   shade_id       NUMBER(10)     NOT NULL,
   shade_code     VARCHAR2(20)   NOT NULL,
   shade_name     VARCHAR2(100)  NOT NULL,
   formula_code   VARCHAR2(20)   NOT NULL,
   category       VARCHAR2(50)   NOT NULL,
   rgb_value      VARCHAR2(30),
   mix_formula    VARCHAR2(500)  NOT NULL,
   finish_type    VARCHAR2(50),
   created_at     DATE           DEFAULT SYSDATE NOT NULL,
   CONSTRAINT pk_paint_shades     PRIMARY KEY (shade_id),
   CONSTRAINT uq_shade_code       UNIQUE (shade_code),
   CONSTRAINT uq_formula_code     UNIQUE (formula_code),
   CONSTRAINT ck_shade_category   CHECK (category IN ('Synthetic','Acrylic','Coat','Steel'))
);

CREATE SEQUENCE seq_shade_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_paint_shades_bi
BEFORE INSERT ON paint_shades
FOR EACH ROW
WHEN (NEW.shade_id IS NULL)
BEGIN
   :NEW.shade_id := seq_shade_id.NEXTVAL;
END;
/

CREATE INDEX idx_shade_category ON paint_shades(category);
CREATE INDEX idx_shade_name     ON paint_shades(shade_name);


-- ========================
--  4. SHADE_SPECS
-- ========================
PROMPT Creating table SHADE_SPECS...
CREATE TABLE shade_specs (
   spec_id    NUMBER(10)    NOT NULL,
   shade_id   NUMBER(10)    NOT NULL,
   spec_tag   VARCHAR2(100) NOT NULL,
   CONSTRAINT pk_shade_specs    PRIMARY KEY (spec_id),
   CONSTRAINT fk_spec_shade     FOREIGN KEY (shade_id) REFERENCES paint_shades(shade_id) ON DELETE CASCADE,
   CONSTRAINT uq_shade_spec_tag UNIQUE (shade_id, spec_tag)
);

CREATE SEQUENCE seq_shade_spec_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_shade_specs_bi
BEFORE INSERT ON shade_specs
FOR EACH ROW
WHEN (NEW.spec_id IS NULL)
BEGIN
   :NEW.spec_id := seq_shade_spec_id.NEXTVAL;
END;
/


-- ========================
--  5. CUSTOMERS
-- ========================
PROMPT Creating table CUSTOMERS...
CREATE TABLE customers (
   customer_id    NUMBER(10)     NOT NULL,
   full_name      VARCHAR2(200)  NOT NULL,
   phone          VARCHAR2(20)   NOT NULL,
   address        VARCHAR2(500),
   customer_type  VARCHAR2(30)   DEFAULT 'Retail',
   created_at     DATE           DEFAULT SYSDATE NOT NULL,
   CONSTRAINT pk_customers       PRIMARY KEY (customer_id),
   CONSTRAINT ck_customer_type   CHECK (customer_type IN ('Retail','Contractor','Wholesale'))
);

CREATE SEQUENCE seq_customer_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_customers_bi
BEFORE INSERT ON customers
FOR EACH ROW
WHEN (NEW.customer_id IS NULL)
BEGIN
   :NEW.customer_id := seq_customer_id.NEXTVAL;
END;
/

CREATE INDEX idx_customer_phone ON customers(phone);


-- ========================
--  6. ORDERS
-- ========================
PROMPT Creating table ORDERS...
CREATE TABLE orders (
   order_id       NUMBER(10)     NOT NULL,
   order_number   VARCHAR2(20)   NOT NULL,
   customer_id    NUMBER(10)     NOT NULL,
   order_date     DATE           DEFAULT SYSDATE NOT NULL,
   total_amount   NUMBER(14,2)   DEFAULT 0 NOT NULL,
   order_status   VARCHAR2(30)   DEFAULT 'Placed',
   CONSTRAINT pk_orders          PRIMARY KEY (order_id),
   CONSTRAINT uq_order_number    UNIQUE (order_number),
   CONSTRAINT fk_order_customer  FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
   CONSTRAINT ck_order_status    CHECK (order_status IN ('Placed','Processing','Shipped','Delivered','Cancelled'))
);

CREATE SEQUENCE seq_order_id START WITH 1000 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_orders_bi
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
   IF :NEW.order_id IS NULL THEN
      :NEW.order_id := seq_order_id.NEXTVAL;
   END IF;
   IF :NEW.order_number IS NULL THEN
      :NEW.order_number := 'ORD-' || LPAD(TO_CHAR(:NEW.order_id), 4, '0');
   END IF;
END;
/

CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_date     ON orders(order_date);


-- ========================
--  7. ORDER_ITEMS
-- ========================
PROMPT Creating table ORDER_ITEMS...
CREATE TABLE order_items (
   item_id        NUMBER(10)     NOT NULL,
   order_id       NUMBER(10)     NOT NULL,
   product_id     NUMBER(10)     NOT NULL,
   shade_id       NUMBER(10),                        -- NULL for non-paint products
   quantity       NUMBER(10)     NOT NULL,
   unit_price     NUMBER(12,2)   NOT NULL,
   line_total     NUMBER(14,2)   NOT NULL,
   custom_shade_name VARCHAR2(100),                   -- Readable label for custom paint
   CONSTRAINT pk_order_items      PRIMARY KEY (item_id),
   CONSTRAINT fk_oi_order         FOREIGN KEY (order_id)   REFERENCES orders(order_id)       ON DELETE CASCADE,
   CONSTRAINT fk_oi_product       FOREIGN KEY (product_id) REFERENCES products(product_id),
   CONSTRAINT fk_oi_shade         FOREIGN KEY (shade_id)   REFERENCES paint_shades(shade_id),
   CONSTRAINT ck_oi_qty           CHECK (quantity > 0),
   CONSTRAINT ck_oi_price         CHECK (unit_price >= 0)
);

CREATE SEQUENCE seq_order_item_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_order_items_bi
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
   IF :NEW.item_id IS NULL THEN
      :NEW.item_id := seq_order_item_id.NEXTVAL;
   END IF;
   :NEW.line_total := :NEW.quantity * :NEW.unit_price;
END;
/

CREATE INDEX idx_oi_order   ON order_items(order_id);
CREATE INDEX idx_oi_product ON order_items(product_id);


-- ========================
--  8. INVENTORY_LOGS
-- ========================
PROMPT Creating table INVENTORY_LOGS...
CREATE TABLE inventory_logs (
   log_id         NUMBER(10)     NOT NULL,
   product_id     NUMBER(10)     NOT NULL,
   movement_type  VARCHAR2(10)   NOT NULL,
   quantity       NUMBER(10)     NOT NULL,
   reference_type VARCHAR2(50),                       -- 'Customer Order', 'Supplier Restock', etc.
   reference_id   VARCHAR2(30),                       -- ORD-xxxx, RS-xxx, CON-xxxx
   notes          VARCHAR2(500),
   movement_date  DATE           DEFAULT SYSDATE NOT NULL,
   CONSTRAINT pk_inventory_logs    PRIMARY KEY (log_id),
   CONSTRAINT fk_invlog_product    FOREIGN KEY (product_id) REFERENCES products(product_id),
   CONSTRAINT ck_movement_type     CHECK (movement_type IN ('IN','OUT'))
);

CREATE SEQUENCE seq_inv_log_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_inv_logs_bi
BEFORE INSERT ON inventory_logs
FOR EACH ROW
WHEN (NEW.log_id IS NULL)
BEGIN
   :NEW.log_id := seq_inv_log_id.NEXTVAL;
END;
/

CREATE INDEX idx_invlog_product ON inventory_logs(product_id);
CREATE INDEX idx_invlog_date    ON inventory_logs(movement_date);


-- ========================
--  9. SUPPLIERS
-- ========================
PROMPT Creating table SUPPLIERS...
CREATE TABLE suppliers (
   supplier_id    NUMBER(10)     NOT NULL,
   supplier_name  VARCHAR2(200)  NOT NULL,
   contact_phone  VARCHAR2(20),
   contact_email  VARCHAR2(100),
   address        VARCHAR2(500),
   created_at     DATE           DEFAULT SYSDATE NOT NULL,
   CONSTRAINT pk_suppliers PRIMARY KEY (supplier_id)
);

CREATE SEQUENCE seq_supplier_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER trg_suppliers_bi
BEFORE INSERT ON suppliers
FOR EACH ROW
WHEN (NEW.supplier_id IS NULL)
BEGIN
   :NEW.supplier_id := seq_supplier_id.NEXTVAL;
END;
/


-- ============================================================
--  TRIGGER: Auto-deduct stock & log inventory on new order item
-- ============================================================
PROMPT Creating trigger TRG_AFTER_ORDER_ITEM...
CREATE OR REPLACE TRIGGER trg_after_order_item
AFTER INSERT ON order_items
FOR EACH ROW
DECLARE
   v_order_num VARCHAR2(20);
BEGIN
   -- Deduct stock
   UPDATE products
      SET stock_qty = stock_qty - :NEW.quantity
     WHERE product_id = :NEW.product_id;

   -- Retrieve order number from orders table
   BEGIN
      SELECT order_number INTO v_order_num
        FROM orders
       WHERE order_id = :NEW.order_id;
   EXCEPTION
      WHEN NO_DATA_FOUND THEN
         v_order_num := 'ORD-' || LPAD(TO_CHAR(:NEW.order_id), 4, '0');
   END;

   -- Log the movement
   INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes)
   VALUES (
      :NEW.product_id,
      'OUT',
      :NEW.quantity,
      'Customer Order',
      v_order_num,
      'Auto-deducted via order item insert'
   );
END;
/


-- ============================================================
--  TRIGGER: Recalculate order total when items change
-- ============================================================
PROMPT Creating trigger TRG_UPDATE_ORDER_TOTAL...
CREATE OR REPLACE TRIGGER trg_update_order_total
AFTER INSERT OR UPDATE OR DELETE ON order_items
FOR EACH ROW
BEGIN
   IF INSERTING THEN
      UPDATE orders
         SET total_amount = total_amount + :NEW.line_total
       WHERE order_id = :NEW.order_id;
   ELSIF UPDATING THEN
      IF :NEW.order_id <> :OLD.order_id THEN
         -- Subtract from old order
         UPDATE orders
            SET total_amount = total_amount - :OLD.line_total
          WHERE order_id = :OLD.order_id;
         -- Add to new order
         UPDATE orders
            SET total_amount = total_amount + :NEW.line_total
          WHERE order_id = :NEW.order_id;
      ELSE
         -- Adjust value on same order
         UPDATE orders
            SET total_amount = total_amount + (:NEW.line_total - :OLD.line_total)
          WHERE order_id = :NEW.order_id;
      END IF;
   ELSIF DELETING THEN
      UPDATE orders
         SET total_amount = total_amount - :OLD.line_total
       WHERE order_id = :OLD.order_id;
   END IF;
END;
/


-- ============================================================
--  SEED DATA — Categories
-- ============================================================
PROMPT Inserting seed data into CATEGORIES...
INSERT INTO categories (category_name, description) VALUES ('Paints',         'All paint products including emulsion, weathercoat, and wood polish');
INSERT INTO categories (category_name, description) VALUES ('Hardware',       'General hardware supplies — nails, screws, fasteners');
INSERT INTO categories (category_name, description) VALUES ('Iron & Steel',   'Iron rods, steel plates, and structural metal');
INSERT INTO categories (category_name, description) VALUES ('Tools',          'Hand tools, power tool accessories, drill bits');
INSERT INTO categories (category_name, description) VALUES ('Construction',   'Cement, concrete, and construction material');
COMMIT;


-- ============================================================
--  SEED DATA — Products  (matches products.html & customer.html)
-- ============================================================
PROMPT Inserting seed data into PRODUCTS...
INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('PNT-001', 'Premium Emulsion Paint (1L)',  1, 'Berger',      1250,  24, 10, 'litre');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('PNT-002', 'Weathercoat Shield (4L)',      1, 'Berger',      3400,  15,  8, 'litre');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('PNT-045', 'Gloss Wood Polish (500ml)',    1, 'Asian Paints',  320,   8, 10, 'ml');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('HDW-011', 'Galvanized Nails (1kg)',       2, 'RFL',           180, 120, 25, 'kg');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('HDW-012', 'Premium Screws Pack (100pc)',  2, 'RFL',           120, 250, 50, 'pack');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('HDW-089', 'Drill Bit Set (13pcs)',        4, 'Bosch',         280,  15,  8, 'set');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('TL-001',  'Heavy Duty Steel Hammer',      4, 'Bosch',         350,  35, 10, 'pcs');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('IRN-210', 'BSRM Iron Rod 10mm (6m)',      3, 'BSRM',         450,  42, 20, 'pcs');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('IRN-211', 'Steel Plate 4x8 ft',           3, 'BSRM',        2800,  10, 10, 'pcs');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('CST-001', 'Ready Mix Concrete (25kg)',    5, 'Lafarge',       850,  50, 15, 'bag');

INSERT INTO products (sku, product_name, category_id, brand, unit_price, stock_qty, safety_level, unit_of_measure)
VALUES ('CST-002', 'Lafarge Cement (50kg)',         5, 'Lafarge',       650,   0, 15, 'bag');

COMMIT;


-- ============================================================
--  SEED DATA — Paint Shades  (first 4 base + 10 steel = 14 sample rows)
-- ============================================================
PROMPT Inserting seed data into PAINT_SHADES...
INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-001', 'Crimson Blaze',           'CB-001', 'Acrylic', 'rgb(244,63,94)',    'Red 40% + Yellow 15% + White 45%',        'Matte');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-002', 'Sunset Gold',             'SG-002', 'Acrylic', 'rgb(245,158,11)',   'Yellow 60% + Red 15% + White 25%',        'Semi-Gloss');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-003', 'Mint Leaf',               'ML-003', 'Acrylic', 'rgb(16,185,129)',   'Green 50% + Yellow 20% + White 30%',      'Satin');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-004', 'Ocean Deep',              'OD-004', 'Acrylic', 'rgb(59,130,246)',   'Blue 70% + Black 15% + White 15%',        'Glossy');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-091', 'Industrial Steel',        'IS-901', 'Steel',   'rgb(112,128,144)',  'Silver 75% + Black 15% + Blue 10%',       'Metallic');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-092', 'Galvanized Gray',         'GG-902', 'Steel',   'rgb(142,145,143)',  'Zinc 80% + Black 12% + Yellow 8%',        'Matte Metallic');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-093', 'Brushed Chrome',          'BC-903', 'Steel',   'rgb(192,192,192)',  'Silver 85% + White 10% + Black 5%',       'Satin Metallic');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-094', 'Gunmetal Blue',           'GB-904', 'Steel',   'rgb(69,85,96)',     'Blue 60% + Black 30% + White 10%',        'Glossy Metallic');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-095', 'Rusty Iron',              'RI-905', 'Steel',   'rgb(183,115,87)',   'Red Oxide 50% + Brown 30% + Yellow 20%',  'Textured');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-096', 'Copper Glow',             'CG-906', 'Steel',   'rgb(184,115,51)',   'Gold 65% + Red 25% + Yellow 10%',         'Semi-Gloss Metallic');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-097', 'Antique Bronze',          'AB-907', 'Steel',   'rgb(102,93,76)',    'Yellow 45% + Brown 35% + Black 20%',      'Matte Bronze');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-098', 'Titanium Silver',         'TS-908', 'Steel',   'rgb(168,175,175)',  'Silver 90% + White 5% + Black 5%',        'High Gloss');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-099', 'Carbon Black Metallic',   'CB-909', 'Steel',   'rgb(43,44,46)',     'Black 85% + Silver 15%',                  'Satin Metallic');

INSERT INTO paint_shades (shade_code, shade_name, formula_code, category, rgb_value, mix_formula, finish_type)
VALUES ('SH-100', 'Stainless Steel',         'SS-910', 'Steel',   'rgb(219,222,224)',  'Silver 80% + Blue 10% + White 10%',       'High Gloss Metallic');

COMMIT;


-- ============================================================
--  SEED DATA — Shade Specs  (sample tags for the 4 base Acrylic shades)
-- ============================================================
PROMPT Inserting seed data into SHADE_SPECS...
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (1, 'Matte Finish');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (1, 'Eco-friendly');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (1, 'Weather Resistant');

INSERT INTO shade_specs (shade_id, spec_tag) VALUES (2, 'Semi-gloss finish');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (2, 'Fade Resistant');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (2, 'Interior/Exterior');

INSERT INTO shade_specs (shade_id, spec_tag) VALUES (3, 'Satin finish');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (3, 'UV Protected');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (3, 'Low VOC');

INSERT INTO shade_specs (shade_id, spec_tag) VALUES (4, 'Gloss finish');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (4, 'Premium Quality');
INSERT INTO shade_specs (shade_id, spec_tag) VALUES (4, 'Long-lasting');

COMMIT;


-- ============================================================
--  SEED DATA — Suppliers
-- ============================================================
PROMPT Inserting seed data into SUPPLIERS...
INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address)
VALUES ('Berger Paints Bangladesh Ltd.', '01711-000001', 'supply@bergerbd.com', 'Dhaka, Bangladesh');

INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address)
VALUES ('Asian Paints BD', '01711-000002', 'orders@asianpaintsbd.com', 'Chattogram, Bangladesh');

INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address)
VALUES ('BSRM Steels Ltd.', '01711-000003', 'sales@bsrm.com', 'Chittagong, Bangladesh');

INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address)
VALUES ('RFL Hardware Division', '01711-000004', 'hardware@rfl.com.bd', 'Dhaka, Bangladesh');

INSERT INTO suppliers (supplier_name, contact_phone, contact_email, address)
VALUES ('Lafarge Holcim Bangladesh', '01711-000005', 'orders@lafarge.com.bd', 'Dhaka, Bangladesh');

COMMIT;


-- ============================================================
--  SEED DATA — Sample Customer
-- ============================================================
PROMPT Inserting seed data into CUSTOMERS...
INSERT INTO customers (full_name, phone, address, customer_type)
VALUES ('Md. Rahim Uddin', '01712345678', '45 Mirpur Road, Dhaka-1216', 'Retail');

INSERT INTO customers (full_name, phone, address, customer_type)
VALUES ('Karim Construction Ltd.', '01898765432', 'Banani DOHS, Dhaka-1206', 'Contractor');

COMMIT;


-- ============================================================
--  SEED DATA — Sample Inventory Logs  (matches inventory.html)
-- ============================================================
PROMPT Inserting seed data into INVENTORY_LOGS...
INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (1, 'IN',  30, 'Supplier Restock', 'RS-221',    'Supplier restocking order #RS-221',       TO_DATE('2025-06-24','YYYY-MM-DD'));

INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (1, 'OUT',  6, 'Customer Order',   'ORD-1024',  'Customer order #ORD-1024',                TO_DATE('2025-06-24','YYYY-MM-DD'));

INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (4, 'IN',  50, 'Supplier Restock', NULL,         'Wholesale shipment',                      TO_DATE('2025-06-23','YYYY-MM-DD'));

INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (8, 'OUT',  8, 'Contractor Order', 'CON-0055',  'Contractor order #CON-0055',              TO_DATE('2025-06-23','YYYY-MM-DD'));

INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (6, 'IN',  20, 'Supplier Restock', NULL,         'New inventory arrival',                   TO_DATE('2025-06-22','YYYY-MM-DD'));

INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (3, 'OUT', 12, 'Customer Order',   'ORD-1019',  'Customer order #ORD-1019',                TO_DATE('2025-06-22','YYYY-MM-DD'));

INSERT INTO inventory_logs (product_id, movement_type, quantity, reference_type, reference_id, notes, movement_date)
VALUES (9, 'OUT',  5, 'Customer Order',   NULL,         'Construction project delivery',           TO_DATE('2025-06-21','YYYY-MM-DD'));

COMMIT;


-- ============================================================
--  VERIFICATION — Quick row counts
-- ============================================================
PROMPT Seeding complete. Running verification row counts...
SELECT 'CATEGORIES'     AS table_name, COUNT(*) AS row_count FROM categories     UNION ALL
SELECT 'PRODUCTS',                     COUNT(*)              FROM products       UNION ALL
SELECT 'PAINT_SHADES',                 COUNT(*)              FROM paint_shades   UNION ALL
SELECT 'SHADE_SPECS',                  COUNT(*)              FROM shade_specs    UNION ALL
SELECT 'CUSTOMERS',                    COUNT(*)              FROM customers      UNION ALL
SELECT 'ORDERS',                       COUNT(*)              FROM orders         UNION ALL
SELECT 'ORDER_ITEMS',                  COUNT(*)              FROM order_items    UNION ALL
SELECT 'INVENTORY_LOGS',              COUNT(*)              FROM inventory_logs UNION ALL
SELECT 'SUPPLIERS',                    COUNT(*)              FROM suppliers;

PROMPT ============================================================
PROMPT  DATABASE SCHEMA AND SEED DATA SETUP SUCCESSFUL
PROMPT ============================================================

-- ============================================================
--  END OF SCHEMA
-- ============================================================
