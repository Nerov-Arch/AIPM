# AIP System database (MySQL / MariaDB)

| File | What it does |
|---|---|
| `aip_db.sql` | Creates the `aip_db` database: 18 tables, 2 views, the offices, sectors and fund sources (FY 2027 ceilings), and one administrator |
| `aip_sample_data.sql` | Optional test data: 8 test accounts, 14 sample PPAs with budgets, history, approval records and expenses (same as the development version) |

## Import in phpMyAdmin (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin` → **Import** → choose `aip_db.sql` → **Import**.
3. For testing only, import `aip_sample_data.sql` the same way.

Importing `aip_db.sql` again **drops and recreates all tables** (all data is lost).

## First sign-in

`admin` / temporary password `Dev@2026`. The account must change its password at first sign-in.
Change its email to the real administrator's `@indang.gov.ph` address. The sample accounts
(password `Dev@2026`) are for testing only; remove them before real use.

## Tables

| Module | Tables |
|---|---|
| Reference | `offices`, `sectors` |
| User Management | `users`, `login_otps`, `login_attempts` |
| Fund Source Monitoring | `fund_sources`, `fund_source_ceilings`, `ppa_fund_allocations` |
| Project Encoding | `ppas`, `ppa_history` |
| Review and Approval | `ppa_review_checklist`, `ppa_budgets` (Finance: requested vs approved) |
| Linking | `approval_records`, `ppa_approval_records` |
| AIP Consolidation | `aip_versions`, `aip_version_entries` |
| Expenses and Audit | `ppa_expenses` |
| Activity log | `audit_log` |
| Views | `v_ppa_summary`, `v_fund_totals` |

The PHP pages use this database through `includes/db.php`. Connection settings are in
`config/database.php` (a password goes in the git-ignored `config/database.local.php`).
