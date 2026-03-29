-- ============================================================
--  Harishji Pav-Vada Admin Panel — MySQL Schema
--  Database : harishji_db
--  Encoding : utf8mb4
--  Created  : 2024
-- ============================================================

CREATE DATABASE IF NOT EXISTS harishji_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE harishji_db;

-- ============================================================
-- 1. ADMIN LOGIN
-- ============================================================
CREATE TABLE admin_users (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username     VARCHAR(60)     NOT NULL UNIQUE,
  password     VARCHAR(255)    NOT NULL,          -- bcrypt hash
  full_name    VARCHAR(100)    NOT NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin  (password = "admin123" — change immediately)
INSERT INTO admin_users (username, password, full_name)
VALUES (
  'admin',
  '$2y$10$J48S5UmjVlmbVwn.IUu5POlvcdXfuNjfiY6kV2AsFQCjld4FXaxLG',
  'Harishji'
);

-- ============================================================
-- 2. LOCATIONS  (main shop + Pipariya Medi)
-- ============================================================
CREATE TABLE locations (
  id           TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)      NOT NULL,
  address      VARCHAR(200)     NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO locations (name, address) VALUES
  ('Main Shop',     'Harishji Pav-Vada — main location'),
  ('Pipariya Medi', 'Pipariya Medi branch');

-- ============================================================
-- 3. PRODUCTS  (pav-vada, khichdi, sandwich …)
-- ============================================================
CREATE TABLE products (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100)      NOT NULL,
  unit         VARCHAR(20)       NOT NULL DEFAULT 'piece',  -- piece / plate / kg
  sale_price   DECIMAL(8,2)      NOT NULL DEFAULT 0.00,
  is_active    TINYINT(1)        NOT NULL DEFAULT 1,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO products (name, unit, sale_price) VALUES
  ('Pav-Vada',  'piece', 10.00),
  ('Khichdi',   'plate', 30.00),
  ('Sandwich',  'piece', 20.00);

-- ============================================================
-- 4. VENDORS  (Mandalor / Munim Ji + others)
-- ============================================================
CREATE TABLE vendors (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100)      NOT NULL,
  contact      VARCHAR(15)       NULL,
  note         VARCHAR(200)      NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO vendors (name, note) VALUES
  ('Mandalor (Munim Ji)', 'Supplies masala, chatni powder, sev');

-- ============================================================
-- 5. INVENTORY / STOCK
-- ============================================================
CREATE TABLE stock_items (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100)      NOT NULL,
  category     ENUM(
                 'masala','chatni','sev','flour','oil',
                 'vegetable','packaging','other'
               )                 NOT NULL DEFAULT 'other',
  unit         VARCHAR(20)       NOT NULL DEFAULT 'kg',
  qty_in_hand  DECIMAL(10,3)     NOT NULL DEFAULT 0.000,
  low_stock_at DECIMAL(10,3)     NOT NULL DEFAULT 1.000,  -- alert threshold
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock adjustment log (every add / reduce)
CREATE TABLE stock_transactions (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  item_id      INT UNSIGNED      NOT NULL,
  txn_type     ENUM('in','out','adjustment') NOT NULL,
  qty          DECIMAL(10,3)     NOT NULL,
  note         VARCHAR(200)      NULL,
  txn_date     DATE              NOT NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (item_id) REFERENCES stock_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. MASALA / RAW MATERIAL PURCHASES  (from vendors)
-- ============================================================
CREATE TABLE purchases (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  vendor_id    SMALLINT UNSIGNED NOT NULL,
  item_name    VARCHAR(100)      NOT NULL,          -- masala / chatni powder / sev
  qty          DECIMAL(10,3)     NOT NULL,
  unit         VARCHAR(20)       NOT NULL DEFAULT 'kg',
  rate         DECIMAL(8,2)      NOT NULL,          -- price per unit
  total_amount DECIMAL(10,2)     GENERATED ALWAYS AS (qty * rate) STORED,
  purchase_date DATE             NOT NULL,
  payment_mode ENUM('cash','upi','credit') NOT NULL DEFAULT 'cash',
  is_paid      TINYINT(1)        NOT NULL DEFAULT 1,
  note         VARCHAR(200)      NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. DAILY EXPENSES (Kharch)
-- ============================================================
CREATE TABLE expense_categories (
  id           TINYINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)       NOT NULL,   -- fuel, rent, salary, misc…
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO expense_categories (name) VALUES
  ('Fuel / Gas'),
  ('Rent'),
  ('Electricity'),
  ('Labour / Daily wage'),
  ('Packaging material'),
  ('Transport'),
  ('Miscellaneous');

CREATE TABLE expenses (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  location_id  TINYINT UNSIGNED  NOT NULL,
  category_id  TINYINT UNSIGNED  NOT NULL,
  amount       DECIMAL(10,2)     NOT NULL,
  expense_date DATE              NOT NULL,
  description  VARCHAR(255)      NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. SALES & INCOME
-- ============================================================
CREATE TABLE sales (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  location_id  TINYINT UNSIGNED  NOT NULL,
  sale_date    DATE              NOT NULL,
  product_id   SMALLINT UNSIGNED NOT NULL,
  qty_sold     DECIMAL(8,2)      NOT NULL,
  unit_price   DECIMAL(8,2)      NOT NULL,
  total_amount DECIMAL(10,2)     GENERATED ALWAYS AS (qty_sold * unit_price) STORED,
  payment_mode ENUM('cash','upi','other') NOT NULL DEFAULT 'cash',
  note         VARCHAR(200)      NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily cash summary (optional quick totals per location per day)
CREATE TABLE daily_summary (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  summary_date   DATE          NOT NULL,
  location_id    TINYINT UNSIGNED NOT NULL,
  total_sales    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_expenses DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  net_profit     DECIMAL(10,2) GENERATED ALWAYS AS (total_sales - total_expenses) STORED,
  note           VARCHAR(200)  NULL,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_date_loc (summary_date, location_id),
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. EMPLOYEES & SALARY
-- ============================================================
CREATE TABLE employees (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100)  NOT NULL,
  phone          VARCHAR(15)   NULL,
  role           VARCHAR(60)   NULL,             -- cook, helper, cashier…
  monthly_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  join_date      DATE          NULL,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE salary_payments (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  employee_id  INT UNSIGNED      NOT NULL,
  pay_month    CHAR(7)           NOT NULL,        -- 'YYYY-MM'
  amount_paid  DECIMAL(10,2)     NOT NULL,
  payment_date DATE              NOT NULL,
  payment_mode ENUM('cash','upi','bank') NOT NULL DEFAULT 'cash',
  is_advance   TINYINT(1)        NOT NULL DEFAULT 0,
  note         VARCHAR(200)      NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. PENDING EMI & DUES
-- ============================================================
CREATE TABLE dues (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  due_type      ENUM('emi','vendor_due','loan','other') NOT NULL,
  party_name    VARCHAR(100)  NOT NULL,           -- bank / vendor / person
  description   VARCHAR(255)  NULL,
  total_amount  DECIMAL(12,2) NOT NULL,
  amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_left   DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
  due_date      DATE          NULL,               -- next payment date / deadline
  is_cleared    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment log for each due
CREATE TABLE due_payments (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  due_id       INT UNSIGNED  NOT NULL,
  amount       DECIMAL(12,2) NOT NULL,
  payment_date DATE          NOT NULL,
  payment_mode ENUM('cash','upi','bank','cheque') NOT NULL DEFAULT 'cash',
  note         VARCHAR(200)  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (due_id) REFERENCES dues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. USEFUL VIEWS  (for reports module)
-- ============================================================

-- Monthly income vs expense per location
CREATE OR REPLACE VIEW vw_monthly_report AS
SELECT
  DATE_FORMAT(s.sale_date, '%Y-%m')  AS report_month,
  l.name                             AS location,
  ROUND(SUM(s.total_amount), 2)      AS total_income,
  ROUND(
    (SELECT COALESCE(SUM(e.amount), 0)
       FROM expenses e
      WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = DATE_FORMAT(s.sale_date, '%Y-%m')
        AND e.location_id = s.location_id
    ), 2)                            AS total_expense,
  ROUND(
    SUM(s.total_amount) -
    (SELECT COALESCE(SUM(e.amount), 0)
       FROM expenses e
      WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = DATE_FORMAT(s.sale_date, '%Y-%m')
        AND e.location_id = s.location_id
    ), 2)                            AS net_profit
FROM sales s
JOIN locations l ON l.id = s.location_id
GROUP BY report_month, s.location_id
ORDER BY report_month DESC, l.name;

-- Low stock alert view
CREATE OR REPLACE VIEW vw_low_stock AS
SELECT
  id, name, category, unit,
  qty_in_hand, low_stock_at
FROM stock_items
WHERE qty_in_hand <= low_stock_at
ORDER BY qty_in_hand ASC;

-- Pending dues summary
CREATE OR REPLACE VIEW vw_pending_dues AS
SELECT
  id, due_type, party_name, description,
  total_amount, amount_paid, amount_left,
  due_date, is_cleared
FROM dues
WHERE is_cleared = 0
ORDER BY due_date ASC;

-- ============================================================
-- END OF SCHEMA
-- ============================================================