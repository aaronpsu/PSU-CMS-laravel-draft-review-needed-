-- Moves CMS seed/content data from cms_db_2 (prototype) to psu_cms_admin (real schema).
-- Homepage and dashboard tables are intentionally not included.
-- Run only after importing both SQL dumps on the same MySQL/MariaDB server.

SET FOREIGN_KEY_CHECKS = 0;

-- Compatibility fields used by the existing admin UI.
ALTER TABLE psu_cms_admin.users
  ADD COLUMN IF NOT EXISTS full_name VARCHAR(150) NULL AFTER last_name,
  ADD COLUMN IF NOT EXISTS role VARCHAR(50) NULL AFTER role_id;

ALTER TABLE psu_cms_admin.campuses
  ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER is_main;

ALTER TABLE psu_cms_admin.colleges
  ADD COLUMN IF NOT EXISTS slug VARCHAR(200) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER dean_name,
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER dean_name,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER header_media_id;

ALTER TABLE psu_cms_admin.departments
  ADD COLUMN IF NOT EXISTS slug VARCHAR(200) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER type;

ALTER TABLE psu_cms_admin.programs
  ADD COLUMN IF NOT EXISTS slug VARCHAR(200) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER degree_level;

ALTER TABLE psu_cms_admin.news_categories
  ADD COLUMN IF NOT EXISTS slug VARCHAR(150) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER slug;

ALTER TABLE psu_cms_admin.announcement_categories
  ADD COLUMN IF NOT EXISTS slug VARCHAR(150) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER slug;

ALTER TABLE psu_cms_admin.event_categories
  ADD COLUMN IF NOT EXISTS id INT NULL AFTER category_id;

ALTER TABLE psu_cms_admin.media
  ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) NULL AFTER file_type,
  ADD COLUMN IF NOT EXISTS file_size BIGINT UNSIGNED NULL AFTER file_size_kb;

ALTER TABLE psu_cms_admin.documents
  ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) NULL AFTER file_name,
  ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) NULL AFTER file_path,
  ADD COLUMN IF NOT EXISTS file_size BIGINT UNSIGNED NULL AFTER mime_type;

ALTER TABLE psu_cms_admin.announcements
  ADD COLUMN IF NOT EXISTS attachment_document_id INT NULL AFTER expires_at,
  ADD COLUMN IF NOT EXISTS author_name VARCHAR(150) NULL AFTER title;

-- 1. Users
INSERT INTO psu_cms_admin.users
  (user_id, role_id, campus_id, college_id, avatar_media_id, username, first_name, last_name, email, password_hash, status,
   full_name, role, created_at, updated_at)
SELECT s.user_id,
       CASE WHEN s.role = 'admin' THEN 2 ELSE 3 END,
       1, NULL, NULL,
       LOWER(REPLACE(SUBSTRING_INDEX(s.email, '@', 1), '.', '')),
       SUBSTRING_INDEX(s.full_name, ' ', 1),
       SUBSTRING_INDEX(s.full_name, ' ', -1),
       s.email, s.password_hash, s.status,
       s.full_name, s.role, s.created_at, s.updated_at
FROM cms_db_2.users s
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  first_name = VALUES(first_name),
  last_name = VALUES(last_name),
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  status = VALUES(status),
  full_name = VALUES(full_name),
  role = VALUES(role),
  updated_at = VALUES(updated_at);

-- 2. Campuses
INSERT INTO psu_cms_admin.campuses
  (campus_id, code, name, address, is_main, status, created_at, updated_at)
SELECT s.campus_id, s.code, s.name, s.address, CASE WHEN s.campus_id = 1 THEN 1 ELSE 0 END,
       s.status, s.created_at, s.updated_at
FROM cms_db_2.campuses s
ON DUPLICATE KEY UPDATE
  code = VALUES(code),
  name = VALUES(name),
  address = VALUES(address),
  is_main = VALUES(is_main),
  status = VALUES(status),
  updated_at = VALUES(updated_at);

-- 3. Media
INSERT INTO psu_cms_admin.media
  (media_id, uploaded_by, title, file_name, file_path, file_type, file_size_kb, mime_type, file_size, alt_text, created_at)
SELECT s.media_id, s.uploaded_by, NULL, s.file_name, s.file_path,
       LOWER(SUBSTRING_INDEX(s.mime_type, '/', -1)), CEILING(s.file_size / 1024),
       s.mime_type, s.file_size, s.alt_text, s.created_at
FROM cms_db_2.media s
ON DUPLICATE KEY UPDATE
  uploaded_by = VALUES(uploaded_by),
  file_name = VALUES(file_name),
  file_path = VALUES(file_path),
  file_type = VALUES(file_type),
  file_size_kb = VALUES(file_size_kb),
  mime_type = VALUES(mime_type),
  file_size = VALUES(file_size),
  alt_text = VALUES(alt_text);

-- 4. Colleges
INSERT INTO psu_cms_admin.colleges
  (college_id, campus_id, created_by, name, slug, code, description, dean_name, email, status,
   logo_media_id, header_media_id, created_at, updated_at)
SELECT s.college_id, 1, COALESCE(s.created_by, 1), s.name, s.slug, s.code, s.description,
       s.dean_name, s.email, s.status, s.logo_media_id, NULL, s.created_at, s.updated_at
FROM cms_db_2.colleges s
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  slug = VALUES(slug),
  code = VALUES(code),
  description = VALUES(description),
  dean_name = VALUES(dean_name),
  email = VALUES(email),
  status = VALUES(status),
  logo_media_id = VALUES(logo_media_id),
  updated_at = VALUES(updated_at);

-- 5. Departments (head is linked after faculty migration)
INSERT INTO psu_cms_admin.departments
  (department_id, college_id, campus_id, created_by, head_faculty_id, name, slug, code, description,
   type, status, created_at, updated_at)
SELECT s.department_id, s.college_id, 1, COALESCE(s.created_by, 1), NULL,
       s.name, s.slug, s.code, s.description, NULL, s.status, s.created_at, s.updated_at
FROM cms_db_2.departments s
ON DUPLICATE KEY UPDATE
  college_id = VALUES(college_id),
  campus_id = VALUES(campus_id),
  name = VALUES(name),
  slug = VALUES(slug),
  code = VALUES(code),
  description = VALUES(description),
  status = VALUES(status),
  updated_at = VALUES(updated_at);

-- 6. Faculty
INSERT INTO psu_cms_admin.faculty_staff
  (faculty_id, user_id, campus_id, department_id, photo_media_id, first_name, middle_name, last_name,
   position, email, contact_number, educational_background, biography, status, created_at, updated_at)
SELECT s.faculty_id, NULL, 1, s.department_id, NULL,
       SUBSTRING_INDEX(s.name, ' ', 1),
       CASE WHEN LENGTH(s.name) - LENGTH(REPLACE(s.name, ' ', '')) >= 2
            THEN TRIM(SUBSTRING(s.name,
                 LENGTH(SUBSTRING_INDEX(s.name, ' ', 1)) + 2,
                 LENGTH(s.name) - LENGTH(SUBSTRING_INDEX(s.name, ' ', 1))
                               - LENGTH(SUBSTRING_INDEX(s.name, ' ', -1)) - 1))
            ELSE NULL END,
       SUBSTRING_INDEX(s.name, ' ', -1),
       s.position, s.email, NULL, NULL, s.summary, 'active', s.created_at, s.updated_at
FROM cms_db_2.faculty s
ON DUPLICATE KEY UPDATE
  campus_id = VALUES(campus_id),
  department_id = VALUES(department_id),
  first_name = VALUES(first_name),
  middle_name = VALUES(middle_name),
  last_name = VALUES(last_name),
  position = VALUES(position),
  email = VALUES(email),
  biography = VALUES(biography),
  updated_at = VALUES(updated_at);

UPDATE psu_cms_admin.departments d
JOIN cms_db_2.departments s ON s.department_id = d.department_id
SET d.head_faculty_id = s.head_faculty_id;

-- 7. Programs
INSERT INTO psu_cms_admin.programs
  (program_id, college_id, created_by, department_id, curriculum_document_id, name, slug, code,
   description, degree_level, status, created_at, updated_at)
SELECT s.program_id, s.college_id, COALESCE(s.created_by, 1), s.department_id, NULL,
       s.name, s.slug, s.code, s.description, s.degree_level, s.status, s.created_at, s.updated_at
FROM cms_db_2.programs s
ON DUPLICATE KEY UPDATE
  college_id = VALUES(college_id),
  department_id = VALUES(department_id),
  name = VALUES(name),
  slug = VALUES(slug),
  code = VALUES(code),
  description = VALUES(description),
  degree_level = VALUES(degree_level),
  status = VALUES(status),
  updated_at = VALUES(updated_at);

-- 8. Category data
UPDATE psu_cms_admin.news_categories ac
JOIN cms_db_2.news_categories s ON s.category_id = 1
SET ac.name = s.name, ac.slug = s.slug, ac.description = s.description
WHERE ac.category_id = 1;

INSERT INTO psu_cms_admin.announcement_categories
  (category_id, name, slug, description)
SELECT 5, s.name, s.slug, s.description
FROM cms_db_2.announcement_categories s
WHERE s.category_id = 2
ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), description = VALUES(description);

UPDATE psu_cms_admin.announcement_categories ac
JOIN cms_db_2.announcement_categories s ON s.category_id = 1
SET ac.name = s.name, ac.slug = s.slug, ac.description = s.description
WHERE ac.category_id = 1;

INSERT INTO psu_cms_admin.event_categories
  (category_id, id, name, description)
VALUES
  (5, 5, 'Social', 'Student social and cultural events')
ON DUPLICATE KEY UPDATE id = VALUES(id), name = VALUES(name), description = VALUES(description);

UPDATE psu_cms_admin.event_categories ec
JOIN cms_db_2.event_categories s ON s.id = 1
SET ec.id = 1, ec.name = s.name, ec.description = s.description
WHERE ec.category_id = 1;

-- 9. Documents and versions
INSERT INTO psu_cms_admin.documents
  (document_id, category_id, campus_id, created_by, title, description, access_level, download_count,
   file_name, file_path, mime_type, file_size, created_at, updated_at)
SELECT s.document_id, 1, 1, COALESCE(s.uploaded_by, 1), s.title, NULL, 'public', 0,
       s.file_name, s.file_path, s.mime_type, s.file_size, s.created_at, s.created_at
FROM cms_db_2.documents s
ON DUPLICATE KEY UPDATE
  category_id = VALUES(category_id),
  campus_id = VALUES(campus_id),
  title = VALUES(title),
  file_name = VALUES(file_name),
  file_path = VALUES(file_path),
  mime_type = VALUES(mime_type),
  file_size = VALUES(file_size),
  updated_at = VALUES(updated_at);

INSERT INTO psu_cms_admin.document_versions
  (document_id, uploaded_by, version_no, file_name, file_path, file_type, file_size_kb, created_at)
SELECT s.document_id, COALESCE(s.uploaded_by, 1), 1, s.file_name, s.file_path,
       s.mime_type, CEILING(s.file_size / 1024), s.created_at
FROM cms_db_2.documents s
ON DUPLICATE KEY UPDATE
  uploaded_by = VALUES(uploaded_by),
  file_name = VALUES(file_name),
  file_path = VALUES(file_path),
  file_type = VALUES(file_type),
  file_size_kb = VALUES(file_size_kb);

-- 10. News
INSERT INTO psu_cms_admin.news
  (news_id, campus_id, department_id, cover_media_id, created_by, title, slug, author_name, excerpt, content,
   published_at, view_count, status, created_at, updated_at)
SELECT s.news_id, s.campus_id, NULL, s.cover_media_id, COALESCE(s.created_by, 1),
       s.title, s.slug, s.author_name, s.summary, s.content, s.published_at, 0,
       s.status, s.created_at, s.updated_at
FROM cms_db_2.news s
ON DUPLICATE KEY UPDATE
  campus_id = VALUES(campus_id),
  cover_media_id = VALUES(cover_media_id),
  title = VALUES(title),
  slug = VALUES(slug),
  author_name = VALUES(author_name),
  excerpt = VALUES(excerpt),
  content = VALUES(content),
  published_at = VALUES(published_at),
  status = VALUES(status),
  updated_at = VALUES(updated_at);

INSERT INTO psu_cms_admin.news_category_map (news_id, category_id)
SELECT s.news_id, CASE WHEN s.category_id = 1 THEN 1 ELSE 3 END
FROM cms_db_2.news_category_map s
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id);

-- 11. Announcements
INSERT INTO psu_cms_admin.announcements
  (announcement_id, campus_id, department_id, category_id, created_by, title, author_name, content, priority,
   published_at, expires_at, attachment_document_id, status, created_at, updated_at)
SELECT s.announcement_id, s.campus_id, NULL,
       CASE WHEN m.category_id = 1 THEN 1 ELSE 5 END,
       COALESCE(s.created_by, 1), s.title,
       u.full_name, s.content, s.priority, s.published_at, s.expires_at,
       s.attachment_document_id, s.status, s.created_at, s.updated_at
FROM cms_db_2.announcements s
LEFT JOIN cms_db_2.announcement_category_map m ON m.announcement_id = s.announcement_id
LEFT JOIN cms_db_2.users u ON u.user_id = s.created_by
ON DUPLICATE KEY UPDATE
  campus_id = VALUES(campus_id),
  category_id = VALUES(category_id),
  title = VALUES(title),
  author_name = VALUES(author_name),
  content = VALUES(content),
  priority = VALUES(priority),
  published_at = VALUES(published_at),
  expires_at = VALUES(expires_at),
  attachment_document_id = VALUES(attachment_document_id),
  status = VALUES(status),
  updated_at = VALUES(updated_at);

-- 12. Events
INSERT INTO psu_cms_admin.events
  (event_id, campus_id, department_id, category_id, cover_media_id, created_by, title, slug, description,
   venue, organizer, start_at, end_at, published_at, status, created_at, updated_at)
SELECT s.event_id, 1, NULL, CASE WHEN s.category_id = 1 THEN 1 ELSE 5 END,
       s.cover_media_id, COALESCE(s.created_by, 1), s.title, s.slug, s.description,
       s.venue, s.organizer, s.start_at, s.end_at,
       CASE WHEN s.status = 'published' THEN s.created_at ELSE NULL END,
       s.status, s.created_at, s.updated_at
FROM cms_db_2.events s
ON DUPLICATE KEY UPDATE
  campus_id = VALUES(campus_id),
  category_id = VALUES(category_id),
  cover_media_id = VALUES(cover_media_id),
  title = VALUES(title),
  slug = VALUES(slug),
  description = VALUES(description),
  venue = VALUES(venue),
  organizer = VALUES(organizer),
  start_at = VALUES(start_at),
  end_at = VALUES(end_at),
  published_at = VALUES(published_at),
  status = VALUES(status),
  updated_at = VALUES(updated_at);

SET FOREIGN_KEY_CHECKS = 1;
