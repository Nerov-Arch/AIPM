# Running the AIP System with XAMPP

A step-by-step guide to running the Web-Based Annual Investment Program (AIP) System on your own computer.

> **Development build.** The system runs on **sample data kept in your browser session**. No database is
> connected yet, so MySQL is not needed. Anything you add or change lasts only until you close the browser.
> The accounts below are **test accounts for demonstration only**, not real users.

---

## 1. What you need

| Item | Notes |
|---|---|
| XAMPP with **PHP 8.0 or newer** | Download it from apachefriends.org. Any current version works. |
| A web browser | Chrome, Edge or Firefox. |
| The project files | The `claude` folder from the GitHub repo `Nerov-Arch/AIPM`. |

MySQL and phpMyAdmin are **not** needed for this build.

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

## 4. Start Apache

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**. It turns green.
3. You do not need to start MySQL.

## 5. Open the system

Go to: **http://localhost/claude/**

The page takes you to the login screen.

## 6. Sign in

The password for **every** test account is:

```
Dev@2026
```

You can sign in with the **username or the email**. The login page also lists the accounts; click one to fill in the username.

| Username | Password | Name | Role | Office | Status |
|---|---|---|---|---|---|
| `admin` | `Dev@2026` | Maria Villanueva | Administrator | MPDO | Active |
| `jbautista` | `Dev@2026` | Jose Bautista | Reviewer | MPDO | Active |
| `alim` | `Dev@2026` | Ana Lim | Approver | MPDO | Active |
| `rdelacruz` | `Dev@2026` | Rafael Dela Cruz | Encoder | MEO (Engineering) | Active |
| `lgarcia` | `Dev@2026` | Liza Garcia | Encoder | MSWDO (Social Welfare) | Active |
| `cramos` | `Dev@2026` | Carmen Ramos | Encoder | MENRO (Environment) | Active |
| `pmendoza` | `Dev@2026` | Paolo Mendoza | Encoder | MAO (Agriculture) | **Inactive**: sign-in is refused (shows the inactive-account check) |

Emails follow the pattern `username@indang.example`, for example `j.bautista@indang.example`. They are not real addresses.

### What each role can do

| Role | Can do |
|---|---|
| **Administrator** | Create, edit, deactivate users and reset passwords; manage fund sources; view the activity log, all offices, the consolidated AIP and all queues. Cannot encode, review or approve PPAs. |
| **Encoder** | Encode, save as draft, submit and revise PPAs for **their own office only**. Sees only their office's records. |
| **Reviewer** (MPDO) | Evaluate submitted PPAs with the checklist, return them with remarks, or recommend them; manage fund sources and allocations; record approval documents and link them; generate AIP versions. |
| **Approver** | Approve, disapprove or send back recommended PPAs; record SB resolutions; finalize the consolidated AIP. |

Rules you'll notice: no one can review a PPA they encoded, and the person who recommended a PPA cannot also approve it.

## 7. Suggested walkthrough (about 10 minutes)

This shows the whole process from encoding to the consolidated AIP:

1. **Encode.** Sign in as `rdelacruz`. Go to **Project Encoding → Encode New PPA**, fill in the form and click **Submit for Review**.
2. **Review.** Sign out, then sign in as `jbautista`. Open **Review** in the menu, open the new PPA, tick every checklist item and click **Recommend for approval**. (Or **Return for revision** with remarks, then sign in as `rdelacruz` again to revise and resubmit.)
3. **Approve.** Sign in as `alim`. Open **Authorization** in the menu, open the PPA, optionally enter an SB resolution number, and click **Approve**.
4. **See the results:**
   - **Dashboard**: counts, pipeline and fund totals update.
   - **AIP Consolidation**: the PPA now appears in the AIP form under its sector and office.
   - **Project Linking**: the PPA is connected to its office, fund source and resolution.
   - **Fund Monitoring**: the fund's programmed amount increases.
5. **Administer.** Sign in as `admin`. Open **User Management** to add a user (a temporary password is shown once) and check the **Activity log**.

Changes survive signing out and switching accounts, **as long as you keep using the same browser window**.

## 8. Resetting the sample data

Close **all** browser windows, or open a private/incognito window. The system starts fresh with the original sample data.

## 9. Troubleshooting

| Problem | Fix |
|---|---|
| Apache won't start (port 80 busy) | Another program, often Skype or IIS, is using port 80. Close it, or in XAMPP click **Config → httpd.conf**, change `Listen 80` to `Listen 8080`, restart Apache and open http://localhost:8080/claude/ |
| "Not Found" / 404 | The folder is in the wrong place or is nested twice. Check that `C:\xampp\htdocs\claude\login.php` exists. |
| Page shows PHP code or a syntax error | XAMPP's PHP is older than 8.0. Install a current XAMPP. |
| "Too many failed attempts" | Sign-in locks for 5 minutes after 5 wrong passwords. Wait, or close the browser to reset. |
| My changes disappeared | The browser was closed or a different browser/window was used. Data is not saved to a database yet. |
| Forced to the **My Account** page | The account has a temporary password (for example, one an admin just created or reset). Set a new password to continue. |
| Login page shows "account is no longer active" | An administrator deactivated that account. Sign in as `admin` and reactivate it under **User Management**. |

## 10. Important notes

- The test accounts and the shared password `Dev@2026` exist **only for development and demonstration**. They must
  be removed and replaced with real accounts before any real use by the client.
- No database is connected. `config/database.php` is an inactive placeholder; connecting MySQL is a separate,
  planned phase.
