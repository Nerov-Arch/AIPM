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
| Planning | Project Encoding (My PPAs) | `pages/ppas.php` |
| Planning | Encode / edit a PPA | `pages/projects.php` |
| Planning | PPA record and status history | `pages/ppa-view.php` |
| Planning | Project Linking & Traceability | `pages/project-linking.php` |
| Workflow | Review queue | `pages/review-workflow.php` |
| Workflow | Evaluate / decide on a PPA | `pages/ppa-review.php` |
| Workflow | Authorization queue | `pages/authorization-workflow.php` |
| Workflow | Implementation | `pages/implementation-workflow.php` |
| Reports & Funds | AIP Consolidation | `pages/aip-consolidation.php` |
| Reports & Funds | Fund Monitoring (sources, PPA funding, issues) | `pages/fund-monitoring.php` |
| Reports & Funds | Fund source detail | `pages/fund-source.php` |
| Reports & Funds | Add / edit fund source | `pages/fund-form.php` |
| Reports & Funds | PPA fund allocation | `pages/ppa-funding.php` |
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

## Project Encoding module

| Feature | Status |
|---|---|
| Standardized AIP fields: reference code, title, type, program, sector, description, expected outputs, office, schedule, PS/MOOE/CO (total computed), fund source, climate change adaptation/mitigation and typology code | Done |
| Save draft (title and office only), edit, submit for review | Works (session store) |
| Returned PPAs show reviewer remarks; encoder revises and resubmits | Works (session store) |
| Delete drafts; submitted PPAs are locked from editing | Enforced |
| Duplicate check: same title for the same office in the same AIP year is rejected | Enforced |
| Encoders encode and see only their own office's PPAs; drafts are private to the office | Enforced |
| Submitted PPAs appear in Review, Linking, Dashboard, Consolidation and Fund Monitoring | Works |
| Status history on each PPA; create/edit/submit/resubmit/delete recorded in the activity log | Works (session store) |
| Matching browser and server validation for every field | Done |

PPA fields, rules and storage are in `includes/ppa.php`.

## Fund Source Monitoring module

| Feature | Status |
|---|---|
| Fund sources with short code, account code, category, description, FY ceiling and active status | Works (session store) |
| Add, edit, activate/deactivate fund sources; delete only when no PPA uses them | Works (session store) |
| Every PPA charged to one or more fund sources; MPDO can split a PPA's cost across sources (must equal the PPA cost, basis required) | Works (session store) |
| Per-fund ceiling, programmed (approved), pending, available and utilized amounts, with a detail page per fund | Done |
| Automatic funding checks: under/over-allocated PPAs, missing fund source, inactive or unknown source, primary fund not in split, over-utilization, fund over ceiling or at risk | Done |
| Only active fund sources can be chosen when encoding | Enforced |
| Administrators and Reviewers manage funds; Approvers view only; Encoders have no access | Enforced |
| Fund and allocation changes recorded in the activity log and PPA history | Works (session store) |

Fund logic is in `includes/funds.php`. Sample data includes one under-allocated PPA (PPA-2027-013)
and a fund at risk of exceeding its ceiling (20% Development Fund) to demonstrate the checks.

## Review and Approval module

| Feature | Status |
|---|---|
| Sequence: Encoder submits → Reviewer (MPDO) returns or recommends → Approver approves, disapproves or sends back | Works (session store) |
| Review checklist (all items required to recommend); remarks required to return, disapprove or send back | Enforced |
| Optional Sangguniang Bayan resolution number and date on approval, shown in Project Linking | Works (session store) |
| Approval blocked when the PPA has funding issues or would push a fund over its ceiling; recommendation blocked by funding errors | Enforced |
| Separation of duties: no one acts on a PPA they encoded; the person who recommended a PPA cannot approve or disapprove it | Enforced |
| Only the right role at the right stage can act; every other attempt is refused on the server | Enforced |
| Stage tracker on every PPA: current stage, who it is waiting on, days in status | Done |
| Queues sorted oldest first with days waiting; items over 7 days flagged as overdue | Done |
| "Awaiting your action" on the dashboard and a count on the bell, per role | Done |
| Every decision recorded in the PPA history and the activity log | Works (session store) |

Workflow rules are in `includes/workflow.php`.

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
| Session-backed PPA records and status history | `includes/ppa.php` | `ppas` and `ppa_history` tables |
| Session-backed fund sources and PPA allocations | `includes/funds.php` | `fund_sources` and `ppa_fund_allocations` tables |
| Shared development password `Dev@2026` and test-account list on the login page | `includes/mock-data.php`, `login.php` | Removed |
| Fiscal year (`AIP_FISCAL_YEAR = 2027`) | `includes/helpers.php` | AIP cycle settings |

## Not yet available

- Department and fiscal year management screens.
- Approval-record attachments, progress updates.
- Email delivery of temporary passwords (shown on screen to the administrator instead).

## Database placeholder

`config/database.php` contains an inactive PDO connection function with placeholder values.
It is not called anywhere. It will be configured only in the separate database phase.
