# AIP Management System — MPDO Indang, Cavite

Web-Based Annual Investment Program (AIP) System built with PHP, HTML, CSS and vanilla JavaScript.

> **Development version.** The MySQL database has not been implemented yet. All records are
> sample data, forms are validated but nothing is stored, and the login is a temporary
> development login. A banner on every page states this.

## Running locally (XAMPP)

1. Copy the `Aip_main` folder into `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\Aip_main`).
2. Start **Apache** in the XAMPP Control Panel. MySQL is not needed yet.
3. Open `http://localhost/Aip_main/`.
4. Sign in with one of the development test accounts (password `Dev@2026` for all):

| Username | Role | Office |
|---|---|---|
| `admin` | Administrator | MPDO |
| `jbautista` | Reviewer | MPDO |
| `alim` | Approver | MPDO |
| `rdelacruz` | Encoder | MEO |
| `lgarcia` | Encoder | MSWDO |
| `cramos` | Encoder | MENRO |

The login page lists these accounts; click one to fill in the username.

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

## User Management module

| Feature | Status |
|---|---|
| Create user (temporary password shown once, must change at first sign-in) | Works (session store) |
| Edit name, username, email, position, role, office, status | Works (session store) |
| Activate / deactivate, reset password | Works (session store) |
| Roles: Administrator, Encoder, Reviewer, Approver | Enforced on every page |
| Encoders limited to their own office's PPAs | Enforced |
| Activity log of sign-ins, account changes and denied access | Works (session store) |
| My Account: view own role, change password | Works (session store) |
| Safeguards: cannot change own role/status, cannot remove last active admin, unique username/email | Enforced |
| Sign-in lockout after 5 failed attempts (5 minutes), CSRF protection on all forms | Works |

Roles and permissions are defined in `includes/permissions.php`. Account data and the activity
log live in `includes/store.php`.

> **Session store, not a database.** Accounts and the activity log are kept in the PHP session.
> They survive signing out and switching test accounts in the same browser, but reset when the
> browser session ends, and are not shared between browsers or computers.

## What works now (no database needed)

- Login, logout and protected pages (redirect to login from any folder).
- Sidebar with all pages, active-page highlighting, breadcrumbs, mobile menu.
- Pending / Processed tabs, filters, search, pagination and live record counts.
- Detail dialogs for PPAs and users.
- Dashboard, fund and consolidation totals calculated from the sample records.
- Global search (top bar) across PPAs.
- CSV export of any table and a print-ready consolidated AIP report (A4 landscape).
- Project Encoding and user forms with matching browser and server validation.

## Temporary / mock components

| Component | Location | Replaced in the database phase by |
|---|---|---|
| Sample offices, fund sources, PPAs, starting users | `includes/mock-data.php` | Prepared-statement queries |
| Session-backed user accounts and activity log | `includes/store.php` | `users` and `audit_logs` tables |
| Shared development password `Dev@2026` and test-account list on the login page | `includes/mock-data.php`, `login.php` | Removed |
| Fiscal year (`AIP_FISCAL_YEAR = 2027`) | `includes/helpers.php` | AIP cycle settings |
| PPA form "saves" (validated, not stored) | `pages/projects.php` | Inserts / updates |

## Not yet available

- Saving PPAs, return-for-revision and approval actions (next modules).
- Department, fund source and fiscal year management screens.
- Approval-record attachments, progress updates.
- Email delivery of temporary passwords (shown on screen to the administrator instead).

## Database placeholder

`config/database.php` contains an inactive PDO connection function with placeholder values.
It is not called anywhere. It will be configured only in the separate database phase.
