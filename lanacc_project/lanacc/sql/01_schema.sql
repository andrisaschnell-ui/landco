-- ============================================================
--  LANACC — MySQL Schema
--  File: sql/01_schema.sql
--  Runs automatically on first docker-compose up
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `lanacc`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `lanacc`;

-- ────────────────────────────────────────────────────────────────
--  REFERENCE / LOOKUP TABLES
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `shareholders` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                VARCHAR(120) NOT NULL,
  `email`               VARCHAR(180),
  `phone`               VARCHAR(40),
  `notes`               TEXT,
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `properties` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                VARCHAR(120) NOT NULL,          -- e.g. Casa Luz
  `house_code`          VARCHAR(10)  NOT NULL UNIQUE,   -- H1 H2 H3 H4
  `shareholder_id`      INT UNSIGNED NOT NULL,
  `description`         TEXT,
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                VARCHAR(120) NOT NULL UNIQUE,
  `is_shared`           TINYINT(1)  NOT NULL DEFAULT 0,
  `notes`               TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bank_name`           VARCHAR(60)  NOT NULL,          -- BDO, BIM
  `account_name`        VARCHAR(120) NOT NULL,
  `account_no`          VARCHAR(40),
  `currency`            CHAR(3)      NOT NULL DEFAULT 'MZN',
  `account_type`        VARCHAR(40)  NOT NULL DEFAULT 'current', -- current petty_cash USD MTN
  `property_id`         INT UNSIGNED,
  `active`              TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `employees` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                VARCHAR(150) NOT NULL,
  `initials`            VARCHAR(5),
  `nib`                 VARCHAR(30),                    -- BIM bank account for salary transfer
  `nuit`                VARCHAR(20),                    -- Tax number
  `role`                VARCHAR(80),
  `category`            VARCHAR(80),
  `engagement_date`     DATE,
  `discharge_date`      DATE,
  `base_salary_mzn`     DECIMAL(14,2) NOT NULL DEFAULT 0,
  `food_allowance_mzn`  DECIMAL(14,2) NOT NULL DEFAULT 0,
  `active`              TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rate_date`           DATE NOT NULL,
  `currency_from`       CHAR(3) NOT NULL DEFAULT 'USD',
  `currency_to`         CHAR(3) NOT NULL DEFAULT 'MZN',
  `rate`                DECIMAL(10,4) NOT NULL,
  `source`              VARCHAR(60),
  UNIQUE KEY `uq_rate_date_pair` (`rate_date`, `currency_from`, `currency_to`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  INCOME & EXPENSE TRANSACTIONS
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `income_transactions` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id`         INT UNSIGNED NOT NULL,
  `tx_date`             DATE,
  `month`               TINYINT UNSIGNED NOT NULL,   -- 1..12
  `year`                SMALLINT UNSIGNED NOT NULL,
  `description`         VARCHAR(255),
  `amount_mzn`          DECIMAL(16,4) NOT NULL DEFAULT 0,
  `amount_usd`          DECIMAL(14,4),
  `exchange_rate`       DECIMAL(10,4),
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `expense_transactions` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id`         INT UNSIGNED,                 -- NULL = shared
  `category_id`         INT UNSIGNED,
  `tx_date`             DATE,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `description`         VARCHAR(255),
  `amount_mzn`          DECIMAL(16,4) NOT NULL DEFAULT 0,
  `amount_usd`          DECIMAL(14,4),
  `exchange_rate`       DECIMAL(10,4),
  `is_shared`           TINYINT(1)    NOT NULL DEFAULT 0,
  `payment_method`      VARCHAR(40),                  -- bank cash card
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  BANK TRANSACTIONS
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bank_account_id`     INT UNSIGNED NOT NULL,
  `tx_date`             DATE,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `description`         VARCHAR(255),
  `credit`              DECIMAL(16,4) NOT NULL DEFAULT 0,
  `debit`               DECIMAL(16,4) NOT NULL DEFAULT 0,
  `balance`             DECIMAL(16,4),
  `reference`           VARCHAR(120),
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `petty_cash_transactions` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `account_name`        VARCHAR(80)  NOT NULL,         -- RSDFuel PARKS_FEES USD_CASH MTN_CASH
  `property_id`         INT UNSIGNED,
  `tx_date`             DATE,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `description`         VARCHAR(255),
  `credit`              DECIMAL(14,4) NOT NULL DEFAULT 0,
  `debit`               DECIMAL(14,4) NOT NULL DEFAULT 0,
  `balance`             DECIMAL(14,4),
  `currency`            CHAR(3)      NOT NULL DEFAULT 'MZN',
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  SHAREHOLDER ACCOUNTS
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `shareholder_transactions` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shareholder_id`      INT UNSIGNED NOT NULL,
  `property_id`         INT UNSIGNED,
  `tx_date`             DATE,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `description`         VARCHAR(255),
  `tx_type`             ENUM('income','expense','advance','opening_balance','drawing') NOT NULL,
  `amount_mzn`          DECIMAL(16,4) NOT NULL DEFAULT 0,
  `amount_usd`          DECIMAL(14,4),
  `exchange_rate`       DECIMAL(10,4),
  `is_shared`           TINYINT(1)    NOT NULL DEFAULT 0,
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders`(`id`),
  FOREIGN KEY (`property_id`)   REFERENCES `properties`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `shareholder_balances` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shareholder_id`      INT UNSIGNED NOT NULL,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `opening_balance_mzn` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `total_income_mzn`    DECIMAL(16,4) NOT NULL DEFAULT 0,
  `total_expenses_mzn`  DECIMAL(16,4) NOT NULL DEFAULT 0,
  `closing_balance_mzn` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `opening_balance_usd` DECIMAL(14,4),
  `closing_balance_usd` DECIMAL(14,4),
  `notes`               TEXT,
  `last_updated`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_sh_month_year` (`shareholder_id`, `month`, `year`),
  FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders`(`id`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  PAYROLL
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `salary_runs` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `company`             VARCHAR(80) NOT NULL DEFAULT 'LANDCO LIMITADA',
  `nuit`                VARCHAR(20),
  `total_gross_mzn`     DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_net_mzn`       DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_inss_employee` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_inss_employer` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_irps`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `status`              ENUM('draft','submitted','paid') NOT NULL DEFAULT 'draft',
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_run_month_year` (`month`, `year`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `salary_lines` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `salary_run_id`       INT UNSIGNED NOT NULL,
  `employee_id`         INT UNSIGNED NOT NULL,
  `base_salary`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `food_allowance`      DECIMAL(14,2) NOT NULL DEFAULT 0,
  `nightshift_hours`    DECIMAL(6,2)  NOT NULL DEFAULT 0,
  `nightshift_pay`      DECIMAL(14,2) NOT NULL DEFAULT 0,
  `back_pay`            DECIMAL(14,2) NOT NULL DEFAULT 0,
  `other_allowances`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `gross_salary`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `inss_employee`       DECIMAL(14,2) NOT NULL DEFAULT 0,  -- 3%
  `inss_employer`       DECIMAL(14,2) NOT NULL DEFAULT 0,  -- 4%
  `sindicate`           DECIMAL(14,2) NOT NULL DEFAULT 0,  -- 1%
  `irps`                DECIMAL(14,2) NOT NULL DEFAULT 0,
  `other_deductions`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `net_salary`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `days_worked`         TINYINT UNSIGNED,
  `source_file`         VARCHAR(255),
  FOREIGN KEY (`salary_run_id`) REFERENCES `salary_runs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`)   REFERENCES `employees`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `salary_advances` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`         INT UNSIGNED NOT NULL,
  `advance_date`        DATE NOT NULL,
  `month_applied`       TINYINT UNSIGNED NOT NULL,
  `year_applied`        SMALLINT UNSIGNED NOT NULL,
  `amount_mzn`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `description`         VARCHAR(255),
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `bim_salary_transfers` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `salary_run_id`       INT UNSIGNED,
  `nib`                 VARCHAR(30)  NOT NULL,
  `employee_name`       VARCHAR(150) NOT NULL,
  `amount_mzn`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `description1`        VARCHAR(120),
  `description2`        VARCHAR(120),
  `transaction_type`    CHAR(1)      NOT NULL DEFAULT 'C',  -- C=Credit
  `email`               VARCHAR(180),
  `source_file`         VARCHAR(255),
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`salary_run_id`) REFERENCES `salary_runs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `inss_payments` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `salary_run_id`       INT UNSIGNED,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `total_employee_mzn`  DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_employer_mzn`  DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_mzn`           DECIMAL(14,2) NOT NULL DEFAULT 0,
  `gare_reference`      VARCHAR(60),
  `payment_date`        DATE,
  `source_file`         VARCHAR(255),
  UNIQUE KEY `uq_inss_month_year` (`month`, `year`),
  FOREIGN KEY (`salary_run_id`) REFERENCES `salary_runs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `irps_payments` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `salary_run_id`       INT UNSIGNED,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `total_irps_mzn`      DECIMAL(14,2) NOT NULL DEFAULT 0,
  `gare_reference`      VARCHAR(60),
  `payment_date`        DATE,
  `source_file`         VARCHAR(255),
  UNIQUE KEY `uq_irps_month_year` (`month`, `year`),
  FOREIGN KEY (`salary_run_id`) REFERENCES `salary_runs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  MONTH-END RECONCILIATION
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `monthly_recon` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id`          INT UNSIGNED NOT NULL,
  `month`                TINYINT UNSIGNED NOT NULL,
  `year`                 SMALLINT UNSIGNED NOT NULL,
  `opening_balance_mzn`  DECIMAL(16,4) NOT NULL DEFAULT 0,
  `total_income_mzn`     DECIMAL(16,4) NOT NULL DEFAULT 0,
  `total_expenses_mzn`   DECIMAL(16,4) NOT NULL DEFAULT 0,
  `closing_balance_mzn`  DECIMAL(16,4) NOT NULL DEFAULT 0,
  `bank_payments_mzn`    DECIMAL(16,4) NOT NULL DEFAULT 0,
  `cash_payments_mzn`    DECIMAL(16,4) NOT NULL DEFAULT 0,
  `notes`                TEXT,
  `source_file`          VARCHAR(255),
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_recon_prop_month_year` (`property_id`, `month`, `year`),
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `annual_expenses` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id`         INT UNSIGNED,
  `description`         VARCHAR(255) NOT NULL,
  `month`               TINYINT UNSIGNED NOT NULL,
  `year`                SMALLINT UNSIGNED NOT NULL,
  `budgeted_mzn`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `actual_mzn`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `source_file`         VARCHAR(255),
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  IMPORT AUDIT LOG
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `import_log` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filename`            VARCHAR(255) NOT NULL,
  `importer`            VARCHAR(80)  NOT NULL,    -- which script ran
  `import_date`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `records_loaded`      INT NOT NULL DEFAULT 0,
  `records_skipped`     INT NOT NULL DEFAULT 0,
  `status`              ENUM('success','partial','failed') NOT NULL DEFAULT 'success',
  `error_message`       TEXT,
  `imported_by`         VARCHAR(80)               -- python or php user
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────────
--  APPLICATION USERS (PHP login)
-- ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `app_users` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`            VARCHAR(60)  NOT NULL UNIQUE,
  `email`               VARCHAR(180) NOT NULL UNIQUE,
  `password_hash`       VARCHAR(255) NOT NULL,
  `role`                ENUM('admin','accountant','viewer') NOT NULL DEFAULT 'viewer',
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  `last_login`          TIMESTAMP NULL,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
