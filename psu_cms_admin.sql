-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: psu_cms_admin
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `entity_title` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement_categories`
--

DROP TABLE IF EXISTS `announcement_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_announcement_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_categories`
--

LOCK TABLES `announcement_categories` WRITE;
/*!40000 ALTER TABLE `announcement_categories` DISABLE KEYS */;
INSERT INTO `announcement_categories` VALUES (1,'Academic','academic','Academic notices and deadlines'),(2,'Advisory',NULL,NULL),(3,'Scholarship',NULL,NULL),(4,'Administrative',NULL,NULL),(5,'General','general','General campus announcements');
/*!40000 ALTER TABLE `announcement_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author_name` varchar(150) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `attachment_document_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`announcement_id`),
  KEY `fk_ann_campus` (`campus_id`),
  KEY `fk_ann_department` (`department_id`),
  KEY `fk_ann_category` (`category_id`),
  KEY `fk_ann_creator` (`created_by`),
  CONSTRAINT `fk_ann_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ann_category` FOREIGN KEY (`category_id`) REFERENCES `announcement_categories` (`category_id`),
  CONSTRAINT `fk_ann_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_ann_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,1,NULL,1,1,'Enrollment Opens December 1','Maria Santos','Enrollment for the next term opens on December 1. See the attached guide.','high','2026-11-10 08:00:00','2026-12-31 23:59:59',1,'published','2026-11-16 18:01:52','2026-11-16 18:01:52'),(2,NULL,NULL,5,2,'Library Extended Hours','John Reyes','The library will stay open until midnight during final exams.','normal',NULL,'2026-12-20 23:59:59',NULL,'draft','2026-11-16 18:01:52','2026-11-16 18:01:52');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campuses`
--

DROP TABLE IF EXISTS `campuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campuses` (
  `campus_id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`campus_id`),
  UNIQUE KEY `uq_campuses_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campuses`
--

LOCK TABLES `campuses` WRITE;
/*!40000 ALTER TABLE `campuses` DISABLE KEYS */;
INSERT INTO `campuses` VALUES (1,'LC','Lingayen Campus','Alvear St., Poblacion, Lingayen, 2401 Pangasinan',1,'active','2026-11-16 18:01:52','2026-11-16 18:09:57'),(2,'UCC','Urdaneta City Campus','Urdaneta City, Pangasinan',0,'active','2026-11-16 18:01:52','2026-11-16 18:10:35');
/*!40000 ALTER TABLE `campuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `colleges`
--

DROP TABLE IF EXISTS `colleges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `colleges` (
  `college_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `dean_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `logo_media_id` int(11) DEFAULT NULL,
  `header_media_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`college_id`),
  UNIQUE KEY `uq_colleges_campus_code` (`campus_id`,`code`),
  KEY `fk_colleges_creator` (`created_by`),
  KEY `fk_colleges_logo` (`logo_media_id`),
  KEY `fk_colleges_header` (`header_media_id`),
  CONSTRAINT `fk_colleges_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`),
  CONSTRAINT `fk_colleges_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_colleges_header` FOREIGN KEY (`header_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_colleges_logo` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colleges`
--

LOCK TABLES `colleges` WRITE;
/*!40000 ALTER TABLE `colleges` DISABLE KEYS */;
INSERT INTO `colleges` VALUES (1,1,1,'College of Engineering','college-of-engineering','COE','Dr. Ana Villanueva','coe@psu.edu.ph','Engineering and technology programs.','active',2,NULL,'2026-11-16 18:01:52','2026-11-16 18:12:33'),(2,1,1,'College of Arts and Sciences','college-of-arts-and-sciences','CAS','Dr. Paolo Mendoza','cas@psu.edu.ph','Liberal arts, natural and social sciences.','active',NULL,NULL,'2026-11-16 18:01:52','2026-11-16 18:12:33');
/*!40000 ALTER TABLE `colleges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_submissions`
--

DROP TABLE IF EXISTS `contact_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `handled_by` int(11) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unread',
  `reply_subject` varchar(255) DEFAULT NULL,
  `reply_message` text DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `replied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`submission_id`),
  KEY `fk_contact_campus` (`campus_id`),
  KEY `fk_contact_department` (`department_id`),
  KEY `fk_contact_handler` (`handled_by`),
  CONSTRAINT `fk_contact_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contact_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contact_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_submissions`
--

LOCK TABLES `contact_submissions` WRITE;
/*!40000 ALTER TABLE `contact_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_attachments`
--

DROP TABLE IF EXISTS `content_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `news_id` int(11) DEFAULT NULL,
  `announcement_id` int(11) DEFAULT NULL,
  `page_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`attachment_id`),
  KEY `fk_ca_document` (`document_id`),
  KEY `fk_ca_news` (`news_id`),
  KEY `fk_ca_announcement` (`announcement_id`),
  KEY `fk_ca_page` (`page_id`),
  CONSTRAINT `fk_ca_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ca_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ca_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`news_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ca_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`page_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_attachments`
--

LOCK TABLES `content_attachments` WRITE;
/*!40000 ALTER TABLE `content_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `college_id` int(11) DEFAULT NULL,
  `campus_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `head_faculty_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_departments_campus_code` (`campus_id`,`code`),
  KEY `fk_departments_college` (`college_id`),
  KEY `fk_departments_creator` (`created_by`),
  KEY `fk_departments_head` (`head_faculty_id`),
  CONSTRAINT `fk_departments_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`),
  CONSTRAINT `fk_departments_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_departments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_departments_head` FOREIGN KEY (`head_faculty_id`) REFERENCES `faculty_staff` (`faculty_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,1,1,1,1,'Information Technology Department','information-technology','IT','Focuses on software development, programming, networking, database management, and emerging technologies.',NULL,'active','2026-11-16 18:01:52','2026-11-21 16:26:07'),(2,2,1,1,2,'Computer Engineering Department','computer-engineering','COE','Focuses on computer hardware, software, embedded systems, networking, and digital technology.',NULL,'active','2026-11-16 18:01:52','2026-11-21 16:26:07');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_categories`
--

DROP TABLE IF EXISTS `document_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_document_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_categories`
--

LOCK TABLES `document_categories` WRITE;
/*!40000 ALTER TABLE `document_categories` DISABLE KEYS */;
INSERT INTO `document_categories` VALUES (1,'Handbooks','Student and employee handbooks'),(2,'Forms','Downloadable forms'),(3,'Reports','Annual and financial reports'),(4,'Calendars','Academic calendars');
/*!40000 ALTER TABLE `document_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_downloads`
--

DROP TABLE IF EXISTS `document_downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_downloads` (
  `download_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `downloaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`download_id`),
  KEY `fk_downloads_document` (`document_id`),
  CONSTRAINT `fk_downloads_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_downloads`
--

LOCK TABLES `document_downloads` WRITE;
/*!40000 ALTER TABLE `document_downloads` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_downloads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_versions` (
  `version_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_size_kb` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version_id`),
  UNIQUE KEY `uq_document_version` (`document_id`,`version_no`),
  KEY `fk_versions_uploader` (`uploaded_by`),
  CONSTRAINT `fk_versions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_versions_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_versions`
--

LOCK TABLES `document_versions` WRITE;
/*!40000 ALTER TABLE `document_versions` DISABLE KEYS */;
INSERT INTO `document_versions` VALUES (1,1,1,1,'enrollment-guide.pdf','/uploads/docs/enrollment-guide.pdf','application/pdf',500,'2026-11-16 18:01:52'),(2,2,2,1,'academic-calendar.pdf','/uploads/docs/academic-calendar.pdf','application/pdf',200,'2026-11-16 18:01:52');
/*!40000 ALTER TABLE `document_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `access_level` varchar(20) NOT NULL DEFAULT 'public',
  `download_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`document_id`),
  KEY `fk_documents_category` (`category_id`),
  KEY `fk_documents_campus` (`campus_id`),
  KEY `fk_documents_creator` (`created_by`),
  CONSTRAINT `fk_documents_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documents_category` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`category_id`),
  CONSTRAINT `fk_documents_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,1,1,'Enrollment Guide','enrollment-guide.pdf','/uploads/docs/enrollment-guide.pdf','application/pdf',512000,NULL,'public',0,'2026-11-16 18:01:52','2026-11-16 18:01:52'),(2,1,1,2,'Academic Calendar','academic-calendar.pdf','/uploads/docs/academic-calendar.pdf','application/pdf',204800,NULL,'public',0,'2026-11-16 18:01:52','2026-11-16 18:01:52');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `email_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(150) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`email_log_id`),
  KEY `fk_email_submission` (`submission_id`),
  CONSTRAINT `fk_email_submission` FOREIGN KEY (`submission_id`) REFERENCES `contact_submissions` (`submission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_categories`
--

DROP TABLE IF EXISTS `event_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `id` int(11) DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_event_categories_id` (`id`),
  UNIQUE KEY `uq_event_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_categories`
--

LOCK TABLES `event_categories` WRITE;
/*!40000 ALTER TABLE `event_categories` DISABLE KEYS */;
INSERT INTO `event_categories` (`category_id`, `id`, `name`, `description`) VALUES (1,1,'Academic','Lectures, seminars and conferences'),(2,2,'Cultural','Arts, festivals, and heritage events'),(3,3,'Sports','Intramurals and athletic meets'),(4,4,'Seminar','Workshops and trainings'),(5,5,'Social','Student social and cultural events');
/*!40000 ALTER TABLE `event_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `cover_media_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `organizer` varchar(150) DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `fk_events_campus` (`campus_id`),
  KEY `fk_events_department` (`department_id`),
  KEY `fk_events_category` (`category_id`),
  KEY `fk_events_cover` (`cover_media_id`),
  KEY `fk_events_creator` (`created_by`),
  CONSTRAINT `fk_events_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`),
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`category_id`),
  CONSTRAINT `fk_events_cover` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_events_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES (1,1,NULL,1,1,1,'Annual Research Symposium','annual-research-symposium','Faculty and students present their latest research.','Main Auditorium','Office of Research','2026-12-05 09:00:00','2026-12-05 17:00:00','2026-11-16 18:01:52','published','2026-11-16 18:01:52','2026-11-16 18:01:52'),(2,1,NULL,5,NULL,2,'Founders Day Festival','founders-day-festival','A day of food, music and games to celebrate the founding of the university.','University Quadrangle','Student Council','2027-01-20 10:00:00','2027-01-20 20:00:00',NULL,'draft','2026-11-16 18:01:52','2026-11-16 18:01:52');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty_staff`
--

DROP TABLE IF EXISTS `faculty_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_staff` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `campus_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `photo_media_id` int(11) DEFAULT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `position` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `educational_background` text DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `uq_faculty_user` (`user_id`),
  KEY `fk_faculty_campus` (`campus_id`),
  KEY `fk_faculty_department` (`department_id`),
  KEY `fk_faculty_photo` (`photo_media_id`),
  CONSTRAINT `fk_faculty_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`),
  CONSTRAINT `fk_faculty_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_faculty_photo` FOREIGN KEY (`photo_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_faculty_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty_staff`
--

LOCK TABLES `faculty_staff` WRITE;
/*!40000 ALTER TABLE `faculty_staff` DISABLE KEYS */;
INSERT INTO `faculty_staff` VALUES (1,NULL,1,1,NULL,'Luis Fernandez',NULL,'Fernandez','Professor and Department Chair','luis.fernandez@university.edu',NULL,NULL,'Specializes in embedded systems and IoT.','active','2026-11-16 18:01:52','2026-11-21 16:34:35'),(2,NULL,1,2,NULL,'Elena Cruz',NULL,'Cruz','Associate Professor','elena.cruz@university.edu',NULL,NULL,'Researches marine biology and conservation.','active','2026-11-16 18:01:52','2026-11-21 16:34:35');
/*!40000 ALTER TABLE `faculty_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `faq_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  PRIMARY KEY (`faq_id`),
  KEY `fk_faqs_campus` (`campus_id`),
  CONSTRAINT `fk_faqs_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faqs`
--

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `featured_items`
--

DROP TABLE IF EXISTS `featured_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `featured_items` (
  `featured_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `news_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`featured_id`),
  KEY `fk_featured_campus` (`campus_id`),
  KEY `fk_featured_news` (`news_id`),
  KEY `fk_featured_event` (`event_id`),
  KEY `fk_featured_creator` (`created_by`),
  CONSTRAINT `fk_featured_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_featured_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_featured_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_featured_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`news_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `featured_items`
--

LOCK TABLES `featured_items` WRITE;
/*!40000 ALTER TABLE `featured_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `featured_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback_submissions`
--

DROP TABLE IF EXISTS `feedback_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback_submissions` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  KEY `fk_feedback_campus` (`campus_id`),
  KEY `fk_feedback_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_feedback_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_feedback_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback_submissions`
--

LOCK TABLES `feedback_submissions` WRITE;
/*!40000 ALTER TABLE `feedback_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `feedback_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_section_items`
--

DROP TABLE IF EXISTS `homepage_section_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepage_section_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `media_id` int(11) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`),
  KEY `fk_items_section` (`section_id`),
  KEY `fk_items_media` (`media_id`),
  CONSTRAINT `fk_items_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_items_section` FOREIGN KEY (`section_id`) REFERENCES `homepage_sections` (`section_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_section_items`
--

LOCK TABLES `homepage_section_items` WRITE;
/*!40000 ALTER TABLE `homepage_section_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `homepage_section_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_sections`
--

DROP TABLE IF EXISTS `homepage_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepage_sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `section_key` varchar(50) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `uq_section_per_campus` (`campus_id`,`section_key`),
  KEY `fk_sections_updater` (`updated_by`),
  CONSTRAINT `fk_sections_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sections_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_sections`
--

LOCK TABLES `homepage_sections` WRITE;
/*!40000 ALTER TABLE `homepage_sections` DISABLE KEYS */;
/*!40000 ALTER TABLE `homepage_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Homepage Management tables used by the Laravel admin API
--

DROP TABLE IF EXISTS `homepage_settings`;
CREATE TABLE `homepage_settings` (
  `setting_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `homepage_settings_setting_key_unique` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `homepage_banners`;
CREATE TABLE `homepage_banners` (
  `banner_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subtitle` text DEFAULT NULL,
  `image_path` varchar(500) NOT NULL,
  `button_label` varchar(100) DEFAULT NULL,
  `button_url` varchar(2048) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`banner_id`),
  KEY `homepage_banners_is_active_sort_order_index` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `homepage_featured_items`;
CREATE TABLE `homepage_featured_items` (
  `featured_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_type` enum('news','announcements','events') NOT NULL,
  `content_id` int(10) unsigned NOT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`featured_item_id`),
  KEY `homepage_featured_content_idx` (`content_type`,`content_id`),
  KEY `homepage_featured_order_idx` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `attempt_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `fk_attempts_user` (`user_id`),
  CONSTRAINT `fk_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media` (
  `media_id` int(11) NOT NULL AUTO_INCREMENT,
  `uploaded_by` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size_kb` int(11) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`media_id`),
  KEY `fk_media_uploader` (`uploaded_by`),
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media`
--

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
INSERT INTO `media` VALUES (1,1,NULL,'graduation-2026.jpg','/uploads/media/graduation-2026.jpg','jpeg','image/jpeg',240,245760,'Graduates tossing caps',NULL,NULL,'2026-11-16 18:01:52'),(2,2,NULL,'engineering-logo.png','/uploads/media/engineering-logo.png','png','image/png',18,18432,'College of Engineering logo',NULL,NULL,'2026-11-16 18:01:52');
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media_usages`
--

DROP TABLE IF EXISTS `media_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media_usages` (
  `usage_id` int(11) NOT NULL AUTO_INCREMENT,
  `media_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  PRIMARY KEY (`usage_id`),
  KEY `fk_usages_media` (`media_id`),
  CONSTRAINT `fk_usages_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`media_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_usages`
--

LOCK TABLES `media_usages` WRITE;
/*!40000 ALTER TABLE `media_usages` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_usages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navigation_items`
--

DROP TABLE IF EXISTS `navigation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `navigation_items` (
  `nav_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `page_id` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `location` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`nav_id`),
  KEY `fk_nav_parent` (`parent_id`),
  KEY `fk_nav_page` (`page_id`),
  KEY `fk_nav_updater` (`updated_by`),
  CONSTRAINT `fk_nav_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`page_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_nav_parent` FOREIGN KEY (`parent_id`) REFERENCES `navigation_items` (`nav_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nav_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `navigation_items`
--

LOCK TABLES `navigation_items` WRITE;
/*!40000 ALTER TABLE `navigation_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `navigation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news` (
  `news_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `cover_media_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `author_name` varchar(150) DEFAULT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`news_id`),
  UNIQUE KEY `uq_news_slug` (`slug`),
  KEY `fk_news_campus` (`campus_id`),
  KEY `fk_news_department` (`department_id`),
  KEY `fk_news_cover` (`cover_media_id`),
  KEY `fk_news_creator` (`created_by`),
  CONSTRAINT `fk_news_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_news_cover` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_news_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_news_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news`
--

LOCK TABLES `news` WRITE;
/*!40000 ALTER TABLE `news` DISABLE KEYS */;
INSERT INTO `news` VALUES (1,1,NULL,1,1,'Class of 2026 Graduation Ceremony','class-of-2026-graduation-ceremony','Maria Santos','Over 1,200 students graduated this year.','The university celebrated the graduation of the Class of 2026 at the Main Campus gymnasium.','2026-06-15 10:00:00',0,'published','2026-11-16 18:01:52','2026-11-16 18:01:52'),(2,2,NULL,NULL,2,'New Research Laboratory Opens','new-research-laboratory-opens','John Reyes','A new lab supports sustainable energy research.','The North Campus opened a state-of-the-art laboratory dedicated to sustainable energy research.',NULL,0,'draft','2026-11-16 18:01:52','2026-11-16 18:01:52');
/*!40000 ALTER TABLE `news` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `news_categories`
--

DROP TABLE IF EXISTS `news_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_news_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news_categories`
--

LOCK TABLES `news_categories` WRITE;
/*!40000 ALTER TABLE `news_categories` DISABLE KEYS */;
INSERT INTO `news_categories` VALUES (1,'Campus Life','campus-life','Stories about student and campus activities'),(2,'Academic',NULL,NULL),(3,'Research',NULL,NULL),(4,'Sports',NULL,NULL);
/*!40000 ALTER TABLE `news_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `news_category_map`
--

DROP TABLE IF EXISTS `news_category_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_category_map` (
  `news_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`news_id`,`category_id`),
  KEY `fk_ncm_category` (`category_id`),
  CONSTRAINT `fk_ncm_category` FOREIGN KEY (`category_id`) REFERENCES `news_categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ncm_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`news_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news_category_map`
--

LOCK TABLES `news_category_map` WRITE;
/*!40000 ALTER TABLE `news_category_map` DISABLE KEYS */;
INSERT INTO `news_category_map` VALUES (1,1),(2,3);
/*!40000 ALTER TABLE `news_category_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'subscribed',
  `subscribed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`subscriber_id`),
  UNIQUE KEY `uq_subscribers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `fk_notifications_user` (`user_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `page_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `parent_page_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`page_id`),
  UNIQUE KEY `uq_pages_slug` (`slug`),
  KEY `fk_pages_campus` (`campus_id`),
  KEY `fk_pages_parent` (`parent_page_id`),
  KEY `fk_pages_creator` (`created_by`),
  CONSTRAINT `fk_pages_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pages_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_page_id`) REFERENCES `pages` (`page_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `uq_reset_token` (`token_hash`),
  KEY `fk_reset_user` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_permissions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_accreditations`
--

DROP TABLE IF EXISTS `program_accreditations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `program_accreditations` (
  `accreditation_id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `accrediting_body` varchar(150) NOT NULL,
  `level` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`accreditation_id`),
  KEY `fk_pa_program` (`program_id`),
  CONSTRAINT `fk_pa_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_accreditations`
--

LOCK TABLES `program_accreditations` WRITE;
/*!40000 ALTER TABLE `program_accreditations` DISABLE KEYS */;
/*!40000 ALTER TABLE `program_accreditations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_campuses`
--

DROP TABLE IF EXISTS `program_campuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `program_campuses` (
  `program_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL,
  PRIMARY KEY (`program_id`,`campus_id`),
  KEY `fk_pc_campus` (`campus_id`),
  CONSTRAINT `fk_pc_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_campuses`
--

LOCK TABLES `program_campuses` WRITE;
/*!40000 ALTER TABLE `program_campuses` DISABLE KEYS */;
/*!40000 ALTER TABLE `program_campuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL AUTO_INCREMENT,
  `college_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `curriculum_document_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `degree_level` varchar(30) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `uq_programs_code` (`code`),
  KEY `fk_programs_college` (`college_id`),
  KEY `fk_programs_creator` (`created_by`),
  KEY `fk_programs_department` (`department_id`),
  KEY `fk_programs_curriculum` (`curriculum_document_id`),
  CONSTRAINT `fk_programs_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`),
  CONSTRAINT `fk_programs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_programs_curriculum` FOREIGN KEY (`curriculum_document_id`) REFERENCES `documents` (`document_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_programs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (1,1,1,1,NULL,'BS in Information Technology','bs-information-technology','BSIT','Four-year program in information technology.','Bachelor','active','2026-11-16 18:01:52','2026-11-16 18:19:16'),(2,2,1,2,NULL,'BS in Computer Engineering','bs-computer-engineering','BSCPE','Four-year program in computer engineering.','Bachelor','active','2026-11-16 18:01:52','2026-11-16 18:19:44');
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Administrator','Full system access','All permissions, settings, security','2026-11-20 19:02:35','2026-11-20 19:02:35'),(2,'Administrator','Manage website content and users','Manage content, users, documents','2026-11-20 19:02:35','2026-11-20 19:02:35'),(3,'Editor','Create, edit, and publish content','Publish and edit all content','2026-11-20 19:02:35','2026-11-20 19:02:35'),(4,'Author','Create and manage own content','Create and edit own drafts','2026-11-20 19:02:35','2026-11-20 19:02:35');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`),
  KEY `fk_settings_updater` (`updated_by`),
  CONSTRAINT `fk_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES ('contact_number',NULL,NULL,'2026-11-20 19:02:35'),('default_language','English',NULL,'2026-11-20 19:02:35'),('facebook_url',NULL,NULL,'2026-11-20 19:02:35'),('footer_layout',NULL,NULL,'2026-11-20 19:02:35'),('header_layout',NULL,NULL,'2026-11-20 19:02:35'),('logo_media_id',NULL,NULL,'2026-11-20 19:02:35'),('official_address',NULL,NULL,'2026-11-20 19:02:35'),('official_email',NULL,NULL,'2026-11-20 19:02:35'),('time_zone','Asia/Manila',NULL,'2026-11-20 19:02:35'),('twitter_url',NULL,NULL,'2026-11-20 19:02:35'),('university_name',NULL,NULL,'2026-11-20 19:02:35'),('website_description',NULL,NULL,'2026-11-20 19:02:35'),('website_name',NULL,NULL,'2026-11-20 19:02:35'),('website_url',NULL,NULL,'2026-11-20 19:02:35');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `themes`
--

DROP TABLE IF EXISTS `themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `themes` (
  `theme_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) DEFAULT NULL,
  `preview_media_id` int(11) DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `primary_color` varchar(7) DEFAULT NULL,
  `secondary_color` varchar(7) DEFAULT NULL,
  `font_family` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`theme_id`),
  KEY `fk_themes_campus` (`campus_id`),
  KEY `fk_themes_preview` (`preview_media_id`),
  CONSTRAINT `fk_themes_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_themes_preview` FOREIGN KEY (`preview_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `themes`
--

LOCK TABLES `themes` WRITE;
/*!40000 ALTER TABLE `themes` DISABLE KEYS */;
INSERT INTO `themes` VALUES (1,NULL,NULL,'PSU Classic','#1E4FA3','#F5B700','Inter',1),(2,NULL,NULL,'Modern Light',NULL,NULL,'Inter',0),(3,NULL,NULL,'Campus Dark',NULL,NULL,'Inter',0);
/*!40000 ALTER TABLE `themes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `is_remembered` tinyint(1) NOT NULL DEFAULT 0,
  `last_activity_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `uq_user_sessions_token` (`token_hash`),
  KEY `fk_sessions_user` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `campus_id` int(11) NOT NULL,
  `college_id` int(11) DEFAULT NULL,
  `avatar_media_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `failed_login_count` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `fk_users_role` (`role_id`),
  KEY `fk_users_campus` (`campus_id`),
  KEY `fk_users_college` (`college_id`),
  KEY `fk_users_avatar` (`avatar_media_id`),
  CONSTRAINT `fk_users_avatar` FOREIGN KEY (`avatar_media_id`) REFERENCES `media` (`media_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`campus_id`),
  CONSTRAINT `fk_users_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,2,'admin',1,NULL,NULL,'mariasantos','Maria','Santos','Maria Santos','maria.santos@university.edu','$2b$12$nMP0ASAzDUdPAQbnPSS8tu2pHMkwrewKs0plNLcWM1VDgphjb5FAy','active',0,NULL,NULL,NULL,0,NULL,0,'2026-11-16 18:01:52','2026-11-16 18:20:43'),(2,3,'editor',1,NULL,NULL,'johnreyes','John','Reyes','John Reyes','john.reyes@university.edu','$2b$12$TgTYpcxmVQSmNXQDWHaq/.IanuriVaWZEyIMpMIh8iblMT/E9v8OW','active',0,NULL,NULL,NULL,0,NULL,0,'2026-11-16 18:01:52','2026-11-16 18:21:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'psu_cms_admin'
--

--
-- Dumping routines for database 'psu_cms_admin'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-21 16:38:15
