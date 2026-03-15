-- ============================================================
-- Migration 001: New Features
-- SMS, QR Pay, Malayalam, Committee Approval Workflow
-- Run once on existing database
-- ============================================================

-- 1. Add approval workflow columns to payments
ALTER TABLE `payments`
    ADD COLUMN `approval_status` ENUM('draft','pending_approval','approved','rejected') NOT NULL DEFAULT 'approved' AFTER `approved_by`,
    ADD COLUMN `approval_comment` TEXT NULL AFTER `approval_status`,
    ADD COLUMN `approval_date` DATETIME NULL AFTER `approval_comment`,
    ADD COLUMN `approval_by` INT(11) NULL AFTER `approval_date`;

-- 2. Add language preference to users
ALTER TABLE `users`
    ADD COLUMN `language` VARCHAR(5) NOT NULL DEFAULT 'en' AFTER `email`;

-- 3. Add UPI fields to masjid_profile
ALTER TABLE `masjid_profile`
    ADD COLUMN `upi_id` VARCHAR(100) NULL AFTER `bank_account`,
    ADD COLUMN `upi_name` VARCHAR(200) NULL AFTER `upi_id`;

-- 4. Payment approval notifications table
CREATE TABLE IF NOT EXISTS `payment_approvals` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `payment_id` INT(11) NOT NULL,
    `requested_by` INT(11) NOT NULL,
    `reviewed_by` INT(11) NULL,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `comment` TEXT NULL,
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_payment_id` (`payment_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. SMS log table
CREATE TABLE IF NOT EXISTS `sms_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `mobile` VARCHAR(15) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
    `provider` VARCHAR(50) DEFAULT 'fast2sms',
    `response` TEXT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` INT(11) NULL,
    `created_by` INT(11) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mobile` (`mobile`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. New settings for SMS, QR, approval
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('sms_provider',            'fast2sms'),
    ('sms_api_key',             ''),
    ('sms_sender_id',           'MASJID'),
    ('sms_enabled',             '0'),
    ('sms_on_receipt',          '1'),
    ('sms_on_salary',           '1'),
    ('sms_on_rent_due',         '1'),
    ('sms_on_approval',         '1'),
    ('approval_enabled',        '0'),
    ('approval_threshold',      '5000'),
    ('approval_roles',          '3'),
    ('upi_id',                  ''),
    ('upi_name',                ''),
    ('qr_donation_enabled',     '1'),
    ('qr_donation_message',     'Scan to donate to our Masjid. Jazakallah Khair!'),
    ('default_language',        'en');
