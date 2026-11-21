SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `cms_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `cms_db`;

CREATE TABLE `announcements` (
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `expires_at` datetime DEFAULT NULL,
  `attachment_document_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `announcements` (`announcement_id`, `campus_id`, `title`, `content`, `priority`, `expires_at`, `attachment_document_id`, `status`, `published_at`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Enrollment Opens December 1', 'Enrollment for the next term opens on December 1. See the attached guide.', 'high', '2026-12-31 23:59:59', 1, 'published', '2026-11-10 08:00:00', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, NULL, 'Library Extended Hours', 'The library will stay open until midnight during final exams.', 'normal', '2026-12-20 23:59:59', NULL, 'draft', NULL, 2, 2, '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `announcement_categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `announcement_categories` (`category_id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Academic', 'academic', 'Academic notices and deadlines', '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, 'General', 'general', 'General campus announcements', '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `announcement_category_map` (
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `announcement_category_map` (`announcement_id`, `category_id`) VALUES
(1, 1),
(2, 2);

CREATE TABLE `campuses` (
  `campus_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `campuses` (`campus_id`, `name`, `code`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Lingayen Campus', 'LC', 'Alvear St., Poblacion, Lingayen, 2401 Pangasinan', 'active', '2026-11-16 10:01:52', '2026-11-16 10:09:57'),
(2, 'Urdaneta City Campus', 'UCC', 'Urdaneta City, Pangasinan', 'active', '2026-11-16 10:01:52', '2026-11-16 10:10:35');

CREATE TABLE `colleges` (
  `college_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `dean_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo_media_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `colleges` (`college_id`, `name`, `slug`, `code`, `description`, `dean_name`, `email`, `logo_media_id`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'College of Engineering', 'college-of-engineering', 'COE', 'Engineering and technology programs.', 'Dr. Ana Villanueva', 'coe@psu.edu.ph', 2, 'active', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:12:33'),
(2, 'College of Arts and Sciences', 'college-of-arts-and-sciences', 'CAS', 'Liberal arts, natural and social sciences.', 'Dr. Paolo Mendoza', 'cas@psu.edu.ph', NULL, 'active', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:12:33');

CREATE TABLE `departments` (
  `department_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `head_faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`department_id`, `college_id`, `head_faculty_id`, `name`, `slug`, `code`, `description`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Information Technology Department', 'information-technology', 'IT', 'Focuses on software development, programming, networking, database management, and emerging technologies.', 'active', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:16:27'),
(2, 2, 2, 'Computer Engineering Department', 'computer-engineering', 'COE', 'Focuses on computer hardware, software, embedded systems, networking, and digital technology.', 'active', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:17:24');

CREATE TABLE `documents` (
  `document_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `documents` (`document_id`, `title`, `file_name`, `file_path`, `mime_type`, `file_size`, `uploaded_by`, `created_at`) VALUES
(1, 'Enrollment Guide', 'enrollment-guide.pdf', '/uploads/docs/enrollment-guide.pdf', 'application/pdf', 512000, 1, '2026-11-16 10:01:52'),
(2, 'Academic Calendar', 'academic-calendar.pdf', '/uploads/docs/academic-calendar.pdf', 'application/pdf', 204800, 2, '2026-11-16 10:01:52');

CREATE TABLE `events` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `cover_media_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `organizer` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','cancelled','completed','archived') NOT NULL DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `events` (`event_id`, `cover_media_id`, `title`, `slug`, `description`, `category_id`, `venue`, `start_at`, `end_at`, `organizer`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Annual Research Symposium', 'annual-research-symposium', 'Faculty and students present their latest research.', 1, 'Main Auditorium', '2026-12-05 09:00:00', '2026-12-05 17:00:00', 'Office of Research', 'published', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, NULL, 'Founders Day Festival', 'founders-day-festival', 'A day of food, music and games to celebrate the founding of the university.', 2, 'University Quadrangle', '2027-01-20 10:00:00', '2027-01-20 20:00:00', 'Student Council', 'draft', 2, 2, '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `event_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `event_categories` (`id`, `name`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'Academic', 'Lectures, seminars and conferences', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, 'Social', 'Student social and cultural events', 2, 2, '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `faculty` (
  `faculty_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `faculty` (`faculty_id`, `department_id`, `name`, `position`, `email`, `summary`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Dr. Luis Fernandez', 'Professor and Department Chair', 'luis.fernandez@university.edu', 'Specializes in embedded systems and IoT.', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, 2, 'Dr. Elena Cruz', 'Associate Professor', 'elena.cruz@university.edu', 'Researches marine biology and conservation.', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `media` (
  `media_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `media` (`media_id`, `file_name`, `file_path`, `mime_type`, `file_size`, `alt_text`, `uploaded_by`, `created_at`) VALUES
(1, 'graduation-2026.jpg', '/uploads/media/graduation-2026.jpg', 'image/jpeg', 245760, 'Graduates tossing caps', 1, '2026-11-16 10:01:52'),
(2, 'engineering-logo.png', '/uploads/media/engineering-logo.png', 'image/png', 18432, 'College of Engineering logo', 2, '2026-11-16 10:01:52');

CREATE TABLE `news` (
  `news_id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `cover_media_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `author_name` varchar(150) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `news` (`news_id`, `campus_id`, `cover_media_id`, `title`, `slug`, `author_name`, `summary`, `content`, `status`, `published_at`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Class of 2026 Graduation Ceremony', 'class-of-2026-graduation-ceremony', 'Maria Santos', 'Over 1,200 students graduated this year.', 'The university celebrated the graduation of the Class of 2026 at the Main Campus gymnasium.', 'published', '2026-06-15 10:00:00', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, 2, NULL, 'New Research Laboratory Opens', 'new-research-laboratory-opens', 'John Reyes', 'A new lab supports sustainable energy research.', 'The North Campus opened a state-of-the-art laboratory dedicated to sustainable energy research.', 'draft', NULL, 2, 2, '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `news_categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `news_categories` (`category_id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Campus Life', 'campus-life', 'Stories about student and campus activities', '2026-11-16 10:01:52', '2026-11-16 10:01:52'),
(2, 'Research', 'research', 'Research achievements and publications', '2026-11-16 10:01:52', '2026-11-16 10:01:52');

CREATE TABLE `news_category_map` (
  `news_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `news_category_map` (`news_id`, `category_id`) VALUES
(1, 1),
(2, 2);

CREATE TABLE `programs` (
  `program_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `degree_level` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `programs` (`program_id`, `college_id`, `department_id`, `code`, `name`, `slug`, `degree_level`, `description`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'BSIT', 'BS in Information Technology', 'bs-information-technology', 'Bachelor', 'Four-year program in information technology.', 'active', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:19:16'),
(2, 2, 2, 'BSCPE', 'BS in Computer Engineering', 'bs-computer-engineering', 'Bachelor', 'Four-year program in computer engineering.', 'active', 1, 1, '2026-11-16 10:01:52', '2026-11-16 10:19:44');

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'editor',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Maria Santos', 'maria.santos@university.edu', '$2b$12$nMP0ASAzDUdPAQbnPSS8tu2pHMkwrewKs0plNLcWM1VDgphjb5FAy', 'admin', 'active', '2026-11-16 10:01:52', '2026-11-16 10:20:43'),
(2, 'John Reyes', 'john.reyes@university.edu', '$2b$12$TgTYpcxmVQSmNXQDWHaq/.IanuriVaWZEyIMpMIh8iblMT/E9v8OW', 'editor', 'active', '2026-11-16 10:01:52', '2026-11-16 10:21:00');


ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `idx_announcements_campus` (`campus_id`),
  ADD KEY `idx_announcements_document` (`attachment_document_id`),
  ADD KEY `idx_announcements_status_published` (`status`,`published_at`),
  ADD KEY `idx_announcements_expires` (`expires_at`),
  ADD KEY `idx_announcements_created_by` (`created_by`),
  ADD KEY `idx_announcements_updated_by` (`updated_by`);

ALTER TABLE `announcement_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_announcement_categories_name` (`name`),
  ADD UNIQUE KEY `uq_announcement_categories_slug` (`slug`);

ALTER TABLE `announcement_category_map`
  ADD PRIMARY KEY (`announcement_id`,`category_id`),
  ADD KEY `idx_acm_category` (`category_id`);

ALTER TABLE `campuses`
  ADD PRIMARY KEY (`campus_id`),
  ADD UNIQUE KEY `uq_campuses_code` (`code`);

ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`),
  ADD UNIQUE KEY `uq_colleges_slug` (`slug`),
  ADD UNIQUE KEY `uq_colleges_code` (`code`),
  ADD KEY `idx_colleges_logo_media` (`logo_media_id`),
  ADD KEY `idx_colleges_created_by` (`created_by`),
  ADD KEY `idx_colleges_updated_by` (`updated_by`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `uq_departments_slug` (`slug`),
  ADD UNIQUE KEY `uq_departments_code` (`code`),
  ADD KEY `idx_departments_college` (`college_id`),
  ADD KEY `idx_departments_head` (`head_faculty_id`),
  ADD KEY `idx_departments_created_by` (`created_by`),
  ADD KEY `idx_departments_updated_by` (`updated_by`);

ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_documents_uploaded_by` (`uploaded_by`);

ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD UNIQUE KEY `uq_events_slug` (`slug`),
  ADD KEY `idx_events_cover_media` (`cover_media_id`),
  ADD KEY `idx_events_category` (`category_id`),
  ADD KEY `idx_events_start` (`start_at`),
  ADD KEY `idx_events_status` (`status`),
  ADD KEY `idx_events_created_by` (`created_by`),
  ADD KEY `idx_events_updated_by` (`updated_by`);

ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_categories_name` (`name`),
  ADD KEY `idx_event_categories_created_by` (`created_by`),
  ADD KEY `idx_event_categories_updated_by` (`updated_by`);

ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `uq_faculty_email` (`email`),
  ADD KEY `idx_faculty_department` (`department_id`),
  ADD KEY `idx_faculty_created_by` (`created_by`),
  ADD KEY `idx_faculty_updated_by` (`updated_by`);

ALTER TABLE `media`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `idx_media_uploaded_by` (`uploaded_by`);

ALTER TABLE `news`
  ADD PRIMARY KEY (`news_id`),
  ADD UNIQUE KEY `uq_news_slug` (`slug`),
  ADD KEY `idx_news_campus` (`campus_id`),
  ADD KEY `idx_news_cover_media` (`cover_media_id`),
  ADD KEY `idx_news_status_published` (`status`,`published_at`),
  ADD KEY `idx_news_created_by` (`created_by`),
  ADD KEY `idx_news_updated_by` (`updated_by`);

ALTER TABLE `news_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_news_categories_name` (`name`),
  ADD UNIQUE KEY `uq_news_categories_slug` (`slug`);

ALTER TABLE `news_category_map`
  ADD PRIMARY KEY (`news_id`,`category_id`),
  ADD KEY `idx_news_category_map_category` (`category_id`);

ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `uq_programs_slug` (`slug`),
  ADD UNIQUE KEY `uq_programs_code` (`code`),
  ADD KEY `idx_programs_college` (`college_id`),
  ADD KEY `idx_programs_department` (`department_id`),
  ADD KEY `idx_programs_created_by` (`created_by`),
  ADD KEY `idx_programs_updated_by` (`updated_by`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);


ALTER TABLE `announcements`
  MODIFY `announcement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `announcement_categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `campuses`
  MODIFY `campus_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `colleges`
  MODIFY `college_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `departments`
  MODIFY `department_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `events`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `event_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `faculty`
  MODIFY `faculty_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `media`
  MODIFY `media_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `news`
  MODIFY `news_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `news_categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `programs`
  MODIFY `program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_document` FOREIGN KEY (`attachment_document_id`) REFERENCES `documents` (`document_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `announcement_category_map`
  ADD CONSTRAINT `fk_acm_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_acm_category` FOREIGN KEY (`category_id`) REFERENCES `announcement_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `colleges`
  ADD CONSTRAINT `fk_colleges_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_colleges_logo_media` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_colleges_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_departments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_departments_head` FOREIGN KEY (`head_faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_departments_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `documents`
  ADD CONSTRAINT `fk_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_cover_media` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `event_categories`
  ADD CONSTRAINT `fk_event_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_event_categories_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `faculty`
  ADD CONSTRAINT `fk_faculty_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_faculty_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_faculty_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `media`
  ADD CONSTRAINT `fk_media_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_news_cover_media` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_news_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_news_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `news_category_map`
  ADD CONSTRAINT `fk_ncm_category` FOREIGN KEY (`category_id`) REFERENCES `news_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncm_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`news_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `programs`
  ADD CONSTRAINT `fk_programs_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_programs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_programs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_programs_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
