-- ============================================================
-- MASJID ERP SOFTWARE - Complete Database Schema
-- Version: 1.0 | PHP 8.x | MySQL 5.7+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `masjid_erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `masjid_erp`;

-- ============================================================
-- ROLES AND PERMISSIONS
-- ============================================================

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `role_name`, `description`) VALUES
(1, 'Admin', 'Full system access'),
(2, 'Accountant', 'Manage accounts, receipts, payments, reports'),
(3, 'Committee Member', 'View reports and audit summaries'),
(4, 'Staff Manager', 'View salary and staff records'),
(5, 'Auditor', 'Read-only access to reports and vouchers');

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module` varchar(100) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_perm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(150),
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `phone` varchar(20),
  `profile_image` varchar(255),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime,
  `last_ip` varchar(45),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user (password: admin123)
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role_id`, `status`) VALUES
('System Admin', 'admin', 'admin@masjid.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active');

-- ============================================================
-- MASJID PROFILE
-- ============================================================

CREATE TABLE `masjid_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `masjid_name` varchar(200) NOT NULL,
  `registration_number` varchar(100),
  `waqf_registration` varchar(100),
  `address` text,
  `city` varchar(100),
  `state` varchar(100),
  `pincode` varchar(10),
  `phone` varchar(20),
  `email` varchar(150),
  `website` varchar(200),
  `logo` varchar(255),
  `committee_period` varchar(100),
  `established_year` year,
  `bank_name` varchar(200),
  `bank_account` varchar(50),
  `bank_ifsc` varchar(20),
  `bank_branch` varchar(200),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `masjid_profile` (`masjid_name`, `address`, `phone`, `email`) VALUES
('Masjid Al-Noor', '123 Main Street, City', '+91 9876543210', 'info@masjid.com');

-- ============================================================
-- FINANCIAL YEARS
-- ============================================================

CREATE TABLE `financial_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_label` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `closed_by` int(11),
  `closed_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `financial_years` (`year_label`, `start_date`, `end_date`, `status`) VALUES
('2024-2025', '2024-04-01', '2025-03-31', 'active'),
('2025-2026', '2025-04-01', '2026-03-31', 'active');

-- ============================================================
-- CHART OF ACCOUNTS
-- ============================================================

CREATE TABLE `account_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) NOT NULL,
  `group_type` enum('Assets','Liabilities','Income','Expense','Capital') NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `account_groups` (`group_name`, `group_type`) VALUES
('Cash & Bank', 'Assets'),
('Fixed Assets', 'Assets'),
('Other Assets', 'Assets'),
('Current Liabilities', 'Liabilities'),
('Long Term Liabilities', 'Liabilities'),
('Donation Income', 'Income'),
('Rental Income', 'Income'),
('Other Income', 'Income'),
('Salary & Wages', 'Expense'),
('Utility Expenses', 'Expense'),
('Maintenance Expenses', 'Expense'),
('Administrative Expenses', 'Expense'),
('Charity & Aid', 'Expense'),
('Capital Fund', 'Capital'),
('Restricted Funds', 'Capital');

CREATE TABLE `chart_of_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL UNIQUE,
  `account_name` varchar(200) NOT NULL,
  `group_id` int(11) NOT NULL,
  `account_type` enum('Assets','Liabilities','Income','Expense','Capital') NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `is_system` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `fk_coa_group` FOREIGN KEY (`group_id`) REFERENCES `account_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `chart_of_accounts` (`account_code`, `account_name`, `group_id`, `account_type`, `is_system`) VALUES
('1001', 'Cash in Hand', 1, 'Assets', 1),
('1002', 'Main Bank Account', 1, 'Assets', 1),
('1003', 'Petty Cash', 1, 'Assets', 0),
('1101', 'Building & Construction', 2, 'Assets', 0),
('1102', 'Land', 2, 'Assets', 0),
('1103', 'Furniture & Fixtures', 2, 'Assets', 0),
('1104', 'Equipment & Machinery', 2, 'Assets', 0),
('2001', 'Accounts Payable', 4, 'Liabilities', 0),
('2002', 'Advance Deposits (Tenants)', 4, 'Liabilities', 0),
('3001', 'Friday Collection', 6, 'Income', 0),
('3002', 'Daily Donation', 6, 'Income', 0),
('3003', 'Ramadan Donation', 6, 'Income', 0),
('3004', 'Zakat Collection', 6, 'Income', 0),
('3005', 'Sadaqah', 6, 'Income', 0),
('3006', 'Fitr/Fidyah', 6, 'Income', 0),
('3007', 'Construction Fund Collection', 6, 'Income', 0),
('3008', 'Madrasa Fee', 6, 'Income', 0),
('3009', 'Special Program Collection', 6, 'Income', 0),
('3101', 'Shop Rent Income', 7, 'Income', 0),
('3102', 'Hall Rent Income', 7, 'Income', 0),
('3103', 'Room Rent Income', 7, 'Income', 0),
('3201', 'Miscellaneous Income', 8, 'Income', 0),
('4001', 'Imam Salary', 9, 'Expense', 0),
('4002', 'Muazzin Salary', 9, 'Expense', 0),
('4003', 'Madrasa Teacher Salary', 9, 'Expense', 0),
('4004', 'Office Staff Salary', 9, 'Expense', 0),
('4005', 'Cleaner Salary', 9, 'Expense', 0),
('4006', 'Security Salary', 9, 'Expense', 0),
('4101', 'Electricity Bill', 10, 'Expense', 0),
('4102', 'Water Bill', 10, 'Expense', 0),
('4103', 'Telephone Bill', 10, 'Expense', 0),
('4201', 'Building Maintenance', 11, 'Expense', 0),
('4202', 'Equipment Maintenance', 11, 'Expense', 0),
('4203', 'Cleaning Supplies', 11, 'Expense', 0),
('4301', 'Stationery', 12, 'Expense', 0),
('4302', 'Food & Refreshment', 12, 'Expense', 0),
('4303', 'Program Expenses', 12, 'Expense', 0),
('4401', 'Charity & Aid', 13, 'Expense', 0),
('4402', 'Orphan Support', 13, 'Expense', 0),
('5001', 'General Fund', 14, 'Capital', 0),
('5002', 'Building Fund', 15, 'Capital', 0),
('5003', 'Zakat Fund', 15, 'Capital', 0),
('5004', 'Madrasa Fund', 15, 'Capital', 0),
('5005', 'Charity Fund', 15, 'Capital', 0);

-- ============================================================
-- INCOME CATEGORIES
-- ============================================================

CREATE TABLE `income_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(200) NOT NULL,
  `account_id` int(11),
  `fund_type` varchar(100),
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `income_categories` (`category_name`, `account_id`) VALUES
('Friday Collection', 10),
('Daily Donation', 11),
('Ramadan Donation', 12),
('Zakat Collection', 13),
('Sadaqah', 14),
('Fitr/Fidyah', 15),
('Construction Fund', 16),
('Madrasa Fee', 17),
('Special Program', 18),
('Shop Rent', 19),
('Hall Rent', 20),
('Room Rent', 21),
('Miscellaneous Income', 22);

-- ============================================================
-- EXPENSE CATEGORIES
-- ============================================================

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(200) NOT NULL,
  `account_id` int(11),
  `restricted_fund` varchar(100),
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `expense_categories` (`category_name`, `account_id`) VALUES
('Imam Salary', 23),
('Muazzin Salary', 24),
('Madrasa Teacher Salary', 25),
('Office Staff Salary', 26),
('Cleaner Salary', 27),
('Security Salary', 28),
('Electricity Bill', 29),
('Water Bill', 30),
('Telephone Bill', 31),
('Building Maintenance', 32),
('Equipment Maintenance', 33),
('Cleaning Supplies', 34),
('Stationery', 35),
('Food & Refreshment', 36),
('Program Expenses', 37),
('Charity & Aid', 38),
('Orphan Support', 39);

-- ============================================================
-- DONORS
-- ============================================================

CREATE TABLE `donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_code` varchar(20) UNIQUE,
  `donor_name` varchar(200) NOT NULL,
  `phone` varchar(20),
  `whatsapp` varchar(20),
  `email` varchar(150),
  `address` text,
  `city` varchar(100),
  `donation_purpose` varchar(200),
  `is_recurring` tinyint(1) DEFAULT 0,
  `notes` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- RECEIPTS (INCOME)
-- ============================================================

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(30) NOT NULL UNIQUE,
  `date` date NOT NULL,
  `donor_id` int(11),
  `donor_name` varchar(200),
  `donor_phone` varchar(20),
  `category_id` int(11),
  `account_id` int(11) NOT NULL,
  `payment_mode` enum('cash','bank','cheque','online','upi') NOT NULL DEFAULT 'cash',
  `cheque_no` varchar(50),
  `bank_ref` varchar(100),
  `amount` decimal(15,2) NOT NULL,
  `financial_year_id` int(11),
  `remarks` text,
  `is_printed` tinyint(1) DEFAULT 0,
  `is_audited` tinyint(1) DEFAULT 0,
  `created_by` int(11),
  `updated_by` int(11),
  `deleted_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `donor_id` (`donor_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PAYMENTS (EXPENSES)
-- ============================================================

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(30) NOT NULL UNIQUE,
  `date` date NOT NULL,
  `payee_name` varchar(200) NOT NULL,
  `category_id` int(11),
  `account_id` int(11) NOT NULL,
  `payment_mode` enum('cash','bank','cheque','online','upi') NOT NULL DEFAULT 'cash',
  `cheque_no` varchar(50),
  `bank_ref` varchar(100),
  `amount` decimal(15,2) NOT NULL,
  `financial_year_id` int(11),
  `remarks` text,
  `approved_by` int(11),
  `is_printed` tinyint(1) DEFAULT 0,
  `is_audited` tinyint(1) DEFAULT 0,
  `created_by` int(11),
  `updated_by` int(11),
  `deleted_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- JOURNAL ENTRIES
-- ============================================================

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_no` varchar(30) NOT NULL UNIQUE,
  `date` date NOT NULL,
  `narration` text NOT NULL,
  `financial_year_id` int(11),
  `is_audited` tinyint(1) DEFAULT 0,
  `created_by` int(11),
  `deleted_at` datetime,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `journal_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `narration` text,
  PRIMARY KEY (`id`),
  KEY `journal_id` (`journal_id`),
  CONSTRAINT `fk_jd_journal` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- WAQF PROPERTIES
-- ============================================================

CREATE TABLE `waqf_properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_code` varchar(20) UNIQUE,
  `property_name` varchar(200) NOT NULL,
  `property_type` enum('land','shop','building','hall','classroom','room','other') NOT NULL,
  `survey_number` varchar(100),
  `location` text,
  `city` varchar(100),
  `ownership_details` text,
  `estimated_value` decimal(15,2),
  `monthly_income` decimal(15,2) DEFAULT 0.00,
  `status` enum('occupied','vacant','under_maintenance','inactive') DEFAULT 'vacant',
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TENANTS
-- ============================================================

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_code` varchar(20) UNIQUE,
  `tenant_name` varchar(200) NOT NULL,
  `phone` varchar(20),
  `whatsapp` varchar(20),
  `email` varchar(150),
  `address` text,
  `property_id` int(11) NOT NULL,
  `shop_number` varchar(50),
  `rent_amount` decimal(15,2) NOT NULL,
  `advance_deposit` decimal(15,2) DEFAULT 0.00,
  `due_day` int(2) DEFAULT 1,
  `agreement_start` date,
  `agreement_end` date,
  `status` enum('active','terminated','expired') DEFAULT 'active',
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  CONSTRAINT `fk_tenant_property` FOREIGN KEY (`property_id`) REFERENCES `waqf_properties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- RENT COLLECTIONS
-- ============================================================

CREATE TABLE `rent_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(30) UNIQUE,
  `tenant_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `collection_month` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `due_amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `payment_date` date,
  `payment_mode` enum('cash','bank','cheque','online','upi') DEFAULT 'cash',
  `bank_ref` varchar(100),
  `account_id` int(11),
  `status` enum('pending','partial','paid','overdue') DEFAULT 'pending',
  `remarks` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_month` (`tenant_id`, `collection_month`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_rent_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- STAFF
-- ============================================================

CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_code` varchar(20) UNIQUE,
  `staff_name` varchar(200) NOT NULL,
  `designation` enum('imam','khatib','muazzin','teacher','office_staff','cleaner','security','other') NOT NULL,
  `phone` varchar(20),
  `whatsapp` varchar(20),
  `email` varchar(150),
  `address` text,
  `joining_date` date,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allowance` decimal(10,2) DEFAULT 0.00,
  `bank_name` varchar(200),
  `bank_account` varchar(50),
  `bank_ifsc` varchar(20),
  `photo` varchar(255),
  `status` enum('active','inactive','resigned','terminated') DEFAULT 'active',
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SALARY SHEET
-- ============================================================

CREATE TABLE `salary_sheet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `salary_month` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `basic_salary` decimal(10,2) NOT NULL,
  `allowance` decimal(10,2) DEFAULT 0.00,
  `deduction` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL,
  `payment_date` date,
  `payment_mode` enum('cash','bank','cheque','online','upi') DEFAULT 'cash',
  `bank_ref` varchar(100),
  `account_id` int(11),
  `voucher_no` varchar(30),
  `status` enum('pending','paid') DEFAULT 'pending',
  `remarks` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_month` (`staff_id`, `salary_month`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `fk_salary_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ASSETS REGISTER
-- ============================================================

CREATE TABLE `assets_register` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(20) UNIQUE,
  `asset_name` varchar(200) NOT NULL,
  `category` varchar(100),
  `purchase_date` date,
  `purchase_value` decimal(15,2),
  `supplier` varchar(200),
  `condition_status` enum('excellent','good','fair','poor','disposed') DEFAULT 'good',
  `location` varchar(200),
  `depreciation_rate` decimal(5,2) DEFAULT 0.00,
  `current_value` decimal(15,2),
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- FUNDS
-- ============================================================

CREATE TABLE `funds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fund_name` varchar(200) NOT NULL,
  `fund_type` enum('general','restricted','designated') DEFAULT 'general',
  `account_id` int(11),
  `description` text,
  `balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `funds` (`fund_name`, `fund_type`, `account_id`) VALUES
('General Fund', 'general', 41),
('Building Fund', 'restricted', 42),
('Zakat Fund', 'restricted', 43),
('Madrasa Fund', 'restricted', 44),
('Charity Fund', 'designated', 45);

-- ============================================================
-- SETTINGS
-- ============================================================

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `setting_group` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('receipt_prefix', 'RCP', 'accounting'),
('voucher_prefix', 'VCH', 'accounting'),
('journal_prefix', 'JNL', 'accounting'),
('salary_prefix', 'SAL', 'salary'),
('rent_prefix', 'RNT', 'waqf'),
('currency_symbol', '₹', 'general'),
('currency_code', 'INR', 'general'),
('date_format', 'd/m/Y', 'general'),
('financial_year_start', '04', 'accounting'),
('enable_whatsapp', '0', 'notifications'),
('enable_sms', '0', 'notifications'),
('session_timeout', '60', 'security');

-- ============================================================
-- AUDIT LOGS
-- ============================================================

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(50) NOT NULL,
  `module` varchar(100) NOT NULL,
  `record_id` int(11),
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45),
  `user_agent` varchar(500),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
