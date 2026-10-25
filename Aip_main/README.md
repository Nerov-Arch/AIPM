# AIP Management System — MPDO Indang, Cavite

Web-Based Annual Investment Program (AIP) System built with PHP, HTML, CSS and vanilla JavaScript.

> **Development version.** The MySQL database has not been implemented yet. All records are
> sample data, forms are validated but nothing is stored, and the login is a temporary
> development login. A banner on every page states this.

## Running locally (XAMPP)

1. Copy the `Aip_main` folder into `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\Aip_main`).
2. Start **Apache** in the XAMPP Control Panel. MySQL is not needed yet.
3. Open `http://localhost/Aip_main/`.
4. Sign in with the development login: `admin` / `admin123`.

## Pages

| Menu group | Page | File |
|---|---|---|
| — | Dashboard | `dashboard.php` |
| Planning | Project Encoding | `pages/projects.php` |
| Planning | Project Linking & Traceability | `pages/project-linking.php` |
| Workflow | Review | `pages/review-workflow.php` |
| Workflow | Authorization | `pages/authorization-workflow.php` |
| Workflow | Implementation | `pages/implementation-workflow.php` |
| Reports & Funds | AIP Consolidation | `pages/aip-consolidation.php` |
| Reports & Funds | Fund Monitoring | `pages/fund-monitoring.php` |
| Administration | User Management | `pages/users.php` |

## What works now (no database needed)

- Login, logout and protected pages (redirect to login from any folder).
- Sidebar with all pages, active-page highlighting, breadcrumbs, mobile menu.
- Pending / Processed tabs, filters, search, pagination and live record counts.
- Detail dialogs for PPAs and users.
- Dashboard, fund and consolidation totals calculated from the sample records.
- Global search (top bar) across PPAs.
- CSV export of any table and a print-ready consolidated AIP report (A4 landscape).
- Project Encoding and Create User forms with matching browser and server validation.

## Temporary / mock components

| Component | Location | Replaced in the database phase by |
|---|---|---|
| Sample offices, fund sources, PPAs, users | `includes/mock-data.php` | Prepared-statement queries |
| Development login (`admin` / `admin123`) | `login.php` | Hashed passwords in MySQL |
| Fiscal year (`AIP_FISCAL_YEAR = 2027`) | `includes/helpers.php` | AIP cycle settings |
| Form "saves" (validated, not stored) | `pages/projects.php`, `pages/users.php` | Inserts / updates |

## Not yet available

- Saving PPAs and users, editing records, return-for-revision and approval actions.
- Department, fund source and fiscal year management screens.
- Role-based access (every signed-in user currently sees every page).
- Audit trail, approval-record attachments, progress updates.

## Database placeholder

`config/database.php` contains an inactive PDO connection function with placeholder values.
It is not called anywhere. It will be configured only in the separate database phase.
