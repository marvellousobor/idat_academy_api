-- ============================================================
-- IDAT Academy Portal — Database Schema
-- Day 1: Foundation & Database
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `idat_academy` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `idat_academy`;

-- ------------------------------------------------------------
-- admins
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin','admin','staff') NOT NULL DEFAULT 'admin',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- tutors
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tutors` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30),
  `password_hash` VARCHAR(255) NOT NULL,
  `bio` TEXT,
  `photo` VARCHAR(255),
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- students
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `other_name` VARCHAR(100),
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30),
  `gender` ENUM('male','female','other'),
  `date_of_birth` DATE,
  `state` VARCHAR(100),
  `lga` VARCHAR(100),
  `address` VARCHAR(255),
  `password_hash` VARCHAR(255),
  `photo` VARCHAR(255),
  `status` ENUM('active','inactive','graduated','suspended') NOT NULL DEFAULT 'active',
  `application_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- courses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `description` TEXT,
  `image` VARCHAR(255),
  `icon` VARCHAR(10) DEFAULT '💡',
  `duration` VARCHAR(100),
  `learning_mode` ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical',
  `requirements` TEXT,
  `instructor_id` INT UNSIGNED NULL,
  `category` ENUM('professional','teens') NOT NULL DEFAULT 'professional',
  `price` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_courses_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `tutors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- course_modules
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_modules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `order_index` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_modules_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- student_courses (enrollment pivot — needed for "My Courses")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `progress` DECIMAL(5,2) DEFAULT 0.00,
  `status` ENUM('enrolled','completed','dropped') NOT NULL DEFAULT 'enrolled',
  `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_course` (`student_id`,`course_id`),
  CONSTRAINT `fk_sc_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- lessons
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `file_path` VARCHAR(255),
  `file_type` ENUM('pdf','ppt','notes','other') NOT NULL DEFAULT 'pdf',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_course_lesson_title` (`course_id`, `title`),
  CONSTRAINT `fk_lessons_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `course_modules`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lessons_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- assignments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `instructions` TEXT,
  `accepted_file_types` VARCHAR(150) DEFAULT 'pdf,doc,docx,zip',
  `max_score` DECIMAL(6,2) DEFAULT 100.00,
  `due_date` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_course_assignment_title` (`course_id`, `title`),
  CONSTRAINT `fk_assignments_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_module` FOREIGN KEY (`module_id`) REFERENCES `course_modules`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignments_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- assignment_submissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255),
  `typed_response` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `score` DECIMAL(6,2) NULL,
  `feedback` TEXT,
  `graded_by` INT UNSIGNED NULL,
  `graded_at` TIMESTAMP NULL,
  UNIQUE KEY `uniq_assignment_student` (`assignment_id`,`student_id`),
  CONSTRAINT `fk_subs_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subs_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subs_grader` FOREIGN KEY (`graded_by`) REFERENCES `tutors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- certificates
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `certificate_number` VARCHAR(100) NOT NULL UNIQUE,
  `file_path` VARCHAR(255),
  `issue_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_certs_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certs_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- applications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `other_name` VARCHAR(100),
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `gender` ENUM('male','female','other'),
  `date_of_birth` DATE,
  `state` VARCHAR(100),
  `lga` VARCHAR(100),
  `address` VARCHAR(255),
  `education_level` VARCHAR(100),
  `occupation` VARCHAR(150),
  `referral_source` VARCHAR(150),
  `preferred_courses` JSON,
  `preferred_mode` ENUM('physical','online') NOT NULL DEFAULT 'physical',
  `payment_proof` VARCHAR(255),
  `terms_agreed` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_applications_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- payments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT UNSIGNED NULL,
  `student_id` INT UNSIGNED NULL,
  `amount` DECIMAL(12,2) DEFAULT 0.00,
  `proof_file` VARCHAR(255),
  `status` ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` INT UNSIGNED NULL,
  `verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_application` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_verifier` FOREIGN KEY (`verified_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- testimonials
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NULL,
  `name` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `rating` TINYINT UNSIGNED DEFAULT 5,
  `photo` VARCHAR(255),
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `approved_at` TIMESTAMP NULL,
  CONSTRAINT `fk_testimonials_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- gallery
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200),
  `image_path` VARCHAR(255) NOT NULL UNIQUE,
  `category` VARCHAR(100),
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_gallery_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `type` ENUM('lesson','assignment','deadline','completion','certificate','announcement','general') NOT NULL DEFAULT 'general',
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- announcements — tutor-published, fan out to student notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tutor_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NULL COMMENT 'NULL = sent to all of this tutor''s courses',
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_announcements_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcements_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- contact_messages
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30),
  `subject` VARCHAR(200),
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Seed data — enough to make Day 1's "DoD" real:
-- blank homepage loads AND connects to the DB successfully
-- ============================================================

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('max_courses_per_student', '3'),
('academy_name', 'IDAT Academy'),
('academy_email', 'info@idatacademy.example'),
('academy_phone', '+234 000 000 0000'),
('whatsapp_number', '2340000000000'),
('stat_students_trained', '200+'),
('stat_programs', '12+'),
('stat_learning_mode', 'Physical & Online'),
('stat_industry_training', 'Practical Industry Training'),
('campus_location', 'Abuja')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Default password for the seed admin account: Admin@123
INSERT INTO `admins` (`name`, `email`, `password_hash`, `role`) VALUES
('Super Admin', 'admin@idatacademy.example', '$2b$10$XxdX473XK5tawfN3hwv13OcibW2Nj4okLiMy8kPBFeEBaiP.P3IzK', 'super_admin')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default password for ALL seed tutor accounts: Tutor@123
INSERT INTO `tutors` (`first_name`, `last_name`, `email`, `phone`, `password_hash`, `bio`) VALUES
('Ada', 'Okafor', 'ada.okafor@idatacademy.example', '+234 000 000 0001', '$2b$10$9zCmyEWUGOVDYamj15cLx.WXLWbkyEXK5phlMUkMdxyjxLQY8pl12', 'Lead instructor, Web Development & AI track.'),
('Tunde', 'Bakare', 'tunde.bakare@idatacademy.example', '+234 000 000 0002', '$2b$10$9zCmyEWUGOVDYamj15cLx.WXLWbkyEXK5phlMUkMdxyjxLQY8pl12', 'Lead instructor, Trading & Finance track (Crypto, Forex).'),
('Ngozi', 'Eze', 'ngozi.eze@idatacademy.example', '+234 000 000 0003', '$2b$10$9zCmyEWUGOVDYamj15cLx.WXLWbkyEXK5phlMUkMdxyjxLQY8pl12', 'Lead instructor, Digital Marketing & Design track.'),
('Ibrahim', 'Sule', 'ibrahim.sule@idatacademy.example', '+234 000 000 0004', '$2b$10$9zCmyEWUGOVDYamj15cLx.WXLWbkyEXK5phlMUkMdxyjxLQY8pl12', 'Lead instructor, Cybersecurity & Data track.')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

INSERT INTO `courses`
  (`title`, `slug`, `description`, `icon`, `image`, `duration`, `learning_mode`, `requirements`, `instructor_id`, `category`, `price`, `status`)
VALUES
('Artificial Intelligence (AI)', 'artificial-intelligence-ai',
  'Learn to use AI tools to boost productivity, solve problems, and build innovative digital solutions.',
  'fa-brain', 'assets/images/course-ai.jpg', '8 Weeks', 'hybrid', 'Basic computer literacy; a laptop is recommended', 1, 'professional', 100000.00, 'active'),

('Crypto Masterclass', 'crypto-masterclass',
  'Understand cryptocurrency, blockchain technology, trading, investing, and digital asset management.',
  'fa-bitcoin-sign', 'assets/images/course-crypto.jpg', '6 Weeks', 'online', 'Smartphone or laptop with internet access', 2, 'professional', 80000.00, 'active'),

('Data Analysis', 'data-analysis',
  'Transform raw data into meaningful insights using industry-standard tools for smarter decision-making.',
  'fa-chart-column', 'assets/images/course-data.jpg', '10 Weeks', 'hybrid', 'Basic computer literacy; no prior experience required', 4, 'professional', 100000.00, 'active'),

('Digital Marketing', 'digital-marketing',
  'Master strategies to grow businesses online through social media, SEO, paid ads, and content marketing.',
  'fa-bullhorn', 'assets/images/course-marketing.jpg', '8 Weeks', 'hybrid', 'Basic computer literacy', 3, 'professional', 90000.00, 'active'),

('Cybersecurity', 'cybersecurity',
  'Learn how to protect systems, networks, and data from cyber threats using industry best practices.',
  'fa-shield-halved', 'assets/images/course-cyber.jpg', '10 Weeks', 'hybrid', 'Basic computer literacy; networking basics helpful but not required', 4, 'professional', 110000.00, 'active'),

('Forex Trading', 'forex-trading',
  'Develop practical skills in analyzing financial markets and trading currencies with confidence.',
  'fa-chart-line', 'assets/images/course-forex.jpg', '6 Weeks', 'online', 'Smartphone or laptop with internet access', 2, 'professional', 80000.00, 'active'),

('Web Development', 'web-development',
  'Learn to design and build responsive, modern websites from the ground up.',
  'fa-code', 'assets/images/course-web.jpg', '12 Weeks', 'hybrid', 'Basic computer literacy', 1, 'professional', 120000.00, 'active'),

('Graphics Design & Video Editing', 'graphics-design-video-editing',
  'Create stunning graphics and professional videos for branding, marketing, and content creation.',
  'fa-palette', 'assets/images/course-design.jpg', '8 Weeks', 'hybrid', 'Basic computer literacy; a laptop is recommended', 3, 'professional', 90000.00, 'active'),

('Virtual Assistant', 'virtual-assistant',
  'Learn the tools and skills needed to provide professional remote administrative and business support to clients worldwide.',
  'fa-headphones', 'assets/images/course-va.jpg', '6 Weeks', 'online', 'Smartphone or laptop with internet access', 3, 'professional', 70000.00, 'active'),

('Teens Tech Program', 'teens-tech-program',
  'Empowering teenagers with future-ready digital skills through fun, practical, and engaging training.',
  'fa-rocket', 'assets/images/teens-program.png', '8 Weeks', 'physical', 'Ages 13–19; no prior experience required', 1, 'teens', 60000.00, 'active')

ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `icon` = VALUES(`icon`), `image` = VALUES(`image`);

INSERT INTO `testimonials` (`name`, `message`, `rating`, `status`, `approved_at`) VALUES
('Blessing A.', 'The Web Development program took me from knowing nothing to building my own projects in weeks. The hands-on approach made all the difference.', 5, 'approved', NOW()),
('Chidi O.', 'IDAT Academy''s Data Analysis course is practical and career-focused. I use what I learned every day at my new job.', 5, 'approved', NOW()),
('Fatima Y.', 'My daughter joined the Teens Tech Program and now she''s the one teaching us at home. Excellent instructors and a safe learning environment.', 5, 'approved', NOW())
ON DUPLICATE KEY UPDATE `message` = VALUES(`message`);

INSERT INTO `gallery` (`title`, `image_path`, `category`) VALUES
('Web Development Class in Session', 'assets/images/gallery/classroom-1.jpg', 'classroom'),
('Students Pairing on a Project', 'assets/images/gallery/classroom-2.jpg', 'classroom'),
('Cybersecurity Hands-on Workshop', 'assets/images/gallery/workshop-1.jpg', 'workshop'),
('Teens Tech Program Coding Session', 'assets/images/gallery/workshop-2.jpg', 'workshop'),
('Graduation Day Cohort Photo', 'assets/images/gallery/graduation-1.jpg', 'graduation'),
('Certificate Presentation', 'assets/images/gallery/graduation-2.jpg', 'graduation'),
('Open Day at IDAT Academy', 'assets/images/gallery/event-1.jpg', 'event'),
('Career Fair Meetup', 'assets/images/gallery/event-2.jpg', 'event')
ON DUPLICATE KEY UPDATE `image_path` = VALUES(`image_path`);

-- ============================================================
-- Day 4 seed data — Student Portal (LMS)
-- Default password for BOTH seed students: Student@123
-- (bcrypt hash below is real and verifiable with PHP's password_verify())
-- ============================================================

INSERT INTO `students`
  (`first_name`, `last_name`, `email`, `phone`, `gender`, `date_of_birth`, `state`, `lga`, `address`, `password_hash`, `status`)
VALUES
('Jane', 'Doe', 'jane.doe@example.com', '+234 801 111 1111', 'female', '2001-04-12', 'FCT - Abuja', 'Abuja Municipal', '12 Gwarinpa Estate, Abuja',
  '$2b$10$HijcqWmbGmyEOd/Uf.BRy.52kU4sN8YBbUuulKTH0YYSBQMqBOk.q', 'active'),
('John', 'Smith', 'john.smith@example.com', '+234 802 222 2222', 'male', '1999-11-03', 'Lagos', 'Ikeja', '4 Allen Avenue, Ikeja, Lagos',
  '$2b$10$HijcqWmbGmyEOd/Uf.BRy.52kU4sN8YBbUuulKTH0YYSBQMqBOk.q', 'active')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

-- Enrollments
INSERT INTO `student_courses` (`student_id`, `course_id`, `progress`, `status`)
VALUES
((SELECT id FROM students WHERE email = 'jane.doe@example.com'), (SELECT id FROM courses WHERE slug = 'web-development'), 45.00, 'enrolled'),
((SELECT id FROM students WHERE email = 'jane.doe@example.com'), (SELECT id FROM courses WHERE slug = 'data-analysis'), 100.00, 'completed'),
((SELECT id FROM students WHERE email = 'john.smith@example.com'), (SELECT id FROM courses WHERE slug = 'crypto-masterclass'), 10.00, 'enrolled')
ON DUPLICATE KEY UPDATE `progress` = VALUES(`progress`), `status` = VALUES(`status`);

-- Lessons (Web Development course, uploaded by Ada Okafor)
INSERT INTO `lessons` (`course_id`, `tutor_id`, `title`, `description`, `file_path`, `file_type`)
VALUES
((SELECT id FROM courses WHERE slug = 'web-development'), (SELECT id FROM tutors WHERE email = 'ada.okafor@idatacademy.example'),
  'Week 1: Introduction to HTML', 'Document structure, semantic HTML5 elements, forms, and accessibility basics.',
  'uploads/lessons/web-dev-week1-intro.pdf', 'pdf'),
((SELECT id FROM courses WHERE slug = 'web-development'), (SELECT id FROM tutors WHERE email = 'ada.okafor@idatacademy.example'),
  'Week 2: CSS Fundamentals (Slides)', 'The box model, flexbox, grid layout, and responsive design with Tailwind CSS.',
  'uploads/lessons/web-dev-week2-css-slides.pdf', 'ppt'),
((SELECT id FROM courses WHERE slug = 'web-development'), (SELECT id FROM tutors WHERE email = 'ada.okafor@idatacademy.example'),
  'Week 3: JavaScript Basics (Notes)', 'Variables, functions, DOM manipulation, and event handling.',
  'uploads/lessons/web-dev-week3-js-notes.pdf', 'notes')
ON DUPLICATE KEY UPDATE `file_path` = VALUES(`file_path`);

-- Assignments (Web Development course)
INSERT INTO `assignments` (`course_id`, `tutor_id`, `title`, `instructions`, `accepted_file_types`, `max_score`, `due_date`)
VALUES
((SELECT id FROM courses WHERE slug = 'web-development'), (SELECT id FROM tutors WHERE email = 'ada.okafor@idatacademy.example'),
  'CSS Layout Challenge', 'Recreate the supplied design mockup using Flexbox or Grid. Submit your HTML/CSS as a ZIP file.',
  'pdf,doc,docx,zip', 100.00, '2026-06-20 23:59:00'),
((SELECT id FROM courses WHERE slug = 'web-development'), (SELECT id FROM tutors WHERE email = 'ada.okafor@idatacademy.example'),
  'Personal Portfolio Page', 'Build a single-page personal portfolio site using semantic HTML and responsive CSS. Submit a ZIP of your project files or a typed link to your repository.',
  'pdf,doc,docx,zip', 100.00, '2026-07-25 23:59:00')
ON DUPLICATE KEY UPDATE `instructions` = VALUES(`instructions`);

-- Assignment submission — CSS Layout Challenge, already graded (demonstrates Results page)
INSERT INTO `assignment_submissions` (`assignment_id`, `student_id`, `typed_response`, `score`, `feedback`, `graded_by`, `graded_at`)
VALUES
(
  (SELECT id FROM assignments WHERE title = 'CSS Layout Challenge'),
  (SELECT id FROM students WHERE email = 'jane.doe@example.com'),
  'Submitted via typed response for seed/demo purposes: https://example.com/jane-css-challenge',
  88.00,
  'Great use of Flexbox! Clean, readable layout — just watch your spacing consistency on smaller screens.',
  (SELECT id FROM tutors WHERE email = 'ada.okafor@idatacademy.example'),
  NOW()
)
ON DUPLICATE KEY UPDATE `score` = VALUES(`score`), `feedback` = VALUES(`feedback`);

-- Certificate — Jane completed Data Analysis
INSERT INTO `certificates` (`student_id`, `course_id`, `certificate_number`, `file_path`, `issue_date`)
VALUES
(
  (SELECT id FROM students WHERE email = 'jane.doe@example.com'),
  (SELECT id FROM courses WHERE slug = 'data-analysis'),
  'IDAT-DA-2026-0001',
  'uploads/certificates/cert-sample-jane-doe-data-analysis.pdf',
  '2026-06-15'
)
ON DUPLICATE KEY UPDATE `file_path` = VALUES(`file_path`);

-- Notifications
INSERT INTO `notifications` (`student_id`, `type`, `title`, `message`, `is_read`)
VALUES
((SELECT id FROM students WHERE email = 'jane.doe@example.com'), 'lesson', 'New Lesson Uploaded', 'Week 3: JavaScript Basics (Notes) is now available in Web Development.', 0),
((SELECT id FROM students WHERE email = 'jane.doe@example.com'), 'deadline', 'Assignment Due Soon', 'Personal Portfolio Page is due soon — submit it from the Assignments page.', 0),
((SELECT id FROM students WHERE email = 'jane.doe@example.com'), 'certificate', 'Certificate Available', 'Your Data Analysis certificate is ready to download.', 1),
((SELECT id FROM students WHERE email = 'john.smith@example.com'), 'general', 'Welcome to IDAT Academy!', 'Your enrollment in Crypto Masterclass is confirmed. Check My Courses to get started.', 0);

