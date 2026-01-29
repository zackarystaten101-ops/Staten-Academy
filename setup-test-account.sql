-- Test Account Setup SQL Script
-- Sets up student@statenacademy.com with unlimited classes and all features activated
-- Run this SQL script directly in your database

-- 1. Activate student account (change from new_student to student)
UPDATE users 
SET role = 'student' 
WHERE email = 'student@statenacademy.com';

-- 2. Get IDs for reference
SET @student_id = (SELECT id FROM users WHERE email = 'student@statenacademy.com');
SET @teacher_id = (SELECT id FROM users WHERE email = 'zackarystaten101@gmail.com');

-- 3. Add teacher to student's favorites (if not already)
INSERT IGNORE INTO favorite_teachers (student_id, teacher_id) 
VALUES (@student_id, @teacher_id);

-- 4. Create a booking record (for compatibility)
INSERT IGNORE INTO bookings (student_id, teacher_id, booking_date) 
VALUES (@student_id, @teacher_id, NOW());

-- 5. Create test lessons (past, today, and future)
-- Past lesson (completed)
INSERT IGNORE INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
VALUES (@teacher_id, @student_id, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:00:00', '11:00:00', 'completed', 'single', '#0b6cf5');

-- Today's lesson (if time allows - adjust start_time as needed)
INSERT IGNORE INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
VALUES (@teacher_id, @student_id, CURDATE(), DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 HOUR), '%H:00:00'), DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 2 HOUR), '%H:00:00'), 'scheduled', 'single', '#0b6cf5');

-- Future lessons (next 7 days at 2 PM)
INSERT IGNORE INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
VALUES 
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5'),
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5'),
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5'),
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5'),
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5'),
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5'),
    (@teacher_id, @student_id, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '15:00:00', 'scheduled', 'single', '#0b6cf5');

-- Verification queries (run these to check setup)
-- SELECT role FROM users WHERE email = 'student@statenacademy.com';
-- SELECT COUNT(*) as total_lessons FROM lessons WHERE student_id = @student_id AND teacher_id = @teacher_id;
-- SELECT COUNT(*) as upcoming_lessons FROM lessons WHERE student_id = @student_id AND teacher_id = @teacher_id AND status = 'scheduled' AND lesson_date >= CURDATE();












