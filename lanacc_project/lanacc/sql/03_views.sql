-- ============================================================
--  LANACC — Reporting Views
--  File: sql/03_views.sql
-- ============================================================

USE `lanacc`;

-- ── Monthly P&L per property ──────────────────────────────────
CREATE OR REPLACE VIEW `v_monthly_pl` AS
SELECT
  p.id           AS property_id,
  p.house_code,
  p.name         AS property_name,
  s.name         AS shareholder,
  r.month,
  r.year,
  r.opening_balance_mzn,
  r.total_income_mzn,
  r.total_expenses_mzn,
  r.closing_balance_mzn,
  (r.total_income_mzn - r.total_expenses_mzn) AS net_mzn
FROM monthly_recon r
JOIN properties p  ON p.id = r.property_id
JOIN shareholders s ON s.id = p.shareholder_id;

-- ── Full payroll summary ──────────────────────────────────────
CREATE OR REPLACE VIEW `v_payroll_summary` AS
SELECT
  sr.month,
  sr.year,
  sr.company,
  sr.status,
  COUNT(sl.id)           AS employee_count,
  SUM(sl.gross_salary)   AS total_gross,
  SUM(sl.inss_employee)  AS total_inss_employee,
  SUM(sl.inss_employer)  AS total_inss_employer,
  SUM(sl.irps)           AS total_irps,
  SUM(sl.net_salary)     AS total_net,
  sr.id                  AS salary_run_id
FROM salary_runs sr
LEFT JOIN salary_lines sl ON sl.salary_run_id = sr.id
GROUP BY sr.id;

-- ── Employee payslip detail ───────────────────────────────────
CREATE OR REPLACE VIEW `v_employee_payslip` AS
SELECT
  sr.month,
  sr.year,
  e.id         AS employee_id,
  e.name       AS employee_name,
  e.nib,
  e.role,
  sl.gross_salary,
  sl.food_allowance,
  sl.nightshift_pay,
  sl.back_pay,
  sl.inss_employee,
  sl.irps,
  sl.sindicate,
  sl.net_salary,
  sl.days_worked
FROM salary_lines sl
JOIN salary_runs sr ON sr.id = sl.salary_run_id
JOIN employees e   ON e.id  = sl.employee_id;

-- ── Shareholder statement ─────────────────────────────────────
CREATE OR REPLACE VIEW `v_shareholder_statement` AS
SELECT
  s.id          AS shareholder_id,
  s.name        AS shareholder_name,
  p.house_code,
  p.name        AS property_name,
  sb.month,
  sb.year,
  sb.opening_balance_mzn,
  sb.total_income_mzn,
  sb.total_expenses_mzn,
  sb.closing_balance_mzn
FROM shareholder_balances sb
JOIN shareholders s ON s.id = sb.shareholder_id
JOIN properties p   ON p.shareholder_id = s.id;

-- ── Annual shared expense tracker ────────────────────────────
CREATE OR REPLACE VIEW `v_annual_expenses` AS
SELECT
  ae.year,
  ae.month,
  ec.name     AS category,
  ae.description,
  ae.budgeted_mzn,
  ae.actual_mzn,
  (ae.actual_mzn - ae.budgeted_mzn) AS variance_mzn
FROM annual_expenses ae
LEFT JOIN expense_categories ec ON ec.id = ae.category_id;

-- ── Import log summary ────────────────────────────────────────
CREATE OR REPLACE VIEW `v_import_log` AS
SELECT
  DATE(import_date)  AS import_day,
  importer,
  status,
  SUM(records_loaded)   AS total_loaded,
  SUM(records_skipped)  AS total_skipped,
  COUNT(*)              AS file_count
FROM import_log
GROUP BY DATE(import_date), importer, status;
