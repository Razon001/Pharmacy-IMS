-- ============================================================
--  PHARMACY MANAGEMENT & BILLING SYSTEM  -  DATABASE SCHEMA
--  MySQL 5.7+ / 8.0  |  Engine: InnoDB  |  Charset: utf8mb4
-- ============================================================
--  IMPORTANT: After importing this file, open  install.php  once
--  in your browser. It creates the default admin login:
--       username: admin      password: admin123
--  (the admin password is created with PHP password_hash(), which
--   MySQL cannot generate, hence the separate installer step)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pharmacy_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pharmacy_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  USERS  (staff accounts / roles)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `email`      VARCHAR(120) DEFAULT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `role`       ENUM('admin','pharmacist','cashier') NOT NULL DEFAULT 'cashier',
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login` DATETIME     DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  CATEGORIES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  SUPPLIERS / VENDORS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `name`           VARCHAR(120) NOT NULL,
  `contact_person` VARCHAR(120) DEFAULT NULL,
  `phone`          VARCHAR(30)  DEFAULT NULL,
  `email`          VARCHAR(120) DEFAULT NULL,
  `address`        VARCHAR(255) DEFAULT NULL,
  `gst_no`         VARCHAR(60)  DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  CUSTOMERS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(120) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `email`      VARCHAR(120) DEFAULT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  MEDICINES  (product master)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `medicines`;
CREATE TABLE `medicines` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(150) NOT NULL,
  `generic_name`  VARCHAR(150) DEFAULT NULL,
  `category_id`   INT DEFAULT NULL,
  `barcode`       VARCHAR(80) DEFAULT NULL,
  `manufacturer`  VARCHAR(150) DEFAULT NULL,
  `unit`          VARCHAR(40) DEFAULT 'pcs',
  `rack`          VARCHAR(40) DEFAULT NULL,
  `reorder_level` INT NOT NULL DEFAULT 10,
  `prescription_required` TINYINT(1) NOT NULL DEFAULT 0,
  `description`   TEXT DEFAULT NULL,
  `status`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_cat` (`category_id`),
  KEY `idx_barcode` (`barcode`),
  CONSTRAINT `fk_med_cat` FOREIGN KEY (`category_id`)
      REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  BATCHES  (stock lots - enables expiry + FEFO tracking)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `medicine_id`    INT NOT NULL,
  `batch_no`       VARCHAR(80) DEFAULT NULL,
  `supplier_id`    INT DEFAULT NULL,
  `expiry_date`    DATE DEFAULT NULL,
  `quantity`       INT NOT NULL DEFAULT 0,
  `purchase_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `sale_price`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_med` (`medicine_id`),
  KEY `idx_expiry` (`expiry_date`),
  CONSTRAINT `fk_batch_med` FOREIGN KEY (`medicine_id`)
      REFERENCES `medicines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batch_sup` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  PURCHASES  (stock-in header)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`    VARCHAR(50) NOT NULL,
  `supplier_id`   INT DEFAULT NULL,
  `user_id`       INT DEFAULT NULL,
  `sub_total`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `tax`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid`          DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_status` ENUM('paid','partial','due') NOT NULL DEFAULT 'paid',
  `note`          VARCHAR(255) DEFAULT NULL,
  `purchase_date` DATE NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sup` (`supplier_id`),
  CONSTRAINT `fk_pur_sup` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  PURCHASE ITEMS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id`    INT NOT NULL,
  `medicine_id`    INT NOT NULL,
  `batch_id`       INT DEFAULT NULL,
  `batch_no`       VARCHAR(80) DEFAULT NULL,
  `expiry_date`    DATE DEFAULT NULL,
  `quantity`       INT NOT NULL,
  `purchase_price` DECIMAL(12,2) NOT NULL,
  `sale_price`     DECIMAL(12,2) NOT NULL,
  `subtotal`       DECIMAL(12,2) NOT NULL,
  CONSTRAINT `fk_pi_pur` FOREIGN KEY (`purchase_id`)
      REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_med` FOREIGN KEY (`medicine_id`)
      REFERENCES `medicines`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  SALES  (billing header)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`     VARCHAR(50) NOT NULL UNIQUE,
  `customer_id`    INT DEFAULT NULL,
  `user_id`        INT DEFAULT NULL,
  `sub_total`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_type`  ENUM('flat','percent') NOT NULL DEFAULT 'flat',
  `tax`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total`          DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `change_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `profit`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_method` ENUM('cash','card','upi','other') NOT NULL DEFAULT 'cash',
  `doctor_name`    VARCHAR(120) DEFAULT NULL,
  `note`           VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('completed','returned') NOT NULL DEFAULT 'completed',
  `sale_date`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_cust` (`customer_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_date` (`sale_date`),
  CONSTRAINT `fk_sale_cust` FOREIGN KEY (`customer_id`)
      REFERENCES `customers`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_user` FOREIGN KEY (`user_id`)
      REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  SALE ITEMS  (per batch line, for accurate profit + stock)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id`        INT NOT NULL,
  `medicine_id`    INT NOT NULL,
  `batch_id`       INT DEFAULT NULL,
  `quantity`       INT NOT NULL,
  `price`          DECIMAL(12,2) NOT NULL,
  `cost`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `subtotal`       DECIMAL(12,2) NOT NULL,
  CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`)
      REFERENCES `sales`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_med` FOREIGN KEY (`medicine_id`)
      REFERENCES `medicines`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  STOCK MOVEMENTS  (full audit trail)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `medicine_id` INT NOT NULL,
  `batch_id`    INT DEFAULT NULL,
  `type`        ENUM('in','out','return','adjust') NOT NULL,
  `quantity`    INT NOT NULL,
  `reference`   VARCHAR(50) DEFAULT NULL,
  `note`        VARCHAR(255) DEFAULT NULL,
  `user_id`     INT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_mv_med` (`medicine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  SALE RETURNS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sale_returns`;
CREATE TABLE `sale_returns` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id`    INT NOT NULL,
  `user_id`    INT DEFAULT NULL,
  `amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ret_sale` FOREIGN KEY (`sale_id`)
      REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  PRESCRIPTIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT DEFAULT NULL,
  `patient_name` VARCHAR(120) DEFAULT NULL,
  `doctor_name` VARCHAR(120) DEFAULT NULL,
  `notes`       TEXT DEFAULT NULL,
  `file`        VARCHAR(255) DEFAULT NULL,
  `sale_id`     INT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_presc_cust` FOREIGN KEY (`customer_id`)
      REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  SETTINGS (single row)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `pharmacy_name` VARCHAR(150) NOT NULL DEFAULT 'My Pharmacy',
  `address`       VARCHAR(255) DEFAULT NULL,
  `phone`         VARCHAR(60)  DEFAULT NULL,
  `email`         VARCHAR(120) DEFAULT NULL,
  `currency`      VARCHAR(10)  NOT NULL DEFAULT '$',
  `tax_rate`      DECIMAL(5,2) NOT NULL DEFAULT 0,
  `invoice_prefix` VARCHAR(10) NOT NULL DEFAULT 'INV',
  `footer_note`   VARCHAR(255) DEFAULT 'Thank you. Get well soon!'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SEED DATA
-- ============================================================
INSERT INTO `settings`
  (`pharmacy_name`,`address`,`phone`,`email`,`currency`,`tax_rate`,`invoice_prefix`,`footer_note`)
VALUES
  ('HealthPlus Pharmacy','123 Market Street, Downtown','+1 555 010 2030','info@healthplus.test','$',5.00,'INV','Thank you for your visit. Get well soon!');

INSERT INTO `categories` (`name`,`description`) VALUES
  ('Tablets','Oral solid dosage'),
  ('Syrups','Liquid oral medicine'),
  ('Injections','Injectable medicine'),
  ('Antibiotics','Antibacterial drugs'),
  ('Painkillers','Analgesics'),
  ('Vitamins & Supplements','Nutritional products'),
  ('Skin Care','Dermatological products'),
  ('Surgical & Devices','Medical devices and disposables');

INSERT INTO `suppliers` (`name`,`contact_person`,`phone`,`email`,`address`,`gst_no`) VALUES
  ('MediSource Distributors','John Carter','+1 555 111 2222','sales@medisource.test','45 Industrial Ave','GST-1122'),
  ('PharmaTrust Wholesale','Amina Khan','+1 555 333 4444','orders@pharmatrust.test','9 Supply Road','GST-3344'),
  ('Global Health Supplies','Wei Chen','+1 555 555 6666','contact@ghsupplies.test','200 Logistics Park','GST-5566');

INSERT INTO `customers` (`name`,`phone`,`email`,`address`) VALUES
  ('Walk-in Customer','','',''),
  ('Sarah Johnson','+1 555 777 8888','sarah.j@example.test','12 Rose Lane'),
  ('Michael Lee','+1 555 999 0000','m.lee@example.test','88 Oak Street');

INSERT INTO `medicines`
  (`name`,`generic_name`,`category_id`,`barcode`,`manufacturer`,`unit`,`rack`,`reorder_level`,`prescription_required`,`description`)
VALUES
  ('Paracetamol 500mg','Acetaminophen',1,'8901001','Acme Pharma','strip','A1',20,0,'Fever and mild pain relief'),
  ('Amoxicillin 500mg','Amoxicillin',4,'8901002','BioMed Labs','strip','A2',15,1,'Broad-spectrum antibiotic'),
  ('Cough Syrup 100ml','Dextromethorphan',2,'8901003','Acme Pharma','bottle','B1',10,0,'Dry cough relief'),
  ('Ibuprofen 400mg','Ibuprofen',5,'8901004','BioMed Labs','strip','A1',20,0,'Anti-inflammatory pain relief'),
  ('Vitamin C 1000mg','Ascorbic Acid',6,'8901005','NutriCare','bottle','C1',12,0,'Immune support'),
  ('Insulin Injection','Insulin Glargine',3,'8901006','Global Health','vial','R1',8,1,'Diabetes management'),
  ('ORS Sachet','Oral Rehydration Salt',1,'8901007','Acme Pharma','sachet','A3',30,0,'Rehydration therapy'),
  ('Antiseptic Cream 30g','Povidone Iodine',7,'8901008','SkinCare Inc','tube','D1',10,0,'Wound antiseptic');

-- Opening stock batches
INSERT INTO `batches`
  (`medicine_id`,`batch_no`,`supplier_id`,`expiry_date`,`quantity`,`purchase_price`,`sale_price`)
VALUES
  (1,'PCM-A100',1, DATE_ADD(CURDATE(), INTERVAL 18 MONTH),150,0.80,1.50),
  (2,'AMX-B200',2, DATE_ADD(CURDATE(), INTERVAL 12 MONTH), 80,2.50,4.00),
  (3,'CS-C300',1,  DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 40,3.00,5.50),
  (4,'IBU-D400',2, DATE_ADD(CURDATE(), INTERVAL 24 MONTH),120,1.00,2.00),
  (5,'VC-E500',3,  DATE_ADD(CURDATE(), INTERVAL  8 MONTH), 60,4.00,7.00),
  (6,'INS-F600',3, DATE_ADD(CURDATE(), INTERVAL  6 MONTH), 25,9.00,15.00),
  (7,'ORS-G700',1, DATE_ADD(CURDATE(), INTERVAL 20 MONTH),200,0.20,0.50),
  (8,'ANT-H800',2, DATE_ADD(CURDATE(), INTERVAL 15 MONTH), 45,1.80,3.50),
  -- a nearly-expired lot to demonstrate expiry alerts
  (1,'PCM-OLD',1,  DATE_ADD(CURDATE(), INTERVAL 20 DAY),   30,0.80,1.50);
