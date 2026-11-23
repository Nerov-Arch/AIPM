# Running the AIP System with XAMPP

A step-by-step guide to running the Web-Based Annual Investment Program (AIP) System on your own computer.

> **Development build.** All data is saved in a **MySQL database** (`aip_db`), so changes are kept and shared
> by everyone using the system. The accounts below are **test accounts for demonstration only**, not real users.

---

## 1. What you need

| Item | Notes |
|---|---|
| XAMPP with **PHP 8.0 or newer** | Download it from apachefriends.org. Any current version works. |
| XAMPP's **MySQL** and **phpMyAdmin** | Included with XAMPP. |
| A web browser | Chrome, Edge or Firefox. |
| The project files | The `claude` folder from the GitHub repo `Nerov-Arch/AIPM`. |

## 2. Get the files

**Option A: download a ZIP (easiest)**

1. Open https://github.com/Nerov-Arch/AIPM
2. If the `claude` pull request hasn't been merged yet, switch the branch selector (top left) to **`claude-folder`**.
3. Click **Code → Download ZIP** and extract it.
4. Inside the extracted folder, find the **`claude`** folder.

**Option B: Git**

```bash
git clone https://github.com/Nerov-Arch/AIPM.git
cd AIPM
git checkout claude-folder   # skip this once the pull request is merged into main
```

## 3. Put the folder in `htdocs`

Copy the **`claude`** folder into XAMPP's web folder:

| System | Copy to |
|---|---|
| Windows | `C:\xampp\htdocs\claude` |
| macOS | `/Applications/XAMPP/htdocs/claude` |
| Linux | `/opt/lampp/htdocs/claude` |

Make sure `login.php` sits directly inside `htdocs\claude\`, **not** in `htdocs\claude\claude\`. Extracting a ZIP
sometimes adds a second folder level; if it does, move the inner folder up.

## 4. Start Apache and MySQL

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and next to **MySQL**. Both turn green.

## 5. Create the database (first time only)

1. Open **http://localhost/phpmyadmin/**.
2. Click the **Import** tab (at the top, with no database selected).
3. Choose `claude\database\aip_db.sql` and click **Import**. This creates the `aip_db` database with its
   tables, sectors, offices, fund sources and one `admin` account.
4. Click **Import** again, choose `claude\database\aip_sample_data.sql` and click **Import**. This adds the
   test accounts below and sample PPAs, budgets, records and expenses. Skip this step for real use.

XAMPP's MySQL user `root` has no password by default, which is what the system expects. If you set a
password, copy `config\database.local.example.php` to `config\database.local.php` and enter it there.

## 6. Open the system

Go to: **http://localhost/claude/**

The page takes you to the login screen.

## 7. Sign in

Signing in has **two steps**:

1. Enter the username (or government email) and the password. For **every** test account the password is:

   ```
   Dev@2026
   ```

2. Enter the **6-digit sign-in code**. In the real system this code is emailed to the account's
   `@indang.gov.ph` address. Email sending is not set up in this version, so the code is shown on the
   screen in a box marked **Development mode**. Type it in and click **Verify and sign in**.

The login page also lists the accounts; click one to fill in the username.

| Username | Password | Name | Role | Office | Government email | Status |
|---|---|---|---|---|---|---|
| `admin` | `Dev@2026` | Maria Villanueva | Administrator | MPDO | m.villanueva@indang.gov.ph | Active |
| `jbautista` | `Dev@2026` | Jose Bautista | Reviewer | MPDO | j.bautista@indang.gov.ph | Active |
| `alim` | `Dev@2026` | Ana Lim | Reviewer (MPDC) | MPDO | a.lim@indang.gov.ph | Active |
| `tnavarro` | `Dev@2026` | Teresa Navarro | Finance | MBO (Budget Office) | t.navarro@indang.gov.ph | Active |
| `raquino` | `Dev@2026` | Ramon Aquino | Auditor | IAS (Internal Audit) | r.aquino@indang.gov.ph | Active |
| `rdelacruz` | `Dev@2026` | Rafael Dela Cruz | Encoder | MEO (Engineering) | r.delacruz@indang.gov.ph | Active |
| `lgarcia` | `Dev@2026` | Liza Garcia | Encoder | MSWDO (Social Welfare) | l.garcia@indang.gov.ph | Active |
| `cramos` | `Dev@2026` | Carmen Ramos | Encoder | MENRO (Environment) | c.ramos@indang.gov.ph | Active |
| `pmendoza` | `Dev@2026` | Paolo Mendoza | Encoder | MAO (Agriculture) | p.mendoza@indang.gov.ph | **Inactive**: sign-in is refused (shows the inactive-account check) |

The email addresses are sample addresses for testing; no email is sent to them.

### What each role can do

| Role | Can do |
|---|---|
| **Administrator** | Create, edit, deactivate users and reset passwords (every account needs an `@indang.gov.ph` email); manage fund sources; view the activity log, all offices, the consolidated AIP and all queues. Cannot encode, review or approve PPAs. |
| **Encoder** | Encode, save as draft and submit PPAs for **their own office only**; still correct a PPA while it is Pending Review; revise returned PPAs; record expenses with receipt photos once a PPA is approved. |
| **Reviewer** (MPDO) | One role for both review and approval: evaluate submitted PPAs with the checklist, return them or forward them to Finance; after Finance approves the budget, give the final approval (enter the climate change expenditure and set the approval status); manage funds and approval records; generate and finalize AIP versions. |
| **Finance** (Budget Office) | Review the requested budget of PPAs forwarded by the MPDO; approve it as requested or at a lower amount (remarks required), or return it to the MPDO; manage fund sources. |
| **Auditor** (Internal Audit) | View every expense, its receipt and the expenditure timeline of each PPA; mark expenses verified or flagged (note required). |

Rules you'll notice: no one can act on a PPA they encoded; Finance cannot approve more than was requested; an expense cannot exceed the remaining approved budget.

## 8. Suggested walkthrough (about 15 minutes)

This shows the whole process from encoding to expenditure audit:

1. **Encode.** Sign in as `rdelacruz`. Go to **Project Encoding → Encode New PPA**, fill in the form and click **Submit for Review**. Open it again and click **Edit (under review)** to see that the office can still correct it; the change appears in the history.
2. **MPDO review.** Sign in as `jbautista`. Open **Review & Approval**, open the new PPA (it shows an "edited while under review" note), tick every checklist item and click **Forward to Finance**. (Or **Return for revision** with remarks.)
3. **Budget review.** Sign in as `tnavarro`. Open **Budget Review**, open the PPA, and either keep the requested amounts or lower one (then add remarks). Click **Approve budget**.
4. **Final approval.** Sign in as `jbautista` (or `alim`). Open **Review & Approval**, open the PPA (now **For Final Approval**), enter the climate change expenditure if any, choose the approval status **Approved**, and click **Save approval status**.
5. **Spend and audit.** Sign in as `rdelacruz`, open **Implementation → Expenses** for the PPA, and record an expense with a photo of a receipt (any JPG/PNG works for testing). Then sign in as `raquino`, open **Expenditure Audit**, open the expense and **Verify** or **Flag** it.
6. **See the results:**
   - **Dashboard**: counts, pipeline, budget and audit tiles update.
   - **Fund Monitoring → Requested vs approved**: the requested and approved budget side by side.
   - **AIP Consolidation**: the PPA appears in the AIP form under its sector and office.
   - **Project Linking**: the PPA is connected to its office and fund source; an SB resolution can be linked here.
7. **Administer.** Sign in as `admin`. Open **User Management** to add a user (a temporary password is shown once) and check the **Activity log**.

Everything you do is saved in the database: it is still there after closing the browser, and other people
signed in on other computers see the same records.

## 9. Resetting the sample data

Import `aip_db.sql` and then `aip_sample_data.sql` again in phpMyAdmin (section 5). **This deletes everything
entered so far**, so export a backup first (phpMyAdmin → `aip_db` → **Export**) if you need it.

Uploaded receipt photos are saved as files in `claude\uploads\receipts\`. Delete the files in that folder
(keep `.gitkeep`) when you reset the data.

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| Apache won't start (port 80 busy) | Another program, often Skype or IIS, is using port 80. Close it, or in XAMPP click **Config → httpd.conf**, change `Listen 80` to `Listen 8080`, restart Apache and open http://localhost:8080/claude/ |
| "The system cannot connect to the database" | Start **MySQL** in XAMPP and make sure `aip_db.sql` was imported (section 5). If your MySQL user has a password, put it in `config\database.local.php`. |
| MySQL won't start (port 3306 busy) | Another MySQL is running. Stop it, or change the port in XAMPP and in `config\database.local.php`. |
| "Not Found" / 404 | The folder is in the wrong place or is nested twice. Check that `C:\xampp\htdocs\claude\login.php` exists. |
| Page shows PHP code or a syntax error | XAMPP's PHP is older than 8.0. Install a current XAMPP. |
| "Too many failed attempts" | Sign-in locks for 5 minutes after 5 wrong passwords (5 wrong sign-in codes also count as a failed attempt). Wait 5 minutes. |
| "The code has expired" | Codes are valid for 5 minutes. Sign in again to get a new code, or click **Send a new code**. |
| "Not linked to an @indang.gov.ph government email" | The account's email is not on the government domain. Sign in as `admin` and change the email under **User Management**. |
| Receipt upload says the file is too large | The limit is 5 MB. If even small files fail, raise `upload_max_filesize` and `post_max_size` to at least 8M in XAMPP's `php.ini` and restart Apache. |
| "Receipt could not be saved on the server" | Make sure the `uploads\receipts` folder exists and is not read-only. |
| My changes disappeared | The SQL files were imported again, which resets the data. |
| Forced to the **My Account** page | The account has a temporary password (for example, one an admin just created or reset). Set a new password to continue. |
| Login page shows "account is no longer active" | An administrator deactivated that account. Sign in as `admin` and reactivate it under **User Management**. |

## 11. Important notes

- The test accounts and the shared password `Dev@2026` exist **only for development and demonstration**. They must
  be removed and replaced with real accounts before any real use by the client.
- Sign-in codes are shown on screen because email sending is off (`config/mail.php`). Turning it on needs an
  SMTP relay set up for PHP; never put SMTP passwords in the repository.
- For real use: import only `aip_db.sql` (not the sample file), sign in as `admin` and change its password,
  create the real accounts, and set `show_sample_accounts` to `false` in `config/app.php`.
- Never commit `config/database.local.php`; it is listed in `.gitignore`.
