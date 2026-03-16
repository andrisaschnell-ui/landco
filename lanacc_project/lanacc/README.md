# LANACC — Landco Accounts Automation System
### Version 1.0 | Landco Lda | Mozambique | 2026

---

## QUICK START (5 minutes)

```bash
# 1. Copy environment file
copy .env.example .env
# Edit .env and set DATA_ROOT to your spreadsheet folder path

# 2. Start all services
docker-compose up -d

# 3. Wait ~20 seconds, then open:
#    App:         http://localhost:8080   (login: admin / Admin@2026)
#    phpMyAdmin:  http://localhost:8081   (view/edit database directly)

# 4. Run Python ETL to load your Excel files
cd python
python -m venv env
env\Scripts\activate        # Windows
pip install -r requirements.txt
python run_all.py
```

---

## PROJECT STRUCTURE

```
lanacc/
├── docker-compose.yml          # All services (MySQL + PHP + phpMyAdmin)
├── Dockerfile.php              # PHP 8.2 / Apache container
├── .env.example                # Copy to .env and fill in
├── .docker/
│   └── apache.conf             # Apache virtual host
├── sql/
│   ├── 01_schema.sql           # All 18 tables (runs on first startup)
│   ├── 02_seed_data.sql        # Shareholders, properties, employees
│   └── 03_views.sql            # Reporting views
├── python/
│   ├── requirements.txt        # pip dependencies
│   ├── config.py               # DB connection + folder paths
│   ├── db.py                   # MySQL helpers + import_log
│   ├── run_all.py              # Master ETL runner (CLI)
│   └── importers/
│       ├── import_salaries.py  # Folha de Salarios sheets
│       ├── import_bim.py       # BIM bank transfer files
│       ├── import_accounts.py  # Month-end accounts
│       ├── import_petty_cash.py# Petty cash sheets
│       ├── import_shareholders.py  # Shareholder P&L
│       └── import_bdo.py       # BDO bank control
└── php/
    ├── index.php               # Redirects to dashboard/login
    ├── login.php               # Login page
    ├── logout.php
    ├── config/
    │   └── database.php        # PDO connection + helpers
    ├── includes/
    │   ├── auth.php            # Session + role auth
    │   ├── header.php          # Bootstrap navbar
    │   └── footer.php          # Bootstrap footer + scripts
    ├── pages/
    │   ├── dashboard.php       # KPIs + charts + import log
    │   ├── upload.php          # File upload + logging
    │   ├── employees.php       # Employee CRUD
    │   ├── shareholders.php    # Shareholder statements + transactions
    │   ├── transactions.php    # Income & expense CRUD
    │   ├── payroll.php         # Payroll runs + salary lines
    │   └── reports.php         # Monthly ledger, shareholder, payroll, P&L
    └── assets/
        ├── css/style.css
        └── js/app.js
```

---

## PHASED IMPLEMENTATION PLAN

### ═══ PHASE 1 — INFRASTRUCTURE & DATABASE  (Week 1) ═══

**Goal:** Get Docker running on Windows with MySQL and PHP accessible.

**Steps:**
1. Install Docker Desktop for Windows from https://docker.com
2. Create a folder: `C:\lanacc\`
3. Copy all project files into `C:\lanacc\`
4. Copy `.env.example` → `.env` and set:
   - `DATA_ROOT=C:/Users/YourName/Documents/landco2026`
   - Change passwords if desired
5. Open Command Prompt (or PowerShell) in `C:\lanacc\`
6. Run: `docker-compose up -d`
7. Wait ~30 seconds for MySQL to initialise
8. Open http://localhost:8080 — you should see the login page
9. Log in with `admin` / `Admin@2026`
10. Open http://localhost:8081 (phpMyAdmin) to verify all tables were created

**What gets created automatically:**
- All 18 database tables (schema from `sql/01_schema.sql`)
- Seed data: shareholders, properties, employees, bank accounts, categories
- Admin user

**Deliverable:** Working web app connected to MySQL ✓

---

### ═══ PHASE 2 — PYTHON ETL SETUP  (Week 2) ═══

**Goal:** Get the Python scripts running and importing your Excel files.

**Steps:**
1. Install Python 3.11+ from https://python.org (check "Add to PATH")
2. Open Command Prompt in `C:\lanacc\python\`
3. Create virtual environment:
   ```
   python -m venv env
   env\Scripts\activate
   pip install -r requirements.txt
   ```
4. Edit `config.py` line for `DATA_ROOT` to match your folder
   OR create a `.env` file in `C:\lanacc\python\` with:
   ```
   DATA_ROOT=C:\Users\YourName\Documents\landco2026
   DB_HOST=localhost
   DB_USER=lanacc_user
   DB_PASSWORD=lanacc_pass_2026
   DB_NAME=lanacc
   ```
5. Test connection: `python -c "from db import get_connection; get_connection(); print('OK')"`
6. Run individual importers to test:
   ```
   python -m importers.import_salaries
   python -m importers.import_bim
   python -m importers.import_accounts
   python -m importers.import_petty_cash
   python -m importers.import_shareholders
   python -m importers.import_bdo
   ```
7. Or run all at once: `python run_all.py`
8. Check the Upload page in the app to verify import log entries

**Available CLI options:**
```
python run_all.py                   # Run all importers
python run_all.py --only salaries   # Run only salary importer
python run_all.py --only bim        # Run only BIM importer
python run_all.py --month 1         # Filter to January files
```

**Deliverable:** All Jan–Mar 2026 data loaded into MySQL ✓

---

### ═══ PHASE 3 — DATA VERIFICATION & MANUAL CORRECTIONS  (Week 3) ═══

**Goal:** Verify imported data matches Excel files; fix any discrepancies.

**Steps:**
1. Open the **Dashboard** in the app — check that income/expense totals match Excel
2. Go to **Payroll** → select Jan 2026 → compare gross/net totals with spreadsheet
3. Go to **Shareholders** → select each shareholder → verify opening balances
4. Open phpMyAdmin (http://localhost:8081) for direct SQL queries:
   ```sql
   -- Check total income Jan 2026
   SELECT property_id, SUM(amount_mzn) FROM income_transactions
   WHERE month=1 AND year=2026 GROUP BY property_id;

   -- Check payroll Jan 2026
   SELECT * FROM v_payroll_summary WHERE month=1 AND year=2026;
   ```
5. Use the **Transactions** page to manually add or delete any records that
   did not import correctly
6. Use **Employees** page to complete NIB/NUIT numbers for any staff

**Common issues & fixes:**
- Shareholder opening balance wrong → Edit via Shareholders → Add Transaction → `opening_balance`
- Employee missing → Employees → Add Employee
- Wrong exchange rate → Transactions → Edit the relevant record

**Deliverable:** Verified, clean data for Jan–Mar 2026 ✓

---

### ═══ PHASE 4 — REPORTING  (Week 4) ═══

**Goal:** Generate and test all report types.

**Reports available at http://localhost:8080/pages/reports.php:**

| Report | Use | URL |
|--------|-----|-----|
| Monthly Ledger | Send to auditor | `?type=monthly_ledger&month=1&year=2026` |
| Shareholder Statement | Per shareholder P&L | `?type=shareholder&sh=2&year=2026` |
| Payroll Report | Full payroll breakdown | `?type=payroll&month=1&year=2026` |
| P&L per Property | Annual P&L all properties | `?type=pl&year=2026` |

**Steps:**
1. Open each report type for January 2026
2. Verify totals match your original Excel files
3. Use browser Print (Ctrl+P) → "Save as PDF" for archiving
4. Check the print CSS hides navigation elements for a clean output

**Deliverable:** All 4 report types working and matching source data ✓

---

### ═══ PHASE 5 — WORKFLOW & MONTHLY ROUTINE  (Week 5) ═══

**Goal:** Establish the monthly data entry workflow going forward.

**Monthly workflow (to be done each month end):**

1. **Save Excel files** into the correct subfolders of `landco2026\`
   following the naming convention: `03 MARCH...xlsx`

2. **Run Python ETL:**
   ```
   cd C:\lanacc\python
   env\Scripts\activate
   python run_all.py
   ```

3. **Verify in the app:**
   - Dashboard → check KPIs look correct
   - Payroll → verify salary totals
   - Shareholders → check each statement

4. **Generate reports:**
   - Monthly Ledger → Print/Save as PDF → Email to auditor
   - Shareholder Statements → Print for each shareholder

5. **Backup the database:**
   ```
   docker exec lanacc_db mysqldump -u lanacc_user -planacc_pass_2026 lanacc
     > C:\lanacc\backups\lanacc_backup_YYYYMMDD.sql
   ```

---

### ═══ PHASE 6 — FUTURE ENHANCEMENTS  (Optional) ═══

These features can be added in future phases:

- **PDF export** of reports (using PHP libraries like TCPDF or mPDF)
- **Email reports** directly from the app
- **Budget vs Actuals** analysis page
- **Multi-year comparison** charts on the dashboard
- **User management** page (add/edit/delete app_users)
- **Petty Cash register** page (view and edit petty_cash_transactions)
- **Bank statement** reconciliation page
- **INSS/IRPS tracking** with payment due dates and alerts
- **Advance salary** management page
- **Audit trail** — who changed what and when

---

## DATABASE TABLES REFERENCE

| Table | Records | Description |
|-------|---------|-------------|
| shareholders | 4 | Luz Tafy, Coco Cohen, Aurora Stead, Caju Kevin |
| properties | 4 | Casa Luz H1, Casa Coco H2, Casa Aurora H3, Casa Caju H4 |
| employees | 5+ | All Landco staff with NIB and salary |
| bank_accounts | 8 | BDO, BIM USD, BIM MTN, petty cash accounts |
| expense_categories | 16 | Shared and personal expense categories |
| income_transactions | — | Monthly rental/guest income per property |
| expense_transactions | — | All expenses with category and shared flag |
| bank_transactions | — | BDO/BIM bank statement lines |
| petty_cash_transactions | — | Fuel, parks fees, USD cash movements |
| shareholder_transactions | — | Income, expenses, drawings per shareholder |
| shareholder_balances | — | Monthly balance summary per shareholder |
| salary_runs | — | One record per payroll month |
| salary_lines | — | One record per employee per month |
| salary_advances | — | Advance salary payments |
| bim_salary_transfers | — | BIM bank file records |
| inss_payments | — | INSS submissions and payments |
| irps_payments | — | IRPS submissions and payments |
| monthly_recon | — | Month-end reconciliation per property |
| annual_expenses | — | Budget vs actuals per category |
| import_log | — | Audit trail of all file imports |
| app_users | 1+ | PHP app login accounts |
| exchange_rates | — | USD/MZN rate history |

---

## DEFAULT CREDENTIALS

| Service | URL | User | Password |
|---------|-----|------|----------|
| Web App | http://localhost:8080 | admin | Admin@2026 |
| phpMyAdmin | http://localhost:8081 | root | lanacc_root_2026 |
| MySQL direct | localhost:3306 | lanacc_user | lanacc_pass_2026 |

**IMPORTANT:** Change all passwords in `.env` before using in production.

---

## SUPPORT & TROUBLESHOOTING

**MySQL not starting:**
```
docker-compose logs mysql
docker-compose down -v   # ⚠ This deletes data — only for fresh start
docker-compose up -d
```

**PHP can't connect to MySQL:**
- Wait 30s after starting — MySQL needs time to initialise
- Check: `docker ps` — both containers should show "healthy"

**Python script can't find files:**
- Check `DATA_ROOT` in `.env` or `config.py`
- Use forward slashes in paths: `C:/Users/Name/Documents/landco2026`

**Reset admin password:**
```sql
-- Run in phpMyAdmin
UPDATE app_users
SET password_hash = '$2y$12$Xp6P4KwZqsKZNlXlK5yQvOqgkYWpR0LtC2cVsFxIqy0VPAdHi9m9y'
WHERE username = 'admin';
-- New password: Admin@2026
```
