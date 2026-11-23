# AIP Management System — MPDO Indang, Cavite

Web-Based Annual Investment Program (AIP) System built with PHP, HTML, CSS and vanilla JavaScript.

> **Development version.** All records are stored in a **MySQL database** (`aip_db`) and are shared
> between every browser and user. The test accounts share one development password and the
> sign-in code is shown on screen instead of emailed; a banner on every page states this while
> the test accounts are switched on.

## Running locally (XAMPP)

See **XAMPP-GUIDE.md** for the full step-by-step guide. In short:

1. Copy this folder into `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\claude`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In phpMyAdmin, import `database/aip_db.sql`, then `database/aip_sample_data.sql` for the
   test accounts and sample PPAs (see `database/README.md`).
4. Open `http://localhost/claude/`.
5. Sign in with a development test account (password `Dev@2026` for all). After the password,
   the system asks for a 6-digit **sign-in code** that would be emailed to the account's
   `@indang.gov.ph` address. Email delivery is off in this version, so the code is shown on the
   sign-in screen, clearly labelled.

| Username | Role | Office |
|---|---|---|
| `admin` | Administrator | MPDO |
| `jbautista` | Reviewer (evaluates and gives final approval) | MPDO |
| `alim` | Reviewer (MPDC) | MPDO |
| `tnavarro` | Finance (budget review) | MBO |
| `raquino` | Auditor (expenditure audit) | IAS |
| `rdelacruz` | Encoder | MEO |
| `lgarcia` | Encoder | MSWDO |
| `cramos` | Encoder | MENRO |

The login page lists these accounts; click one to fill in the username.

## Pages

| Menu group | Page | File |
|---|---|---|
| — | Dashboard (live AIP overview, per role) | `dashboard.php` |
| Planning | Project Encoding (My PPAs) | `pages/ppas.php` |
| Planning | Encode / edit a PPA | `pages/projects.php` |
| Planning | PPA record and status history | `pages/ppa-view.php` |
| Planning | Project Linking & Traceability (PPA links, approval records, offices, link checks) | `pages/project-linking.php` |
| Planning | Manage a PPA's approval records | `pages/link-record.php` |
| Planning | Approval record (register, view, edit) | `pages/approval-record.php` |
| Planning | Implementing office and its linked records | `pages/office.php` |
| Workflow | Review & Approval queue (MPDO evaluation and final approval) | `pages/review-workflow.php` |
| Workflow | Evaluate / give final approval on a PPA | `pages/ppa-review.php` |
| Workflow | Budget Review queue (Finance) | `pages/budget-workflow.php` |
| Workflow | Budget decision: requested vs approved | `pages/budget-review.php` |
| Workflow | Implementation | `pages/implementation-workflow.php` |
| Workflow | Expenses, receipts and expenditure timeline of a PPA | `pages/ppa-expenses.php` |
| Workflow | Expenditure Audit (all expenses, audit status) | `pages/expenditures.php` |
| Workflow | Receipt file (served after a permission check) | `pages/receipt.php` |
| — | Old Authorization link (redirects to Review & Approval) | `pages/authorization-workflow.php` |
| Reports & Funds | AIP Consolidation (readiness, summary, AIP form, versions) | `pages/aip-consolidation.php` |
| Reports & Funds | Saved AIP version (compare, finalize) | `pages/aip-version.php` |
| Reports & Funds | AIP CSV download | `pages/aip-export.php` |
| Reports & Funds | Fund Monitoring (sources, PPA funding, requested vs approved budget, issues) | `pages/fund-monitoring.php` |
| Reports & Funds | Fund source detail | `pages/fund-source.php` |
| Reports & Funds | Add / edit fund source | `pages/fund-form.php` |
| Reports & Funds | PPA fund allocation | `pages/ppa-funding.php` |
| Administration | User Management | `pages/users.php` |

## User Management module

| Feature | Status |
|---|---|
| Create user (temporary password shown once, must change at first sign-in) | Works (MySQL) |
| Edit name, username, government email, position, role, office, status | Works (MySQL) |
| Every account is linked to an `@indang.gov.ph` government email (checked in the browser and on the server) | Enforced |
| Two-step sign-in: password, then a 6-digit one-time code sent to the government email (valid 5 minutes, 5 tries, new code after 60 seconds) | Works; **email delivery is a placeholder** (see below) |
| Activate / deactivate, reset password | Works (MySQL) |
| Roles: Administrator, Encoder, Reviewer (reviews and approves; the former Approver role is merged into it), Finance, Auditor | Enforced on every page |
| Encoders limited to their own office's PPAs | Enforced |
| Activity log of sign-ins, account changes and denied access | Works (MySQL) |
| My Account: view own role, change password | Works (MySQL) |
| Safeguards: cannot change own role/status, cannot remove last active admin, unique username/email | Enforced |
| Sign-in lockout after 5 failed attempts (5 minutes), CSRF protection on all forms | Works |

Roles and permissions are defined in `includes/permissions.php`. Account data and the activity
log live in `includes/store.php`.

> **Sign-in codes are not emailed yet.** `config/mail.php` has `MAIL_ENABLED = false`. While it
> is off, the code is shown on the sign-in screen with a "Development mode" notice. Sending real
> email needs an SMTP relay configured for PHP; no SMTP credentials are stored in the code.

> **Database settings.** `config/database.php` holds the XAMPP defaults (localhost, database
> `aip_db`, user `root`, no password). If your MySQL user has a password, copy
> `config/database.local.example.php` to `config/database.local.php` and put it there; that file
> is git-ignored, so the password is never committed. If MySQL is not running, every page shows a
> short setup message instead of an error.

## Project Encoding module

| Feature | Status |
|---|---|
| Standardized AIP fields: reference code, title, type, program, sector, description, expected outputs, office, schedule, PS/MOOE/CO (total computed), fund source | Done |
| Climate change expenditure is **not** encoded by the office; the MPDO enters it at final approval | Done |
| Save draft (title and office only), edit, submit for review | Works (MySQL) |
| Returned PPAs show reviewer remarks; encoder revises and resubmits | Works (MySQL) |
| The office can still edit a PPA while it is Pending Review; each edit is recorded with what changed and flagged to the reviewer. Editing stops once the MPDO forwards it to Finance | Works (MySQL) |
| Delete drafts | Enforced |
| Duplicate check: same title for the same office in the same AIP year is rejected | Enforced |
| Encoders encode and see only their own office's PPAs; drafts are private to the office | Enforced |
| Submitted PPAs appear in Review, Linking, Dashboard, Consolidation and Fund Monitoring | Works |
| Status history on each PPA; create/edit/submit/resubmit/delete recorded in the activity log | Works (MySQL) |
| Matching browser and server validation for every field | Done |

PPA fields, rules and storage are in `includes/ppa.php`.

## Fund Source Monitoring module

| Feature | Status |
|---|---|
| Fund sources with short code, account code, category, description, FY ceiling and active status | Works (MySQL) |
| Add, edit, activate/deactivate fund sources; delete only when no PPA uses them | Works (MySQL) |
| Every PPA charged to one or more fund sources; MPDO can split a PPA's cost across sources (must equal the PPA cost, basis required) | Works (MySQL) |
| Per-fund ceiling, programmed (approved), pending, available and utilized amounts, with a detail page per fund | Done |
| Automatic funding checks: under/over-allocated PPAs, missing fund source, inactive or unknown source, primary fund not in split, over-utilization, fund over ceiling or at risk | Done |
| Only active fund sources can be chosen when encoding | Enforced |
| Requested vs approved budget: per PPA and per fund source, with the difference (tab "Requested vs approved") | Done |
| Administrators, Reviewers and Finance manage funds; Auditors view only; Encoders have no access | Enforced |
| Fund and allocation changes recorded in the activity log and PPA history | Works (MySQL) |

Fund logic is in `includes/funds.php`. Sample data includes one under-allocated PPA (PPA-2027-013)
and a fund at risk of exceeding its ceiling (20% Development Fund) to demonstrate the checks.

## Review and Approval module

| Feature | Status |
|---|---|
| One MPDO **Reviewer** role evaluates and gives the final approval (the separate Approver role was merged into it); one Review & Approval queue | Done |
| Sequence: Encoder submits → MPDO evaluates (return to office, or forward to Finance) → **Finance reviews the budget** (approve as requested or lower, or return to MPDO) → MPDO final approval | Works (MySQL) |
| Review checklist (all items required to forward); remarks required to return, disapprove, send back or return to MPDO | Enforced |
| Finance approves PS / MOOE / CO amounts; the total cannot exceed the request, and remarks are required when it is lower. Split fund allocations are scaled to the approved total | Enforced |
| The requested budget is kept and compared with the approved budget on the PPA, the budget page, Fund Monitoring and the dashboard | Done |
| Final decision is recorded as an **approval status** (Approved / Disapproved); it no longer asks for a resolution number. Resolutions can still be linked from Project Linking | Done |
| **Climate change expenditure** (adaptation, mitigation, typology code) entered manually by the MPDO at final approval; cannot exceed the approved cost | Enforced |
| Approval blocked when the PPA has funding issues or would push a fund over its ceiling | Enforced |
| No one acts on a PPA they encoded; only the right role at the right stage can act; every other attempt is refused on the server | Enforced |
| Stage tracker (Encoding → MPDO Review → Budget Review → Final Approval → Implementation): current stage, who it is waiting on, days in status | Done |
| Queues sorted oldest first with days waiting; items over 7 days flagged as overdue | Done |
| "Awaiting your action" on the dashboard and a count on the bell, per role | Done |
| Every decision recorded in the PPA history and the activity log | Works (MySQL) |

Workflow rules are in `includes/workflow.php`.

## Expenses and Audit (implementation)

| Feature | Status |
|---|---|
| The implementing office records each expense of an approved PPA: date, description, amount, receipt / OR / voucher number and a **photo or scan of the receipt** (JPG, PNG, WEBP or PDF, up to 5 MB; the file type is checked from its contents) | Works |
| Expenses cannot exceed the remaining approved budget; the first expense moves the PPA to Ongoing | Enforced |
| Expenditure timeline per PPA: every expense in date order with running total, receipt preview and spending by month | Done |
| Audit staff (Auditor role) see all expenses, open the receipts, and mark each expense **verified** or **flagged** (note required) | Works (MySQL) |
| Expenditure Audit page: totals, not-yet-audited / flagged / verified tabs, spending per PPA (approved, spent, unutilized) | Done |
| Offices see only their own expenses and receipts, and can remove an expense only before it is audited | Enforced |
| Receipt files are stored in `uploads/receipts/` with random names and served only through `pages/receipt.php` after a permission check (`uploads/.htaccess` blocks direct access on Apache) | Done |

Expense logic is in `includes/expenses.php`.

> **Receipts.** Expense records are in the `ppa_expenses` table; the uploaded files are saved in
> `uploads/receipts/` and the table stores the file name. Delete the files there when resetting
> test data. Uploaded files are never committed (see `.gitignore`).

## AIP Consolidation module

| Feature | Status |
|---|---|
| Approved PPAs (approved, ongoing, completed) compiled automatically from the stored records; no re-encoding | Done |
| Standard AIP form layout: AIP reference code, PPA description, implementing office, start/completion, expected outputs, funding source, PS/MOOE/CO/total, climate change adaptation/mitigation, CC typology code | Done |
| Grouped by sector (General Public, Social, Economic, Other Services) then implementing office, with office subtotals, sector totals and a grand total | Done |
| Amounts in pesos or in thousand pesos (₱'000); preview filters by sector, office and fund source | Done |
| Summaries by sector, fund source and office | Done |
| Readiness checks: approved PPAs with funding errors or missing AIP fields (block saving), undecided PPAs that will be left out, possible duplicates across offices, changes since the last version | Done |
| Saved versions (snapshots) by the MPDO reviewer; compare any version with current data (added, removed, changed) | Works (MySQL) |
| An MPDO reviewer finalizes one version, optionally with the adoption resolution; later versions are amendments and supersede the earlier final | Works (MySQL) |
| Print-ready AIP form (legal landscape) with Prepared/Attested/Approved signatories; CSV export of the current AIP or any version | Done |
| Reviewers generate and finalize; Administrators, Finance and Auditors view; Encoders have no access; versions and finalization in the activity log | Enforced |

Consolidation logic is in `includes/aip.php`; the AIP form table is `includes/aip-table.php`.

## Linking module

| Feature | Status |
|---|---|
| Every PPA linked to its implementing office, fund sources (with amounts) and approval records | Done |
| Approval records (SB/LDC resolutions, executive orders, memoranda) as their own records: number, type, date, subject; one record can cover many PPAs | Works (MySQL) |
| Attach an existing record, register and attach a new one, or unlink (reason required); only approved PPAs can be linked | Works (MySQL) |
| Resolutions are registered and linked here (the final decision itself only records the approval status) | Done |
| Finalizing the AIP with a resolution links the record to the AIP version | Done |
| Office pages: the office's PPAs, fund sources used, approval records and user accounts | Done |
| Linked-records panel on every PPA: office, fund sources, approval records, AIP versions and the people who encoded, recommended and decided | Done |
| Graph view of one PPA's connections | Done |
| Link checks: approved PPAs without a record, records not linked to anything, records on unapproved PPAs, unknown or inactive fund sources, offices with open PPAs but no active encoder | Done |
| Reviewers manage records; Administrators, Finance and Auditors view; Encoders see their own office only | Enforced |
| Link changes recorded in the PPA history and activity log | Works (MySQL) |

Linking logic is in `includes/links.php`. Sample data includes an approved PPA with no record
(PPA-2027-011) and an LDC resolution not yet linked, to demonstrate the checks.

## Dashboard module

| Feature | Status |
|---|---|
| Computed live from the stored records every time it loads; updates as users encode, review, approve and allocate | Done |
| Key indicators: submitted PPAs, pending decisions (with overdue count), approved for the AIP (amount and approval rate), total proposed cost, implementation progress | Done |
| Process pipeline: count and amount at every stage, each linked to its queue | Done |
| Approval status chart (approved, pending, returned, disapproved) and average time in MPDO review, in budget review and to final approval | Done |
| Approved vs requested budget, and expenses not yet audited / flagged | Done |
| Fund distribution: programmed and pending against each fund ceiling | Done |
| Distribution by sector and by office (proposed vs approved), linked to office pages | Done |
| Monitoring and compliance: overdue submissions, funding issues, link issues, approved PPAs without approval records, AIP readiness, oldest pending items | Done |
| Recent activity timeline from PPA histories (what, which PPA, by whom, when) | Done |
| Filters by office and sector; printable | Done |
| Role-aware: encoders see their own office including drafts; MPDO roles see all offices; administrators also see accounts and access today | Enforced |

Indicator logic is in `includes/dashboard.php`.

## What works now

- Every module reads and writes the MySQL database through PDO prepared statements; records are
  shared between users and kept after the browser closes.
- Login with password + emailed-style 6-digit code; failed attempts and codes are stored in the
  database (`login_attempts`, `login_otps`), so the 5-attempt lockout applies across browsers.
- Sidebar, breadcrumbs, mobile menu, tabs, filters, search, pagination and record counts.
- Dashboard, fund and consolidation totals calculated from the database.
- CSV export of any table and a print-ready consolidated AIP report (A4 landscape).
- Matching browser and server validation on every form; database constraints as a second check.

## Development-only components

| Component | Location | Before real use |
|---|---|---|
| Sign-in code shown on screen instead of emailed (`MAIL_ENABLED = false`) | `config/mail.php`, `includes/mailer.php` | Set up SMTP delivery |
| Test accounts with fictional `@indang.gov.ph` emails and the shared password `Dev@2026` | `database/aip_sample_data.sql` | Do not import the sample file; create real accounts |
| Test-account list on the login page and the development banner | `config/app.php` (`show_sample_accounts`) | Set to `false` |
| Starting `admin` account (password `Dev@2026`, must be changed at first sign-in) | `database/aip_db.sql` | Change the password at first sign-in |
| Fiscal year (`AIP_FISCAL_YEAR = 2027`) | `includes/helpers.php` | AIP cycle settings |

## Not yet available

- Department and fiscal year management screens.
- Scanned copies of approval records (file upload), physical progress updates and marking a PPA completed.
- Email delivery: sign-in codes and temporary passwords are shown on screen instead.
- Re-importing `database/aip_db.sql` deletes all data; back up from phpMyAdmin (Export) first.
