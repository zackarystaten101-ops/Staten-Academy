<?php
/**
 * Migration: Add Credit System
 * 
 * This migration:
 * 1. Adds credit-related columns to users table
 * 2. Creates credit_transactions table
 * 3. Creates gift_credit_purchases table
 * 4. Creates gift_credit_products table
 * 5. Updates subscription_plans table structure
 */

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../db.php';

echo "Starting migration: Add Credit System\n";
echo "======================================\n\n";

// Get existing columns from users table
$cols = $conn->query("SHOW COLUMNS FROM users");
$existing_cols = [];
if ($cols) {
    while($row = $cols->fetch_assoc()) { 
        $existing_cols[] = $row['Field']; 
    }
}

// Add stripe_customer_id if not exists
if (!in_array('stripe_customer_id', $existing_cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN stripe_customer_id VARCHAR(255) NULL");
    echo "✓ Added stripe_customer_id column to users table\n";
} else {
    echo "- stripe_customer_id column already exists\n";
}

// Add credits_balance if not exists
if (!in_array('credits_balance', $existing_cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN credits_balance INT DEFAULT 0");
    echo "✓ Added credits_balance column to users table\n";
} else {
    echo "- credits_balance column already exists\n";
}

// Add credits_gifted if not exists
if (!in_array('credits_gifted', $existing_cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN credits_gifted INT DEFAULT 0");
    echo "✓ Added credits_gifted column to users table\n";
} else {
    echo "- credits_gifted column already exists\n";
}

// Add active_subscription_id if not exists
if (!in_array('active_subscription_id', $existing_cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN active_subscription_id VARCHAR(255) NULL");
    echo "✓ Added active_subscription_id column to users table\n";
} else {
    echo "- active_subscription_id column already exists\n";
}

// Add subscription_billing_cycle_date if not exists
if (!in_array('subscription_billing_cycle_date', $existing_cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN subscription_billing_cycle_date INT NULL");
    echo "✓ Added subscription_billing_cycle_date column to users table\n";
} else {
    echo "- subscription_billing_cycle_date column already exists\n";
}

// Add subscription_payment_failed flag if not exists
if (!in_array('subscription_payment_failed', $existing_cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN subscription_payment_failed BOOLEAN DEFAULT FALSE");
    echo "✓ Added subscription_payment_failed column to users table\n";
} else {
    echo "- subscription_payment_failed column already exists\n";
}

// Create credit_transactions table
$sql = "CREATE TABLE IF NOT EXISTS credit_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT(6) UNSIGNED NOT NULL,
    type ENUM('admin_add', 'admin_remove', 'purchase', 'subscription_renewal', 'gift_received', 'gift_sent', 'lesson_used') NOT NULL,
    amount INT NOT NULL,
    description TEXT,
    reference_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_type (type),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "✓ Created credit_transactions table\n";
} else {
    echo "✗ Error creating credit_transactions table: " . $conn->error . "\n";
}

// Create gift_credit_purchases table
$sql = "CREATE TABLE IF NOT EXISTS gift_credit_purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchaser_id INT(6) UNSIGNED NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_id INT(6) UNSIGNED NULL,
    credits_amount INT NOT NULL,
    stripe_payment_id VARCHAR(255) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_purchaser (purchaser_id),
    INDEX idx_recipient (recipient_id),
    FOREIGN KEY (purchaser_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "✓ Created gift_credit_purchases table\n";
} else {
    echo "✗ Error creating gift_credit_purchases table: " . $conn->error . "\n";
}

// Create gift_credit_products table
$sql = "CREATE TABLE IF NOT EXISTS gift_credit_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    credits_amount INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stripe_product_id VARCHAR(255) NOT NULL,
    stripe_price_id VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "✓ Created gift_credit_products table\n";
} else {
    echo "✗ Error creating gift_credit_products table: " . $conn->error . "\n";
}

// Update subscription_plans table
$plan_cols = $conn->query("SHOW COLUMNS FROM subscription_plans");
$existing_plan_cols = [];
if ($plan_cols) {
    while($row = $plan_cols->fetch_assoc()) { 
        $existing_plan_cols[] = $row['Field']; 
    }
}

// Add type column if not exists
if (!in_array('type', $existing_plan_cols)) {
    $conn->query("ALTER TABLE subscription_plans ADD COLUMN type ENUM('package', 'subscription', 'addon') DEFAULT 'subscription'");
    echo "✓ Added type column to subscription_plans table\n";
} else {
    echo "- type column already exists in subscription_plans table\n";
}

// Add credits_included column if not exists
if (!in_array('credits_included', $existing_plan_cols)) {
    $conn->query("ALTER TABLE subscription_plans ADD COLUMN credits_included INT DEFAULT 0");
    echo "✓ Added credits_included column to subscription_plans table\n";
} else {
    echo "- credits_included column already exists in subscription_plans table\n";
}

// Add billing_cycle_days column if not exists
if (!in_array('billing_cycle_days', $existing_plan_cols)) {
    $conn->query("ALTER TABLE subscription_plans ADD COLUMN billing_cycle_days INT NULL");
    echo "✓ Added billing_cycle_days column to subscription_plans table\n";
} else {
    echo "- billing_cycle_days column already exists in subscription_plans table\n";
}

echo "\nMigration completed successfully!\n";

