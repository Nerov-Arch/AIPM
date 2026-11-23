-- =============================================================================
--  AIP System: SAMPLE DATA for testing (same data as the development version)
--  Import AFTER aip_db.sql. Do NOT import this into the database used for real work.
--
--  Test accounts below all use the temporary password Dev@2026 and are NOT forced
--  to change it, so testers can switch accounts freely. Delete or deactivate them
--  before real use. Their @indang.gov.ph emails are fictional.
-- =============================================================================
USE `aip_db`;
SET NAMES utf8mb4;

-- Make the administrator a sample account too (name, email, no forced password change)
UPDATE `users` SET `full_name` = 'Maria Villanueva', `email` = 'm.villanueva@indang.gov.ph', `must_change_password` = 0
WHERE `username` = 'admin';

-- Test accounts
INSERT INTO `users` (`username`, `full_name`, `email`, `password_hash`, `role`, `office_code`, `position`, `status`, `must_change_password`) VALUES
  ('jbautista', 'Jose Bautista', 'j.bautista@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Reviewer', 'MPDO', 'Planning Officer II', 'Active', 0),
  ('alim', 'Ana Lim', 'a.lim@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Reviewer', 'MPDO', 'Municipal Planning and Development Coordinator', 'Active', 0),
  ('rdelacruz', 'Rafael Dela Cruz', 'r.delacruz@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Encoder', 'MEO', 'Engineer II', 'Active', 0),
  ('lgarcia', 'Liza Garcia', 'l.garcia@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Encoder', 'MSWDO', 'Social Welfare Officer I', 'Active', 0),
  ('pmendoza', 'Paolo Mendoza', 'p.mendoza@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Encoder', 'MAO', 'Agricultural Technologist', 'Inactive', 0),
  ('cramos', 'Carmen Ramos', 'c.ramos@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Encoder', 'MENRO', 'Environmental Management Specialist', 'Active', 0),
  ('tnavarro', 'Teresa Navarro', 't.navarro@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Finance', 'MBO', 'Municipal Budget Officer', 'Active', 0),
  ('raquino', 'Ramon Aquino', 'r.aquino@indang.gov.ph', '$2y$10$IxSj6ScM5z2Lx3rErWzm3.PpU4oKfoig4RZ5eMfXuYcOsDdc7bCy6', 'Auditor', 'IAS', 'Internal Auditor', 'Active', 0);

-- Sample PPAs (FY 2027)
INSERT INTO `ppas` (`ref`, `fiscal_year`, `title`, `ppa_type`, `program`, `sector_code`, `description`, `expected_output`, `office_code`, `start_date`, `end_date`, `ps`, `mooe`, `co`, `fund_code`, `cc_adaptation`, `cc_mitigation`, `cc_typology`, `status`, `progress`, `remarks`, `created_by`, `submitted_at`, `forwarded_by`, `forwarded_at`, `decided_by`, `decided_at`, `created_at`, `updated_at`) VALUES
  ('PPA-2027-001', 2027, 'Concreting of Barangay Farm-to-Market Road (1.2 km)', 'Project', 'Rural Infrastructure Program', 'ES', 'Sample description for Concreting of Barangay Farm-to-Market Road (1.2 km).', 'Completed facility turned over to the implementing office.', 'MEO', '2027-02-01', '2027-07-31', 0.00, 0.00, 14500000.00, 'DF', 0.00, 0.00, NULL, 'for_review', 0, NULL, (SELECT id FROM users WHERE username = 'rdelacruz'), '2026-10-21 08:00:00', NULL, NULL, NULL, NULL, '2026-10-21 08:00:00', '2026-10-21 08:00:00'),
  ('PPA-2027-002', 2027, 'Rehabilitation of Municipal Health Center', 'Project', 'Health Services Enhancement', 'SS', 'Sample description for Rehabilitation of Municipal Health Center.', 'Completed facility turned over to the implementing office.', 'MHO', '2027-03-01', '2027-09-30', 0.00, 0.00, 6800000.00, 'DF', 0.00, 0.00, NULL, 'for_budget', 0, NULL, NULL, '2026-10-20 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-20 09:00:00', NULL, NULL, '2026-10-20 08:00:00', '2026-10-20 08:00:00'),
  ('PPA-2027-003', 2027, 'Community-Based Disaster Preparedness Training', 'Activity', 'Disaster Risk Reduction Program', 'SS', 'Sample description for Community-Based Disaster Preparedness Training.', 'Activity conducted for target beneficiaries.', 'MDRRMO', '2027-01-15', '2027-06-30', 0.00, 950000.00, 0.00, 'LDRRMF', 0.00, 0.00, NULL, 'for_review', 0, NULL, NULL, '2026-10-19 08:00:00', NULL, NULL, NULL, NULL, '2026-10-19 08:00:00', '2026-10-19 08:00:00'),
  ('PPA-2027-004', 2027, 'Supplementary Feeding Program for Day Care Children', 'Program', 'Social Protection Program', 'SS', 'Sample description for Supplementary Feeding Program for Day Care Children.', 'Activity conducted for target beneficiaries.', 'MSWDO', '2027-01-02', '2027-12-15', 0.00, 1800000.00, 0.00, 'GF', 0.00, 0.00, NULL, 'for_review', 0, NULL, (SELECT id FROM users WHERE username = 'lgarcia'), '2026-10-14 08:00:00', NULL, NULL, NULL, NULL, '2026-10-14 08:00:00', '2026-10-14 08:00:00'),
  ('PPA-2027-005', 2027, 'Construction of Materials Recovery Facility', 'Project', 'Solid Waste Management Program', 'OS', 'Sample description for Construction of Materials Recovery Facility.', 'Completed facility turned over to the implementing office.', 'MENRO', '2027-04-01', '2027-10-31', 0.00, 0.00, 4200000.00, 'DF', 0.00, 0.00, NULL, 'returned', 0, 'Please indicate the exact site and number of barangays served, and break down the cost into MOOE and Capital Outlay.', (SELECT id FROM users WHERE username = 'cramos'), '2026-10-16 08:00:00', NULL, NULL, NULL, NULL, '2026-10-16 08:00:00', '2026-10-16 08:00:00'),
  ('PPA-2027-006', 2027, 'Installation of Solar Street Lights along Poblacion', 'Project', 'Rural Infrastructure Program', 'ES', 'Sample description for Installation of Solar Street Lights along Poblacion.', 'Completed facility turned over to the implementing office.', 'MEO', '2027-02-15', '2027-05-31', 0.00, 0.00, 3500000.00, 'DF', 0.00, 0.00, NULL, 'recommended', 0, NULL, (SELECT id FROM users WHERE username = 'rdelacruz'), '2026-10-15 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-15 09:00:00', NULL, NULL, '2026-10-15 08:00:00', '2026-10-15 08:00:00'),
  ('PPA-2027-007', 2027, 'Distribution of Certified Rice and Vegetable Seeds', 'Activity', 'Agricultural Productivity Program', 'ES', 'Sample description for Distribution of Certified Rice and Vegetable Seeds.', 'Activity conducted for target beneficiaries.', 'MAO', '2027-05-01', '2027-06-30', 0.00, 1250000.00, 0.00, 'GF', 0.00, 0.00, NULL, 'recommended', 0, NULL, (SELECT id FROM users WHERE username = 'pmendoza'), '2026-10-14 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-14 09:00:00', NULL, NULL, '2026-10-14 08:00:00', '2026-10-14 08:00:00'),
  ('PPA-2027-008', 2027, 'Procurement of Rescue Vehicle and Equipment', 'Project', 'Disaster Risk Reduction Program', 'SS', 'Sample description for Procurement of Rescue Vehicle and Equipment.', 'Completed facility turned over to the implementing office.', 'MDRRMO', '2027-03-01', '2027-06-30', 0.00, 0.00, 5600000.00, 'LDRRMF', 0.00, 0.00, NULL, 'recommended', 0, NULL, NULL, '2026-10-13 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-13 09:00:00', NULL, NULL, '2026-10-13 08:00:00', '2026-10-13 08:00:00'),
  ('PPA-2027-009', 2027, 'Repair of Public Elementary School Classrooms', 'Project', 'Basic Education Support', 'ES', 'Sample description for Repair of Public Elementary School Classrooms.', 'Completed facility turned over to the implementing office.', 'MEO', '2027-04-15', '2027-07-31', 0.00, 0.00, 3900000.00, 'SEF', 0.00, 0.00, NULL, 'disapproved', 0, NULL, (SELECT id FROM users WHERE username = 'rdelacruz'), '2026-10-10 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-10 09:00:00', (SELECT id FROM users WHERE username = 'alim'), '2026-10-10 14:00:00', '2026-10-10 08:00:00', '2026-10-10 08:00:00'),
  ('PPA-2027-010', 2027, 'Barangay Health Workers Capability Building', 'Program', 'Health Services Enhancement', 'SS', 'Sample description for Barangay Health Workers Capability Building.', 'Activity conducted for target beneficiaries.', 'MHO', '2027-02-01', '2027-11-30', 0.00, 750000.00, 0.00, 'GF', 0.00, 0.00, NULL, 'approved', 0, NULL, NULL, '2026-10-08 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-08 09:00:00', (SELECT id FROM users WHERE username = 'alim'), '2026-10-08 14:00:00', '2026-10-08 08:00:00', '2026-10-08 08:00:00'),
  ('PPA-2027-011', 2027, 'Construction of Drainage System, Barangay Center', 'Project', 'Rural Infrastructure Program', 'ES', 'Sample description for Construction of Drainage System, Barangay Center.', 'Completed facility turned over to the implementing office.', 'MEO', '2027-01-15', '2027-08-31', 0.00, 0.00, 8200000.00, 'DF', 0.00, 0.00, NULL, 'approved', 0, NULL, (SELECT id FROM users WHERE username = 'rdelacruz'), '2026-10-07 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-07 09:00:00', (SELECT id FROM users WHERE username = 'alim'), '2026-10-07 14:00:00', '2026-10-07 08:00:00', '2026-10-07 08:00:00'),
  ('PPA-2027-012', 2027, 'Tree Planting and Watershed Rehabilitation', 'Activity', 'Environmental Protection Program', 'OS', 'Sample description for Tree Planting and Watershed Rehabilitation.', 'Activity conducted for target beneficiaries.', 'MENRO', '2027-06-01', '2027-09-30', 0.00, 600000.00, 0.00, 'TF', 300000.00, 0.00, 'A612-01', 'ongoing', 45, NULL, (SELECT id FROM users WHERE username = 'cramos'), '2026-10-05 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-05 09:00:00', (SELECT id FROM users WHERE username = 'alim'), '2026-10-05 14:00:00', '2026-10-05 08:00:00', '2026-10-05 08:00:00'),
  ('PPA-2027-013', 2027, 'Assistance to Individuals in Crisis Situations', 'Program', 'Social Protection Program', 'SS', 'Sample description for Assistance to Individuals in Crisis Situations.', 'Activity conducted for target beneficiaries.', 'MSWDO', '2027-01-02', '2027-12-31', 0.00, 2500000.00, 0.00, 'GF', 0.00, 0.00, NULL, 'ongoing', 60, NULL, (SELECT id FROM users WHERE username = 'lgarcia'), '2026-10-03 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-10-03 09:00:00', (SELECT id FROM users WHERE username = 'alim'), '2026-10-03 14:00:00', '2026-10-03 08:00:00', '2026-10-03 08:00:00'),
  ('PPA-2027-014', 2027, 'Updating of the Comprehensive Land Use Plan', 'Activity', 'Development Planning Support', 'GPS', 'Sample description for Updating of the Comprehensive Land Use Plan.', 'Activity conducted for target beneficiaries.', 'MPDO', '2027-01-02', '2027-06-30', 0.00, 1200000.00, 0.00, 'GF', 0.00, 0.00, NULL, 'completed', 100, NULL, NULL, '2026-09-30 08:00:00', (SELECT id FROM users WHERE username = 'jbautista'), '2026-09-30 09:00:00', (SELECT id FROM users WHERE username = 'alim'), '2026-09-30 14:00:00', '2026-09-30 08:00:00', '2026-09-30 08:00:00');

-- Requested vs approved budgets (two PPAs approved below the request)
INSERT INTO `ppa_budgets` (`ppa_id`, `requested_ps`, `requested_mooe`, `requested_co`, `approved_ps`, `approved_mooe`, `approved_co`, `decided_by`, `decided_at`, `remarks`) VALUES
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-002'), 0.00, 0.00, 6800000.00, NULL, NULL, NULL, NULL, NULL, NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-006'), 0.00, 0.00, 3800000.00, 0.00, 0.00, 3500000.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-15 10:00:00', 'Approved at a lower amount based on the updated cost estimate.'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-007'), 0.00, 1250000.00, 0.00, 0.00, 1250000.00, 0.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-14 10:00:00', NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-008'), 0.00, 0.00, 5600000.00, 0.00, 0.00, 5600000.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-13 10:00:00', NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-010'), 0.00, 750000.00, 0.00, 0.00, 750000.00, 0.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-08 10:00:00', NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-011'), 0.00, 0.00, 8500000.00, 0.00, 0.00, 8200000.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-07 10:00:00', 'Approved at a lower amount based on the updated cost estimate.'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-012'), 0.00, 600000.00, 0.00, 0.00, 600000.00, 0.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-05 10:00:00', NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), 0.00, 2500000.00, 0.00, 0.00, 2500000.00, 0.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-10-03 10:00:00', NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-014'), 0.00, 1200000.00, 0.00, 0.00, 1200000.00, 0.00, (SELECT id FROM users WHERE username = 'tnavarro'), '2026-09-30 10:00:00', NULL);

-- Split allocation that does NOT add up (demonstrates the funding checks)
UPDATE `ppas` SET `allocation_basis` = 'Sample split for testing' WHERE `ref` = 'PPA-2027-013';
INSERT INTO `ppa_fund_allocations` (`ppa_id`, `fund_code`, `amount`) VALUES
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), 'GF', 2000000.00),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), 'TF', 400000.00);

-- Status history
INSERT INTO `ppa_history` (`ppa_id`, `action`, `status`, `remarks`, `actor_name`, `created_at`) VALUES
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-001'), 'Sample record loaded', 'for_review', NULL, 'System (sample data)', '2026-10-21 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-002'), 'Sample record loaded', 'for_budget', NULL, 'System (sample data)', '2026-10-20 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-003'), 'Sample record loaded', 'for_review', NULL, 'System (sample data)', '2026-10-19 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-004'), 'Sample record loaded', 'for_review', NULL, 'System (sample data)', '2026-10-14 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-005'), 'Sample record loaded', 'returned', NULL, 'System (sample data)', '2026-10-16 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-005'), 'Returned for revision', 'returned', 'Please indicate the exact site and number of barangays served, and break down the cost into MOOE and Capital Outlay.', 'Jose Bautista (jbautista)', '2026-10-16 15:30:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-006'), 'Sample record loaded', 'recommended', NULL, 'System (sample data)', '2026-10-15 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-007'), 'Sample record loaded', 'recommended', NULL, 'System (sample data)', '2026-10-14 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-008'), 'Sample record loaded', 'recommended', NULL, 'System (sample data)', '2026-10-13 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-009'), 'Sample record loaded', 'disapproved', NULL, 'System (sample data)', '2026-10-10 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-010'), 'Sample record loaded', 'approved', NULL, 'System (sample data)', '2026-10-08 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-011'), 'Sample record loaded', 'approved', NULL, 'System (sample data)', '2026-10-07 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-012'), 'Sample record loaded', 'ongoing', NULL, 'System (sample data)', '2026-10-05 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), 'Sample record loaded', 'ongoing', NULL, 'System (sample data)', '2026-10-03 08:00:00'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-014'), 'Sample record loaded', 'completed', NULL, 'System (sample data)', '2026-09-30 08:00:00');

-- Approval records and the PPAs they cover
INSERT INTO `approval_records` (`record_no`, `record_type`, `record_date`, `subject`, `created_by`) VALUES
  ('SB Res. No. 2026-075', 'SB Resolution', '2026-08-20', 'Approving the CLUP updating activity for inclusion in the FY 2027 AIP', (SELECT id FROM users WHERE username = 'jbautista')),
  ('SB Res. No. 2026-098', 'SB Resolution', '2026-09-14', 'Approving social protection and environmental PPAs for FY 2027', (SELECT id FROM users WHERE username = 'jbautista')),
  ('SB Res. No. 2026-114', 'SB Resolution', '2026-10-06', 'Approving the barangay health workers capability building program', (SELECT id FROM users WHERE username = 'jbautista')),
  ('LDC Res. No. 2026-012', 'LDC Resolution', '2026-10-10', 'Endorsing priority infrastructure PPAs to the Sangguniang Bayan', (SELECT id FROM users WHERE username = 'jbautista'));
INSERT INTO `ppa_approval_records` (`ppa_id`, `approval_record_id`, `linked_by`) VALUES
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-014'), (SELECT id FROM approval_records WHERE record_no = 'SB Res. No. 2026-075'), (SELECT id FROM users WHERE username = 'jbautista')),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-012'), (SELECT id FROM approval_records WHERE record_no = 'SB Res. No. 2026-098'), (SELECT id FROM users WHERE username = 'jbautista')),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), (SELECT id FROM approval_records WHERE record_no = 'SB Res. No. 2026-098'), (SELECT id FROM users WHERE username = 'jbautista')),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-010'), (SELECT id FROM approval_records WHERE record_no = 'SB Res. No. 2026-114'), (SELECT id FROM users WHERE username = 'jbautista'));

-- Expenses of PPAs under implementation (sample records have no receipt image)
INSERT INTO `ppa_expenses` (`ppa_id`, `expense_date`, `description`, `amount`, `receipt_no`, `recorded_by`, `recorded_at`, `audit_status`, `audited_by`, `audited_at`, `audit_note`) VALUES
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-012'), '2026-10-02', 'Seedlings and planting materials (2,000 pcs)', 150000.00, 'OR-55120', (SELECT id FROM users WHERE username = 'cramos'), '2026-10-02 16:00:00', 'verified', (SELECT id FROM users WHERE username = 'raquino'), '2026-10-06 09:00:00', 'Matches the delivery receipt.'),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-012'), '2026-10-05', 'Meals and transport of volunteers, 3 planting days', 120000.00, 'OR-55187', (SELECT id FROM users WHERE username = 'cramos'), '2026-10-05 16:00:00', 'unverified', NULL, NULL, NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), '2026-09-20', 'Financial assistance released, batch 1 (200 beneficiaries)', 1000000.00, 'DV-2026-0918', (SELECT id FROM users WHERE username = 'lgarcia'), '2026-09-20 16:00:00', 'unverified', NULL, NULL, NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-013'), '2026-10-01', 'Financial assistance released, batch 2 (100 beneficiaries)', 500000.00, 'DV-2026-1004', (SELECT id FROM users WHERE username = 'lgarcia'), '2026-10-01 16:00:00', 'unverified', NULL, NULL, NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-014'), '2026-08-28', 'Consultancy services, CLUP technical working group', 700000.00, 'DV-2026-0811', NULL, '2026-08-28 16:00:00', 'unverified', NULL, NULL, NULL),
  ((SELECT id FROM ppas WHERE ref = 'PPA-2027-014'), '2026-09-25', 'Printing and public hearing expenses', 480000.00, 'OR-54410', NULL, '2026-09-25 16:00:00', 'unverified', NULL, NULL, NULL);
