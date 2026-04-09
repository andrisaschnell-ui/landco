-- ============================================================
--  LANACC — Seed / Reference Data
--  File: sql/02_seed_data.sql
-- ============================================================

USE `lanacc`;

-- ── Shareholders ─────────────────────────────────────────────
INSERT IGNORE INTO `shareholders` (`id`, `name`, `email`, `active`) VALUES
  (1, 'Luz Tafy',     NULL, 1),
  (2, 'Coco Cohen',   NULL, 1),
  (3, 'Aurora Stead', NULL, 1),
  (4, 'Caju Kevin',   NULL, 1);

-- ── Properties ───────────────────────────────────────────────
INSERT IGNORE INTO `properties` (`id`, `name`, `house_code`, `shareholder_id`) VALUES
  (1, 'Casa Luz',    'H1', 1),
  (2, 'Casa Coco',   'H2', 2),
  (3, 'Casa Aurora', 'H3', 3),
  (4, 'Casa Caju',   'H4', 4);

-- ── Bank Accounts ─────────────────────────────────────────────
INSERT IGNORE INTO `bank_accounts` (`id`, `bank_name`, `account_name`, `currency`, `account_type`, `property_id`) VALUES
  (1, 'BDO',  'BDO Main MZN',       'MZN', 'current',    NULL),
  (2, 'BIM',  'BIM USD Account',    'USD', 'current',    NULL),
  (3, 'BIM',  'BIM MTN Account',    'MZN', 'current',    NULL),
  (4, 'CASH', 'Petty Cash MZN',     'MZN', 'petty_cash', NULL),
  (5, 'CASH', 'Parks Fees USD',     'USD', 'petty_cash', NULL),
  (6, 'CASH', 'Landco USD Cash',    'USD', 'petty_cash', NULL),
  (7, 'CASH', 'Landco Pre-paid',    'MZN', 'petty_cash', NULL),
  (8, 'BIM',  'RSD Fuel Account',   'MZN', 'petty_cash', NULL);

-- ── Expense Categories ────────────────────────────────────────
INSERT IGNORE INTO `expense_categories` (`name`, `is_shared`) VALUES
  ('Salaries & Wages',              1),
  ('Casual Workers & Food Allow',   1),
  ('INSS Employer Contribution',    1),
  ('IRPS Income Tax',               1),
  ('Fuel & Transport',              0),
  ('Parks & Permits Fees',          0),
  ('Utilities',                     1),
  ('Maintenance & Repairs',         0),
  ('Household Equipment',           0),
  ('Bank Charges',                  1),
  ('Guest Expenses',                0),
  ('Security',                      0),
  ('Marketing & Advertising',       1),
  ('Insurance',                     1),
  ('Professional Fees',             1),
  ('Other Expense',                 0);

-- ── Employees (Jan 2026 data) ─────────────────────────────────
INSERT IGNORE INTO `employees`
  (`id`, `name`, `initials`, `nib`, `role`, `base_salary_mzn`, `food_allowance_mzn`, `active`) VALUES
  (1, 'Marco Bebe Gimo',          'AM', '304762846',  'Gerente',  81000.00, 8000.00, 1),
  (2, 'Armindo Quetane Hou',      'AM', '312688813',  'Staff',    17476.33, 0.00,    1),
  (3, 'Constantino Jose Penga',   'AM', '88904090',   'Staff',    17948.67, 0.00,    1),
  (4, 'Anselmo Lucas Huo',        'AM', '311846368',  'Staff',    14170.00, 0.00,    1),
  (5, 'Cistora Joao Tangune',     'AM', '465121888',  'Staff',    10464.00, 0.00,    1);

-- ── App users (admin password: Admin@2026) ────────────────────
-- Hash generated with: password_hash('Admin@2026', PASSWORD_BCRYPT)
INSERT IGNORE INTO `app_users` (`username`, `email`, `password_hash`, `role`) VALUES
  ('admin', 'admin@landco.co.mz',
   '$2y$12$Xp6P4KwZqsKZNlXlK5yQvOqgkYWpR0LtC2cVsFxIqy0VPAdHi9m9y',
   'admin');
