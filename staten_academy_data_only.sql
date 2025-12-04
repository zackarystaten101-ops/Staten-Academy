-- phpMyAdmin SQL Dump - DATA ONLY with IF checks
-- This file contains INSERT statements with conditional checks to avoid duplicates
-- Use this when you already have the table structure created
-- This version uses only columns that are guaranteed to exist
--
-- Generation Time: Dec 04, 2025 at 06:59 PM
-- Server version: 10.4.32-MariaDB

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Dumping data for table `users`
-- IMPORTANT: Users must be inserted FIRST due to foreign key constraints
-- Using only core columns that are guaranteed to exist
-- --------------------------------------------------------

INSERT INTO `users` (`id`, `email`, `backup_email`, `password`, `google_id`, `name`, `role`, `dob`, `age`, `age_visibility`, `bio`, `hours_taught`, `hours_available`, `calendly_link`, `application_status`, `profile_pic`, `about_text`, `video_url`, `google_calendar_token`, `google_calendar_token_expiry`, `google_calendar_refresh_token`, `reg_date`, `specialty`, `hourly_rate`)
SELECT 1, 'ZackaryStaten101@Gmail.com', '0', '$2y$10$PPvxh1GGF0of5tLo.z.oYe/QWt5lu.Ye0.xcFmDo1Ve62qPQV38Oe', NULL, 'Zackary Staten', 'teacher', '0000-00-00', NULL, '', 'I am a TESOL-certified English teacher with over 10 years of teaching experience in general and 4 years in the English field ', 0, 0, '', 'approved', 'images/user_1_1764242096.jpg', NULL, NULL, NULL, NULL, NULL, '2025-12-02 21:21:39', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `id` = 1);

INSERT INTO `users` (`id`, `email`, `backup_email`, `password`, `google_id`, `name`, `role`, `dob`, `age`, `age_visibility`, `bio`, `hours_taught`, `hours_available`, `calendly_link`, `application_status`, `profile_pic`, `about_text`, `video_url`, `google_calendar_token`, `google_calendar_token_expiry`, `google_calendar_refresh_token`, `reg_date`, `specialty`, `hourly_rate`)
SELECT 2, 'nathanielstaten@gmail.com', 'ZackaryStaten101@Gmail.com', '$2y$10$5ffoj77Gxr8.m3n8UiB7Ie5x/cJ26j5bstPUO.lnv6RINeHCZrbRW', NULL, 'Jamin Staten', 'teacher', NULL, 21, 'private', 'I am a teacher', 0, 0, NULL, 'approved', 'images/student_2_1764283280.jpg', NULL, NULL, NULL, NULL, NULL, '2025-12-02 22:13:30', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `id` = 2);

INSERT INTO `users` (`id`, `email`, `backup_email`, `password`, `google_id`, `name`, `role`, `dob`, `age`, `age_visibility`, `bio`, `hours_taught`, `hours_available`, `calendly_link`, `application_status`, `profile_pic`, `about_text`, `video_url`, `google_calendar_token`, `google_calendar_token_expiry`, `google_calendar_refresh_token`, `reg_date`, `specialty`, `hourly_rate`)
SELECT 3, 'statenenglishacademy@gmail.com', NULL, '$2y$10$mEUkw2w95cEh6bnJw2OdaOCHTFkCedWiEXKGcorHa1wdB3xDN4YUe', NULL, 'Admin', 'admin', NULL, NULL, 'private', NULL, 0, 0, NULL, 'approved', 'images/placeholder-teacher.svg', NULL, NULL, NULL, NULL, NULL, '2025-11-28 14:35:46', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `id` = 3);

INSERT INTO `users` (`id`, `email`, `backup_email`, `password`, `google_id`, `name`, `role`, `dob`, `age`, `age_visibility`, `bio`, `hours_taught`, `hours_available`, `calendly_link`, `application_status`, `profile_pic`, `about_text`, `video_url`, `google_calendar_token`, `google_calendar_token_expiry`, `google_calendar_refresh_token`, `reg_date`, `specialty`, `hourly_rate`)
SELECT 4, 'student@statenacademy.com', NULL, '$2y$10$lCwJbwKu24XZeG6VoVG9Ou8cSnPZEBAf8LDQWShIcJvZ8IScPEmCi', NULL, 'Student', 'student', NULL, NULL, 'private', NULL, 0, 0, NULL, 'none', 'images/placeholder-teacher.svg', NULL, NULL, NULL, NULL, NULL, '2025-12-02 13:14:00', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `id` = 4);

-- --------------------------------------------------------
-- Dumping data for table `bookings`
-- Note: Requires users to exist first (foreign key constraint)
-- --------------------------------------------------------

INSERT INTO `bookings` (`id`, `student_id`, `teacher_id`, `booking_date`)
SELECT 1, 1, 1, '2025-11-27 10:30:09'
WHERE NOT EXISTS (SELECT 1 FROM `bookings` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` = 1);

-- --------------------------------------------------------
-- Dumping data for table `message_threads`
-- Note: Requires users to exist first (foreign key constraint)
-- --------------------------------------------------------

INSERT INTO `message_threads` (`id`, `initiator_id`, `recipient_id`, `last_message_at`, `thread_type`)
SELECT 1, 3, 1, '2025-11-27 23:18:00', 'user'
WHERE NOT EXISTS (SELECT 1 FROM `message_threads` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` IN (1, 3));

-- --------------------------------------------------------
-- Dumping data for table `messages`
-- Note: Requires message_threads and users to exist first (foreign key constraints)
-- --------------------------------------------------------

INSERT INTO `messages` (`id`, `thread_id`, `sender_id`, `receiver_id`, `message`, `message_type`, `sent_at`)
SELECT 1, 1, 3, 1, 'Hey how are you I am interested in your classes', 'direct', '2025-11-27 23:18:00'
WHERE NOT EXISTS (SELECT 1 FROM `messages` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `message_threads` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` IN (1, 3));

INSERT INTO `messages` (`id`, `thread_id`, `sender_id`, `receiver_id`, `message`, `message_type`, `sent_at`)
SELECT 2, 1, 1, 3, 'Hey I am fine. How are you?\r\nI am glad to see that you are interested in my classes! What are your goals in learning English, and what is your level', 'direct', '2025-11-28 14:33:14'
WHERE NOT EXISTS (SELECT 1 FROM `messages` WHERE `id` = 2)
AND EXISTS (SELECT 1 FROM `message_threads` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` IN (1, 3));

INSERT INTO `messages` (`id`, `thread_id`, `sender_id`, `receiver_id`, `message`, `message_type`, `sent_at`)
SELECT 3, 1, 1, 3, 'testing', 'direct', '2025-12-02 19:03:30'
WHERE NOT EXISTS (SELECT 1 FROM `messages` WHERE `id` = 3)
AND EXISTS (SELECT 1 FROM `message_threads` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` IN (1, 3));

-- --------------------------------------------------------
-- Dumping data for table `support_messages`
-- Note: Requires users to exist first (foreign key constraint)
-- --------------------------------------------------------

INSERT INTO `support_messages` (`id`, `sender_id`, `sender_role`, `message`, `subject`, `status`, `created_at`)
SELECT 1, 1, 'teacher', 'I am testing', 'test', 'open', '2025-11-27 16:38:39'
WHERE NOT EXISTS (SELECT 1 FROM `support_messages` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` = 1);

INSERT INTO `support_messages` (`id`, `sender_id`, `sender_role`, `message`, `subject`, `status`, `created_at`)
SELECT 2, 2, 'student', 'Does it work', 'test', 'read', '2025-11-27 22:34:17'
WHERE NOT EXISTS (SELECT 1 FROM `support_messages` WHERE `id` = 2)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` = 2);

INSERT INTO `support_messages` (`id`, `sender_id`, `sender_role`, `message`, `subject`, `status`, `created_at`)
SELECT 3, 4, 'student', 'I am testing', 'test', 'open', '2025-12-02 13:15:37'
WHERE NOT EXISTS (SELECT 1 FROM `support_messages` WHERE `id` = 3)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` = 4);

-- --------------------------------------------------------
-- Dumping data for table `teacher_availability`
-- Note: Requires users to exist first (foreign key constraint)
-- --------------------------------------------------------

INSERT INTO `teacher_availability` (`id`, `teacher_id`, `day_of_week`, `start_time`, `end_time`, `is_available`, `created_at`, `updated_at`)
SELECT 1, 1, 'Tuesday', '04:00:00', '17:00:00', 1, '2025-11-29 02:48:42', '2025-11-29 02:48:57'
WHERE NOT EXISTS (SELECT 1 FROM `teacher_availability` WHERE `id` = 1)
AND EXISTS (SELECT 1 FROM `users` WHERE `id` = 1);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
