-- Migration: Add specific_date column to teacher_availability table
-- This allows for one-time availability slots in addition to weekly recurring slots

ALTER TABLE teacher_availability 
ADD COLUMN specific_date DATE NULL AFTER day_of_week,
MODIFY COLUMN day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NULL;

-- Update unique constraint to allow same time slot on different specific dates
ALTER TABLE teacher_availability 
DROP INDEX unique_teacher_slot,
ADD UNIQUE KEY unique_teacher_slot (teacher_id, day_of_week, specific_date, start_time);





