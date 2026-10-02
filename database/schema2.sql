-- =========================================================
-- FRESH SCENTS — Mfumo wa Ndani wa Duka (Internal Shop Management System)
-- Database Schema (MySQL / MariaDB)
--
-- MFUMO HUU NI KWA MATUMIZI YA NDANI TU (Admin/Wafanyakazi).
-- HAUNA sehemu ya wateja, akaunti za wateja, wala maagizo ya mtandaoni.
-- =========================================================

CREATE DATABASE IF NOT EXISTS fresh_scents_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE fresh_scents_db;

-- ---------------------------------------------------------
-- 1. WATUMIAJI WA MFUMO (ADMINS / WAFANYAKAZI)
--    role: super_admin/admin = ufikiaji kamili, worker = ruhusa chache
-- ---------------------------------------------------------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(120) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    security_question VARCHAR(255) NOT NULL,
    security_answer_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','worker') NOT NULL DEFAULT 'admin',
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 2. AINA ZA BIDHAA (CATEGORIES)
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES ('Wanaume'),('Wanawake'),('Unisex'),('Body Spray'),('Mafuta ya Kunukia');

-- ---------------------------------------------------------
-- 3. BIDHAA (PRODUCTS / PERFUMES)
-- ---------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    description TEXT,
    category_id INT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 4. UKUBWA (ML) NA BEI KWA KILA BIDHAA (PRODUCT VARIANTS)
--    Bidhaa moja inaweza kuwa na ML na bei nyingi (30ml, 50ml, 100ml, n.k.)
-- ---------------------------------------------------------
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    ml INT NOT NULL COMMENT 'Ujazo kwa mililita',
    price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2) DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 5. MAUZO (SALES) — Rekodi za mauzo zinazowekwa na Admin/Mfanyakazi
--    HAKUNA uhusiano na akaunti za wateja au maagizo ya mtandaoni.
-- ---------------------------------------------------------
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_code VARCHAR(20) NOT NULL UNIQUE,
    customer_name VARCHAR(120) DEFAULT NULL COMMENT 'Hiari - kwa rekodi tu, siyo akaunti',
    customer_phone VARCHAR(30) DEFAULT NULL COMMENT 'Hiari - kwa rekodi tu',
    payment_method ENUM('cash','mobile_money','bank','other') NOT NULL DEFAULT 'cash',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    recorded_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    variant_id INT DEFAULT NULL,
    product_name VARCHAR(150) NOT NULL,
    ml INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 6. WAFANYAKAZI (EMPLOYEES) NA MALIPO (PAYROLL)
--    (Hii ni tofauti na "admins" - hii ni rekodi ya HR/mishahara,
--     "admins" ni watumiaji wa mfumo wa kompyuta)
-- ---------------------------------------------------------
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    position VARCHAR(80) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    hired_date DATE DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE salary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    pay_month VARCHAR(20) NOT NULL COMMENT 'Mfano: Agosti 2026',
    pay_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    paid_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (paid_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 7. MATUMIZI (EXPENSES)
-- ---------------------------------------------------------
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(80) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 8. BIDHAA ZINAZOINGIA STOO (STOCK IN)
-- ---------------------------------------------------------
CREATE TABLE stock_ins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    quantity INT NOT NULL,
    cost_price DECIMAL(12,2) DEFAULT 0,
    supplier VARCHAR(150) DEFAULT NULL,
    stock_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    added_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 9. BIDHAA ZILIZOHARIBIKA / ZIMEISHA MUDA (DAMAGES / LOSSES)
-- ---------------------------------------------------------
CREATE TABLE damages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT DEFAULT NULL COMMENT 'NULL kama ni kitu kisicho stock (is_stock_item=0)',
    item_name VARCHAR(150) DEFAULT NULL COMMENT 'Jina la kitu kwa vitu visivyo stock (mfano: chupa, kompyuta, samani)',
    is_stock_item TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=bidhaa iliyopo kwenye product_variants, 0=kitu kingine kisicho stock',
    quantity INT NOT NULL DEFAULT 1,
    reason ENUM('expired','damaged','other') NOT NULL DEFAULT 'damaged',
    loss_value DECIMAL(12,2) DEFAULT 0,
    damage_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 10. MIPANGILIO YA DUKA (SETTINGS)
-- ---------------------------------------------------------
CREATE TABLE settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('shop_name', 'FRESH SCENTS'),
('shop_tagline', 'Harufu Nzuri, Hadhi Ya Kifalme'),
('shop_phone', '0621002091'),
('shop_address', 'Mbeya, Tanzania'),
('currency', 'TZS'),
('installed', '0');

-- ---------------------------------------------------------
-- 11. LOGS ZA KUINGIA (LOGIN AUDIT - hiari, kwa usalama)
-- ---------------------------------------------------------
CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    ip_address VARCHAR(60) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ACTIVITY LOG (rejesta ya matukio/vitendo vyote vya mfumo)
-- ---------------------------------------------------------
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    admin_name VARCHAR(120) DEFAULT NULL COMMENT 'Jina la mtumiaji wakati wa kitendo (linabaki hata akifutwa)',
    module VARCHAR(40) NOT NULL COMMENT 'mfano: sales, stock, damages, payroll, expenses, auth, products, admins',
    action VARCHAR(30) NOT NULL COMMENT 'mfano: create, update, delete, login, logout',
    description VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- SAMPLE PRODUCTS (mifano ya bidhaa - unaweza kufuta)
-- ---------------------------------------------------------
INSERT INTO products (name, brand, description, category_id, image, status) VALUES
('Fresh Scents Royal Oud', 'Fresh Scents', 'Harufu ya kifalme yenye mchanganyiko wa oud na vanilla, hukaa muda mrefu.', 1, NULL, 'active'),
('Fresh Scents Blue Ice', 'Fresh Scents', 'Harufu safi na yenye baridi, nzuri kwa matumizi ya kila siku.', 1, NULL, 'active'),
('Fresh Scents Rose Elegance', 'Fresh Scents', 'Harufu ya kike yenye mchanganyiko wa rose na jasmine.', 2, NULL, 'active');

INSERT INTO product_variants (product_id, ml, price, stock_qty) VALUES
(1, 30, 22000, 20), (1, 50, 32000, 18), (1, 100, 45000, 25),
(2, 50, 28000, 30), (2, 100, 38000, 14),
(3, 30, 24000, 22), (3, 75, 38000, 20);
