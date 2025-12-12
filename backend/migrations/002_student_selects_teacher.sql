-- =====================================================
-- Staten Academy - Student Selects Teacher Model Migration
-- Migration: 002_student_selects_teacher.sql
-- Purpose: Transform from teacher-assignment to student-selects-teacher model
-- =====================================================

-- Select the database first
USE staten_academy;

-- =====================================================
-- TEACHER_CATEGORIES TABLE
-- Links teachers to categories (young_learners, adults, coding)
-- =====================================================
CREATE TABLE IF NOT EXISTS teacher_categories (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    category ENUM('young_learners', 'adults', 'coding') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_category (teacher_id, category),
    INDEX idx_teacher (teacher_id),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- STUDENT_WALLET TABLE
-- Wallet balance tracking (PHP side, syncs with TypeScript)
-- =====================================================
CREATE TABLE IF NOT EXISTS student_wallet (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL UNIQUE,
    balance DECIMAL(10,2) DEFAULT 0.00,
    trial_credits INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- WALLET_TRANSACTIONS TABLE
-- Transaction history for wallet operations
-- =====================================================
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    type ENUM('purchase', 'deduction', 'refund', 'trial', 'adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    reference_id VARCHAR(255) DEFAULT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_type (type),
    INDEX idx_stripe_payment (stripe_payment_id),
    INDEX idx_reference (reference_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TRIAL_LESSONS TABLE
-- Track trial lesson usage (one per student)
-- =====================================================
CREATE TABLE IF NOT EXISTS trial_lessons (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    teacher_id INT(6) UNSIGNED NOT NULL,
    lesson_id INT(6) UNSIGNED DEFAULT NULL,
    stripe_payment_id VARCHAR(255) NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_trial (student_id),
    INDEX idx_student (student_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_lesson (lesson_id),
    INDEX idx_stripe_payment (stripe_payment_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TEACHER_AVAILABILITY_SLOTS TABLE
-- Available time slots per teacher (more detailed than teacher_availability)
-- =====================================================
CREATE TABLE IF NOT EXISTS teacher_availability_slots (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    is_recurring BOOLEAN DEFAULT TRUE,
    specific_date DATE DEFAULT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_day_time (day_of_week, start_time),
    INDEX idx_specific_date (specific_date),
    INDEX idx_available (is_available),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CATEGORY_PLANS TABLE
-- Plans per category (migrate from subscription_plans)
-- =====================================================
CREATE TABLE IF NOT EXISTS category_plans (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('young_learners', 'adults', 'coding') NOT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stripe_price_id VARCHAR(255) DEFAULT NULL,
    stripe_product_id VARCHAR(255) DEFAULT NULL,
    features JSON DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_stripe_price (stripe_price_id),
    INDEX idx_stripe_product (stripe_product_id),
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MODIFY EXISTING TABLES
-- =====================================================

-- Add columns to users table (check if they exist first)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_category');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN preferred_category ENUM(''young_learners'', ''adults'', ''coding'') NULL AFTER learning_track',
    'SELECT "Column preferred_category already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'trial_used');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN trial_used BOOLEAN DEFAULT FALSE AFTER preferred_category',
    'SELECT "Column trial_used already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add columns to lessons table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND COLUMN_NAME = 'is_trial');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE lessons ADD COLUMN is_trial BOOLEAN DEFAULT FALSE AFTER status',
    'SELECT "Column is_trial already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND COLUMN_NAME = 'wallet_transaction_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE lessons ADD COLUMN wallet_transaction_id INT(6) UNSIGNED NULL AFTER is_trial',
    'SELECT "Column wallet_transaction_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND COLUMN_NAME = 'category');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE lessons ADD COLUMN category ENUM(''young_learners'', ''adults'', ''coding'') NULL AFTER wallet_transaction_id',
    'SELECT "Column category already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes to lessons table
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND INDEX_NAME = 'idx_wallet_transaction');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE lessons ADD INDEX idx_wallet_transaction (wallet_transaction_id)',
    'SELECT "Index idx_wallet_transaction already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND INDEX_NAME = 'idx_category');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE lessons ADD INDEX idx_category (category)',
    'SELECT "Index idx_category already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND INDEX_NAME = 'idx_is_trial');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE lessons ADD INDEX idx_is_trial (is_trial)',
    'SELECT "Index idx_is_trial already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for wallet_transaction_id
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lessons' 
    AND COLUMN_NAME = 'wallet_transaction_id' 
    AND REFERENCED_TABLE_NAME = 'wallet_transactions');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE lessons ADD CONSTRAINT fk_lesson_wallet_transaction FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add columns to messages table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'attachment_path');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message',
    'SELECT "Column attachment_path already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'attachment_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE messages ADD COLUMN attachment_type ENUM(''file'', ''image'', ''video'') NULL AFTER attachment_path',
    'SELECT "Column attachment_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'is_read');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE AFTER attachment_type',
    'SELECT "Column is_read already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'read_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL AFTER is_read',
    'SELECT "Column read_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes to messages table
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'idx_is_read');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE messages ADD INDEX idx_is_read (is_read)',
    'SELECT "Index idx_is_read already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'idx_read_at');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE messages ADD INDEX idx_read_at (read_at)',
    'SELECT "Index idx_read_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- MIGRATE EXISTING DATA
-- =====================================================

-- Migrate assigned_teacher_id data to teacher_categories (for reference)
-- This creates category assignments based on existing student-teacher relationships
INSERT INTO teacher_categories (teacher_id, category, is_active, created_at)
SELECT DISTINCT 
    u.assigned_teacher_id as teacher_id,
    COALESCE(u.learning_track, 'adults') as category,
    TRUE as is_active,
    NOW() as created_at
FROM users u
WHERE u.assigned_teacher_id IS NOT NULL
    AND u.role = 'student'
    AND u.learning_track IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM teacher_categories tc 
        WHERE tc.teacher_id = u.assigned_teacher_id 
        AND tc.category = COALESCE(u.learning_track, 'adults')
    )
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Initialize student_wallet for existing students
INSERT INTO student_wallet (student_id, balance, trial_credits, created_at)
SELECT id, 0.00, 0, NOW()
FROM users
WHERE role IN ('student', 'new_student')
    AND id NOT IN (SELECT student_id FROM student_wallet)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

SELECT 'Migration 002_student_selects_teacher completed successfully!' AS status;

