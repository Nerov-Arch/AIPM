-- =============================================================================
--  Web-Based Annual Investment Program (AIP) System
--  Municipal Planning and Development Office (MPDO), Indang, Cavite
--
--  Database schema for MySQL / MariaDB (phpMyAdmin, XAMPP)
--  Tested on MariaDB 10.4+ and MySQL 8.0+
--
--  HOW TO IMPORT
--    1. Open http://localhost/phpmyadmin
--    2. Click "Import", choose this file, and click "Import" (or "Go").
--       This creates the database `aip_db`, its tables, views and the
--       reference data (offices, sectors, fund sources) and one administrator.
--    3. Optional: import aip_sample_data.sql afterwards to load test accounts
--       and sample PPAs (same data as the development version).
--
--  WARNING: running this file again DROPS and recreates every table.
--
--  FIRST SIGN-IN
--    Username: admin   Temporary password: Dev@2026
--    The account must change its password at first sign-in. Change the email
--    to the real administrator's @indang.gov.ph address in User Management.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `aip_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `aip_db`;

SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS `v_fund_totals`;
DROP VIEW  IF EXISTS `v_ppa_summary`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `ppa_expenses`;
DROP TABLE IF EXISTS `aip_version_entries`;
DROP TABLE IF EXISTS `aip_versions`;
DROP TABLE IF EXISTS `ppa_approval_records`;
DROP TABLE IF EXISTS `approval_records`;
DROP TABLE IF EXISTS `ppa_history`;
DROP TABLE IF EXISTS `ppa_review_checklist`;
DROP TABLE IF EXISTS `ppa_fund_allocations`;
DROP TABLE IF EXISTS `ppa_budgets`;
DROP TABLE IF EXISTS `ppas`;
DROP TABLE IF EXISTS `fund_source_ceilings`;
DROP TABLE IF EXISTS `fund_sources`;
DROP TABLE IF EXISTS `login_otps`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `sectors`;
DROP TABLE IF EXISTS `offices`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
--  1. Reference tables
-- -----------------------------------------------------------------------------

-- Implementing offices / departments
CREATE TABLE `offices` (
  `code`       VARCHAR(10)  NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  UNIQUE KEY `uq_offices_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AIP sectors (General Public Services, Social, Economic, Other Services)
CREATE TABLE `sectors` (
  `code`       VARCHAR(5)   NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `sort_order` TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  2. User Management
-- -----------------------------------------------------------------------------

CREATE TABLE `users` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`             VARCHAR(30)  NOT NULL,
  `full_name`            VARCHAR(120) NOT NULL,
  `email`                VARCHAR(150) NOT NULL,                 -- must be an @indang.gov.ph address (OTP is sent here)
  `password_hash`        VARCHAR(255) NOT NULL,                 -- PHP password_hash()
  `role`                 ENUM('Administrator','Encoder','Reviewer','Finance','Auditor') NOT NULL,
  `office_code`          VARCHAR(10)  NOT NULL,
  `position`             VARCHAR(120) NOT NULL DEFAULT '',
  `status`               ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `must_change_password` TINYINT(1)   NOT NULL DEFAULT 1,       -- forced to My Account on next sign-in
  `last_login_at`        DATETIME     NULL,
  `created_by`           INT UNSIGNED NULL,
  `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_office` (`office_code`),
  KEY `idx_users_role_status` (`role`, `status`),
  CONSTRAINT `fk_users_office`     FOREIGN KEY (`office_code`) REFERENCES `offices` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_users_email` CHECK (`email` LIKE '%_@indang.gov.ph'),
  CONSTRAINT `chk_users_username` CHECK (CHAR_LENGTH(`username`) >= 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time sign-in codes (step 2 of sign-in). Only the hash of the code is stored.
CREATE TABLE `login_otps` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `code_hash`  VARCHAR(255) NOT NULL,
  `sent_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME     NOT NULL,                            -- sent_at + 5 minutes
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,              -- max 5
  `used_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_otps_user` (`user_id`, `sent_at`),
  CONSTRAINT `fk_otps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sign-in attempts, used for the lockout (5 failures = 5-minute lock)
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identity`     VARCHAR(150) NOT NULL,                          -- username or email typed
  `ip_address`   VARCHAR(45)  NOT NULL,
  `success`      TINYINT(1)   NOT NULL DEFAULT 0,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_identity` (`identity`, `attempted_at`),
  KEY `idx_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  3. Fund Source Monitoring
-- -----------------------------------------------------------------------------

CREATE TABLE `fund_sources` (
  `code`         VARCHAR(10)  NOT NULL,                          -- short code, e.g. DF, GF
  `name`         VARCHAR(120) NOT NULL,
  `account_code` VARCHAR(20)  NOT NULL,
  `category`     ENUM('General Fund','Special Purpose Fund','Trust Fund','External Source') NOT NULL,
  `description`  VARCHAR(500) NOT NULL DEFAULT '',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  UNIQUE KEY `uq_funds_name` (`name`),
  UNIQUE KEY `uq_funds_account` (`account_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ceiling of each fund source per fiscal year
CREATE TABLE `fund_source_ceilings` (
  `fund_code`   VARCHAR(10)   NOT NULL,
  `fiscal_year` SMALLINT UNSIGNED NOT NULL,
  `ceiling`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`fund_code`, `fiscal_year`),
  CONSTRAINT `fk_ceilings_fund` FOREIGN KEY (`fund_code`) REFERENCES `fund_sources` (`code`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_ceilings_amount` CHECK (`ceiling` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  4. Project Encoding (PPAs) and Review and Approval
-- -----------------------------------------------------------------------------

CREATE TABLE `ppas` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref`                 VARCHAR(20)  NOT NULL,                   -- AIP reference code, e.g. PPA-2027-001
  `fiscal_year`         SMALLINT UNSIGNED NOT NULL,
  `title`               VARCHAR(200) NOT NULL,
  `ppa_type`            ENUM('Program','Project','Activity') NULL,          -- NULL allowed while Draft
  `program`             VARCHAR(150) NOT NULL DEFAULT '',
  `sector_code`         VARCHAR(5)   NULL,
  `description`         TEXT         NULL,
  `expected_output`     TEXT         NULL,
  `office_code`         VARCHAR(10)  NOT NULL,                   -- implementing office
  `start_date`          DATE         NULL,
  `end_date`            DATE         NULL,
  -- Expense classes. Before Finance decides these are the requested amounts;
  -- after the budget is approved they hold the approved amounts (see ppa_budgets).
  `ps`                  DECIMAL(15,2) NOT NULL DEFAULT 0.00,      -- Personal Services
  `mooe`                DECIMAL(15,2) NOT NULL DEFAULT 0.00,      -- Maintenance and Other Operating Expenses
  `co`                  DECIMAL(15,2) NOT NULL DEFAULT 0.00,      -- Capital Outlay
  `total_cost`          DECIMAL(15,2) AS (`ps` + `mooe` + `co`) STORED,
  `fund_code`           VARCHAR(10)  NULL,                       -- primary fund source
  `allocation_basis`    VARCHAR(500) NULL,                       -- basis when split across funds
  -- Climate change expenditure: entered by the MPDO at final approval
  `cc_adaptation`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cc_mitigation`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cc_typology`         VARCHAR(20)  NULL,
  `status`              ENUM('draft','for_review','returned','for_budget','recommended','approved','disapproved','ongoing','completed') NOT NULL DEFAULT 'draft',
  `progress`            TINYINT UNSIGNED NOT NULL DEFAULT 0,     -- physical progress, 0-100 %
  `remarks`             TEXT         NULL,                       -- latest return / send-back remarks
  `created_by`          INT UNSIGNED NULL,
  `submitted_at`        DATETIME     NULL,
  `edited_in_review_at` DATETIME     NULL,                       -- office edited while Pending Review
  `forwarded_by`        INT UNSIGNED NULL,                       -- MPDO reviewer who forwarded to Finance
  `forwarded_at`        DATETIME     NULL,
  `decided_by`          INT UNSIGNED NULL,                       -- MPDO reviewer who gave the final decision
  `decided_at`          DATETIME     NULL,
  `decision_remarks`    TEXT         NULL,
  `updated_by`          INT UNSIGNED NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ppas_ref` (`ref`),
  UNIQUE KEY `uq_ppas_title` (`fiscal_year`, `office_code`, `title`),   -- no duplicate PPA per office and year
  KEY `idx_ppas_status` (`fiscal_year`, `status`),
  KEY `idx_ppas_office` (`office_code`),
  KEY `idx_ppas_sector` (`sector_code`),
  KEY `idx_ppas_fund` (`fund_code`),
  CONSTRAINT `fk_ppas_office`       FOREIGN KEY (`office_code`)  REFERENCES `offices` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ppas_sector`       FOREIGN KEY (`sector_code`)  REFERENCES `sectors` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ppas_fund`         FOREIGN KEY (`fund_code`)    REFERENCES `fund_sources` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ppas_created_by`   FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ppas_forwarded_by` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ppas_decided_by`   FOREIGN KEY (`decided_by`)   REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ppas_updated_by`   FOREIGN KEY (`updated_by`)   REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_ppas_amounts`  CHECK (`ps` >= 0 AND `mooe` >= 0 AND `co` >= 0 AND `cc_adaptation` >= 0 AND `cc_mitigation` >= 0),
  CONSTRAINT `chk_ppas_cc`       CHECK (`cc_adaptation` + `cc_mitigation` <= `ps` + `mooe` + `co`),
  CONSTRAINT `chk_ppas_dates`    CHECK (`start_date` IS NULL OR `end_date` IS NULL OR `end_date` >= `start_date`),
  CONSTRAINT `chk_ppas_progress` CHECK (`progress` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requested vs approved budget (Finance budget review). One row per PPA once forwarded to Finance.
CREATE TABLE `ppa_budgets` (
  `ppa_id`          INT UNSIGNED  NOT NULL,
  `requested_ps`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `requested_mooe`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `requested_co`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `requested_total` DECIMAL(15,2) AS (`requested_ps` + `requested_mooe` + `requested_co`) STORED,
  `approved_ps`     DECIMAL(15,2) NULL,                          -- NULL until Finance decides
  `approved_mooe`   DECIMAL(15,2) NULL,
  `approved_co`     DECIMAL(15,2) NULL,
  `approved_total`  DECIMAL(15,2) AS (`approved_ps` + `approved_mooe` + `approved_co`) STORED,
  `decided_by`      INT UNSIGNED  NULL,                          -- Finance user
  `decided_at`      DATETIME      NULL,
  `remarks`         TEXT          NULL,                          -- required when approved < requested
  PRIMARY KEY (`ppa_id`),
  CONSTRAINT `fk_budgets_ppa`        FOREIGN KEY (`ppa_id`)     REFERENCES `ppas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budgets_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_budgets_amounts` CHECK (`requested_ps` >= 0 AND `requested_mooe` >= 0 AND `requested_co` >= 0
                                          AND (`approved_ps` IS NULL OR `approved_ps` >= 0)
                                          AND (`approved_mooe` IS NULL OR `approved_mooe` >= 0)
                                          AND (`approved_co` IS NULL OR `approved_co` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Split of a PPA's cost across fund sources. No rows = charged entirely to ppas.fund_code.
CREATE TABLE `ppa_fund_allocations` (
  `ppa_id`    INT UNSIGNED  NOT NULL,
  `fund_code` VARCHAR(10)   NOT NULL,
  `amount`    DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`ppa_id`, `fund_code`),
  KEY `idx_alloc_fund` (`fund_code`),
  CONSTRAINT `fk_alloc_ppa`  FOREIGN KEY (`ppa_id`)    REFERENCES `ppas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_fund` FOREIGN KEY (`fund_code`) REFERENCES `fund_sources` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `chk_alloc_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review checklist items confirmed by the MPDO before forwarding to Finance
CREATE TABLE `ppa_review_checklist` (
  `ppa_id`     INT UNSIGNED NOT NULL,
  `item_key`   ENUM('complete','alignment','cost','funding','schedule') NOT NULL,
  `checked_by` INT UNSIGNED NULL,
  `checked_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ppa_id`, `item_key`),
  CONSTRAINT `fk_checklist_ppa`  FOREIGN KEY (`ppa_id`)     REFERENCES `ppas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checklist_user` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Status history of every PPA (encoding, review, budget, approval, expenses)
CREATE TABLE `ppa_history` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ppa_id`     INT UNSIGNED NOT NULL,
  `action`     VARCHAR(200) NOT NULL,                          -- e.g. "Returned for revision"
  `status`     VARCHAR(20)  NOT NULL,                          -- PPA status after the action
  `remarks`    TEXT         NULL,
  `user_id`    INT UNSIGNED NULL,
  `actor_name` VARCHAR(160) NOT NULL,                          -- kept even if the account is removed
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_ppa` (`ppa_id`, `created_at`),
  CONSTRAINT `fk_history_ppa`  FOREIGN KEY (`ppa_id`)  REFERENCES `ppas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  5. Linking Module (approval records)
-- -----------------------------------------------------------------------------

CREATE TABLE `approval_records` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_no`   VARCHAR(60)  NOT NULL,                          -- e.g. SB Res. No. 2026-098
  `record_type` ENUM('SB Resolution','LDC Resolution','Executive Order','Memorandum') NOT NULL,
  `record_date` DATE         NOT NULL,
  `subject`     VARCHAR(300) NOT NULL,
  `remarks`     TEXT         NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_records_no` (`record_no`),
  CONSTRAINT `fk_records_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which PPAs each approval record covers (many-to-many)
CREATE TABLE `ppa_approval_records` (
  `ppa_id`             INT UNSIGNED NOT NULL,
  `approval_record_id` INT UNSIGNED NOT NULL,
  `linked_by`          INT UNSIGNED NULL,
  `linked_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ppa_id`, `approval_record_id`),
  KEY `idx_links_record` (`approval_record_id`),
  CONSTRAINT `fk_links_ppa`    FOREIGN KEY (`ppa_id`)             REFERENCES `ppas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_links_record` FOREIGN KEY (`approval_record_id`) REFERENCES `approval_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_links_user`   FOREIGN KEY (`linked_by`)          REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  6. AIP Consolidation (saved versions)
-- -----------------------------------------------------------------------------

CREATE TABLE `aip_versions` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fiscal_year`        SMALLINT UNSIGNED NOT NULL,
  `version_no`         SMALLINT UNSIGNED NOT NULL,
  `kind`               ENUM('Original','Amendment') NOT NULL,
  `status`             ENUM('draft','final','superseded') NOT NULL DEFAULT 'draft',
  `remarks`            TEXT          NULL,
  `ppa_count`          INT UNSIGNED  NOT NULL DEFAULT 0,
  `total_ps`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_mooe`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_co`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_cost`         DECIMAL(15,2) AS (`total_ps` + `total_mooe` + `total_co`) STORED,
  `total_cc_adaptation` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_cc_mitigation` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_by`         INT UNSIGNED  NULL,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalized_by`       INT UNSIGNED  NULL,
  `finalized_at`       DATETIME      NULL,
  `approval_record_id` INT UNSIGNED  NULL,                       -- adoption resolution (optional)
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_versions_no` (`fiscal_year`, `version_no`),
  CONSTRAINT `fk_versions_created_by`   FOREIGN KEY (`created_by`)         REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_versions_finalized_by` FOREIGN KEY (`finalized_by`)       REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_versions_record`       FOREIGN KEY (`approval_record_id`) REFERENCES `approval_records` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot of every PPA row in a saved AIP version (does not change when the PPA changes later)
CREATE TABLE `aip_version_entries` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `aip_version_id`  INT UNSIGNED  NOT NULL,
  `ppa_id`          INT UNSIGNED  NULL,
  `ref`             VARCHAR(20)   NOT NULL,
  `title`           VARCHAR(200)  NOT NULL,
  `ppa_type`        VARCHAR(10)   NOT NULL,
  `program`         VARCHAR(150)  NOT NULL DEFAULT '',
  `sector_code`     VARCHAR(5)    NOT NULL,
  `office_code`     VARCHAR(10)   NOT NULL,
  `start_date`      DATE          NULL,
  `end_date`        DATE          NULL,
  `expected_output` TEXT          NULL,
  `fund_label`      VARCHAR(200)  NOT NULL DEFAULT '',            -- e.g. "GF ₱2,000,000.00 / TF ₱500,000.00"
  `status`          VARCHAR(20)   NOT NULL,
  `ps`              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `mooe`            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `co`              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_cost`      DECIMAL(15,2) AS (`ps` + `mooe` + `co`) STORED,
  `cc_adaptation`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cc_mitigation`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cc_typology`     VARCHAR(20)   NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entries_ref` (`aip_version_id`, `ref`),
  CONSTRAINT `fk_entries_version` FOREIGN KEY (`aip_version_id`) REFERENCES `aip_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entries_ppa`     FOREIGN KEY (`ppa_id`)         REFERENCES `ppas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  7. Expenses and Audit (implementation)
-- -----------------------------------------------------------------------------

CREATE TABLE `ppa_expenses` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ppa_id`                INT UNSIGNED  NOT NULL,
  `expense_date`          DATE          NOT NULL,
  `description`           VARCHAR(200)  NOT NULL,
  `amount`                DECIMAL(15,2) NOT NULL,
  `receipt_no`            VARCHAR(40)   NOT NULL,              -- OR / invoice / voucher number
  `receipt_file`          VARCHAR(100)  NULL,                  -- random file name in uploads/receipts/
  `receipt_original_name` VARCHAR(120)  NULL,
  `receipt_mime`          VARCHAR(50)   NULL,
  `receipt_size`          INT UNSIGNED  NULL,                  -- bytes, max 5 MB
  `recorded_by`           INT UNSIGNED  NULL,
  `recorded_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `audit_status`          ENUM('unverified','verified','flagged') NOT NULL DEFAULT 'unverified',
  `audited_by`            INT UNSIGNED  NULL,
  `audited_at`            DATETIME      NULL,
  `audit_note`            VARCHAR(500)  NULL,                  -- required when flagged
  PRIMARY KEY (`id`),
  KEY `idx_expenses_ppa` (`ppa_id`, `expense_date`),
  KEY `idx_expenses_audit` (`audit_status`),
  CONSTRAINT `fk_expenses_ppa`         FOREIGN KEY (`ppa_id`)      REFERENCES `ppas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expenses_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_audited_by`  FOREIGN KEY (`audited_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_expenses_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_expenses_receipt_size` CHECK (`receipt_size` IS NULL OR `receipt_size` <= 5242880)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  8. Activity log (who did what and when)
-- -----------------------------------------------------------------------------

CREATE TABLE `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`     VARCHAR(40)  NOT NULL,                          -- e.g. auth.login, ppa.approve, expense.flag
  `target`     VARCHAR(100) NOT NULL DEFAULT '',               -- e.g. PPA ref, username, fund code
  `details`    TEXT         NULL,
  `user_id`    INT UNSIGNED NULL,
  `username`   VARCHAR(150) NOT NULL DEFAULT '',               -- kept for failed sign-ins of unknown users
  `actor_name` VARCHAR(120) NOT NULL DEFAULT '',
  `role`       VARCHAR(20)  NOT NULL DEFAULT '',
  `ip_address` VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  9. Views used by the dashboard and Fund Monitoring
-- -----------------------------------------------------------------------------

-- Each PPA with its office, sector, requested vs approved budget and amount spent
CREATE VIEW `v_ppa_summary` AS
SELECT
  p.id, p.ref, p.fiscal_year, p.title, p.ppa_type, p.status,
  p.office_code, o.name AS office_name,
  p.sector_code, s.name AS sector_name,
  p.fund_code, p.total_cost,
  COALESCE(b.requested_total, p.total_cost) AS requested_total,
  b.approved_total,
  COALESCE((SELECT SUM(e.amount) FROM ppa_expenses e WHERE e.ppa_id = p.id), 0) AS utilized,
  p.progress, p.submitted_at, p.decided_at, p.updated_at
FROM ppas p
JOIN offices o      ON o.code = p.office_code
LEFT JOIN sectors s ON s.code = p.sector_code
LEFT JOIN ppa_budgets b ON b.ppa_id = p.id;

-- Programmed (approved), pending and utilized amounts per fund source and fiscal year.
-- A PPA with no allocation rows is charged entirely to its primary fund.
CREATE VIEW `v_fund_totals` AS
SELECT
  f.code AS fund_code, f.name AS fund_name, c.fiscal_year, c.ceiling,
  COALESCE(SUM(CASE WHEN p.status IN ('approved','ongoing','completed') THEN a.amount END), 0) AS programmed,
  COALESCE(SUM(CASE WHEN p.status IN ('for_review','for_budget','recommended','returned') THEN a.amount END), 0) AS pending,
  c.ceiling - COALESCE(SUM(CASE WHEN p.status IN ('approved','ongoing','completed') THEN a.amount END), 0) AS available
FROM fund_sources f
JOIN fund_source_ceilings c ON c.fund_code = f.code
LEFT JOIN (
    SELECT al.ppa_id, al.fund_code, al.amount FROM ppa_fund_allocations al
    UNION ALL
    SELECT pp.id, pp.fund_code, pp.total_cost FROM ppas pp
    WHERE pp.fund_code IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM ppa_fund_allocations x WHERE x.ppa_id = pp.id)
) a ON a.fund_code = f.code
LEFT JOIN ppas p ON p.id = a.ppa_id AND p.fiscal_year = c.fiscal_year
GROUP BY f.code, f.name, c.fiscal_year, c.ceiling;

-- -----------------------------------------------------------------------------
--  10. Reference data
-- -----------------------------------------------------------------------------

INSERT INTO `sectors` (`code`, `name`, `sort_order`) VALUES
  ('GPS', 'General Public Services', 1),
  ('SS',  'Social Services',         2),
  ('ES',  'Economic Services',       3),
  ('OS',  'Other Services',          4);

INSERT INTO `offices` (`code`, `name`) VALUES
  ('MEO',    'Municipal Engineering Office'),
  ('MHO',    'Municipal Health Office'),
  ('MSWDO',  'Municipal Social Welfare and Development Office'),
  ('MAO',    'Municipal Agriculture Office'),
  ('MDRRMO', 'Municipal Disaster Risk Reduction and Management Office'),
  ('MENRO',  'Municipal Environment and Natural Resources Office'),
  ('MPDO',   'Municipal Planning and Development Office'),
  ('MBO',    'Municipal Budget Office'),
  ('IAS',    'Internal Audit Service');

INSERT INTO `fund_sources` (`code`, `name`, `account_code`, `category`, `description`, `is_active`) VALUES
  ('DF',     '20% Development Fund',   'DF-20',   'General Fund',         '20% of the IRA/NTA share set aside for development projects.', 1),
  ('GF',     'General Fund',           'GF-101',  'General Fund',         'Regular appropriations of the municipal general fund.', 1),
  ('LDRRMF', 'Local DRRM Fund',        'LDRRMF',  'Special Purpose Fund', 'Local disaster risk reduction and management fund.', 1),
  ('SEF',    'Special Education Fund', 'SEF-201', 'Special Purpose Fund', 'Proceeds of the additional real property tax for education.', 1),
  ('TF',     'Trust Fund',             'TF-301',  'Trust Fund',           'Funds held in trust for specific purposes.', 1),
  ('LP',     'Loan Proceeds',          'LP-401',  'External Source',      'Proceeds of loans from government financial institutions.', 0);

-- FY 2027 ceilings (the AIP being prepared). Replace with the actual figures from the budget call.
INSERT INTO `fund_source_ceilings` (`fund_code`, `fiscal_year`, `ceiling`) VALUES
  ('DF', 2027, 35000000.00), ('GF', 2027, 30000000.00), ('LDRRMF', 2027, 12000000.00),
  ('SEF', 2027, 8000000.00), ('TF', 2027, 6000000.00), ('LP', 2027, 0.00);

-- First administrator. Temporary password: Dev@2026 (must be changed at first sign-in).
INSERT INTO `users` (`username`, `full_name`, `email`, `password_hash`, `role`, `office_code`, `position`, `status`, `must_change_password`) VALUES
  ('admin', 'System Administrator', 'admin@indang.gov.ph',
   '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6',
   'Administrator', 'MPDO', 'System Administrator', 'Active', 1);
