-- =============================================
-- User Identity & Account Status Enhancements
-- Module 4: Complete User Identity Management
-- =============================================

-- =============================================
-- PART 1: Enhance users table
-- =============================================

-- Add multi-identifier support
ALTER TABLE `users` 
    ADD COLUMN `phone` VARCHAR(20) NULL UNIQUE COMMENT 'Phone number (unique)',
    ADD COLUMN `username` VARCHAR(50) NULL UNIQUE COMMENT 'Username (unique)',
    ADD COLUMN `phone_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Phone verification status',
    ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Email verification status',
    ADD COLUMN `primary_identifier` ENUM('email', 'phone', 'username') NOT NULL DEFAULT 'email' COMMENT 'Primary login identifier';

-- Add account status management
ALTER TABLE `users`
    ADD COLUMN `account_status` ENUM('active', 'locked', 'suspended', 'pending_verification', 'deactivated') NOT NULL DEFAULT 'pending_verification' COMMENT 'Account status',
    ADD COLUMN `locked_at` DATETIME NULL COMMENT 'Account lock timestamp',
    ADD COLUMN `locked_reason` VARCHAR(255) NULL COMMENT 'Reason for account lock',
    ADD COLUMN `suspended_at` DATETIME NULL COMMENT 'Suspension timestamp',
    ADD COLUMN `suspended_by` VARCHAR(36) NULL COMMENT 'User ID who suspended the account',
    ADD COLUMN `suspended_reason` VARCHAR(255) NULL COMMENT 'Reason for suspension',
    ADD COLUMN `failed_login_attempts` INT NOT NULL DEFAULT 0 COMMENT 'Count of failed login attempts',
    ADD COLUMN `last_failed_login` DATETIME NULL COMMENT 'Timestamp of last failed login';

-- Add indexes for performance
ALTER TABLE `users`
    ADD INDEX `idx_phone` (`phone`),
    ADD INDEX `idx_username` (`username`),
    ADD INDEX `idx_account_status` (`account_status`),
    ADD INDEX `idx_email_verified` (`email_verified`),
    ADD INDEX `idx_phone_verified` (`phone_verified`);

-- =============================================
-- PART 2: User Identifiers Table (Future-proof)
-- =============================================

CREATE TABLE IF NOT EXISTS `user_identifiers` (
    `identifier_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `user_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to users',
    `identifier_type` ENUM('email', 'phone', 'username', 'oauth_google', 'oauth_github') NOT NULL COMMENT 'Type of identifier',
    `identifier_value` VARCHAR(255) NOT NULL COMMENT 'Identifier value (email, phone, etc)',
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Primary identifier for login',
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Verification status',
    `verified_at` DATETIME NULL COMMENT 'Verification timestamp',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_identifier_value` (`identifier_value`, `identifier_type`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_identifier_type` (`identifier_type`),
    INDEX `idx_is_primary` (`is_primary`),
    INDEX `idx_is_verified` (`is_verified`),
        CONSTRAINT `fk_ui_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User identifiers for multi-identifier support';

-- =============================================
-- PART 3: Account Status History Table
-- =============================================

CREATE TABLE IF NOT EXISTS `account_status_history` (
    `history_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `user_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to users',
    `old_status` ENUM('active', 'locked', 'suspended', 'pending_verification', 'deactivated') NULL COMMENT 'Previous status',
    `new_status` ENUM('active', 'locked', 'suspended', 'pending_verification', 'deactivated') NOT NULL COMMENT 'New status',
    `changed_by` VARCHAR(36) NULL COMMENT 'User ID who changed status (NULL = system)',
    `reason` VARCHAR(255) NULL COMMENT 'Reason for status change',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address of action',
    `user_agent` VARCHAR(255) NULL COMMENT 'User agent of action',
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_new_status` (`new_status`),
    INDEX `idx_changed_at` (`changed_at`),
        CONSTRAINT `fk_ash_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Account status change history for audit trail';

-- =============================================
-- PART 4: Email Verification Tokens Table
-- =============================================

CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
    `token_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `user_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to users',
    `token` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Verification token (hashed)',
    `email` VARCHAR(255) NOT NULL COMMENT 'Email to verify',
    `expires_at` DATETIME NOT NULL COMMENT 'Token expiration',
    `used_at` DATETIME NULL COMMENT 'Token usage timestamp (NULL = not used)',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address that requested token',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_user_email` (`user_id`, `email`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_used_at` (`used_at`),
        CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email verification tokens for email confirmation workflow';

-- =============================================
-- PART 5: Phone Verification Enhancement
-- Note: Uses existing OTP system, just tracks verified status
-- =============================================

-- No new table needed - uses existing `otp_verifications` table
-- Phone verification will mark phone_verified=1 in users table after OTP success

-- =============================================
-- PART 6: Migration Notes
-- =============================================

-- Existing users will have:
-- - account_status = 'pending_verification' (requires verification)
-- - To activate existing users, run:
--   UPDATE users SET account_status = 'active', email_verified = 1 WHERE created_at < NOW();
