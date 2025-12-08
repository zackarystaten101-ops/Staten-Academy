-- SQL script to create test student account
-- NOTE: This SQL script uses a pre-generated password hash
-- For better security, use the PHP script (create_test_student.php) instead
-- 
-- Email: student@statenacademy.com
-- Password: 123456789
-- Role: student

-- First, check if user exists
SET @email = 'student@statenacademy.com';
SET @name = 'Test Student';
SET @role = 'student';
SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; -- hash for '123456789'

-- Update if exists, insert if not (requires unique constraint on email)
INSERT INTO users (name, email, password, role, has_purchased_class)
VALUES (@name, @email, @password_hash, @role, TRUE)
ON DUPLICATE KEY UPDATE
    name = @name,
    password = @password_hash,
    role = @role,
    has_purchased_class = TRUE;

-- Verify the account was created/updated
SELECT id, name, email, role, has_purchased_class FROM users WHERE email = @email;

