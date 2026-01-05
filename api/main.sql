-- Database Name: main
-- CREATE DATABASE `main`;
-- USE `main`;

-- ==========================================
-- 1. SHARED TABLES
-- ==========================================

-- Users Table (Merged from Mail and Community, plus Verification fields)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE, -- From Community
    email VARCHAR(255) NOT NULL UNIQUE, -- From Mail & Community
    password VARCHAR(255) NOT NULL, -- From Mail (hash_password) & Community
    first_name VARCHAR(100), -- From Mail (name)
    last_name VARCHAR(100), -- From Mail (surname)
    role VARCHAR(50) DEFAULT 'user', -- From Both
    profile_path VARCHAR(255) DEFAULT NULL, -- Profile picture path
    verification_code VARCHAR(10), -- Added for Auth
    is_verified TINYINT DEFAULT 0, -- Added for Auth
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 2. MAIL COMPONENT TABLES
-- ==========================================

-- Messages Table (Renamed to mail_messages)
CREATE TABLE mail_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255), -- object
    body TEXT,
    created_at DATETIME, -- Merged date and time
    parent_id INT DEFAULT 0, -- parent
    sender_id INT, -- sid
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Recipients Table (Renamed to mail_recipients from received)
CREATE TABLE mail_recipients (
    user_id INT, -- rid
    message_id INT, -- mid
    status VARCHAR(50), -- 'to', 'cc', 'bcc'
    PRIMARY KEY (user_id, message_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES mail_messages(id) ON DELETE CASCADE
);

-- Labels Table (Renamed to mail_labels)
CREATE TABLE mail_labels (
    user_id INT, -- uid
    message_id INT, -- mid
    is_starred BOOLEAN DEFAULT FALSE, -- stared
    is_spam BOOLEAN DEFAULT FALSE, -- spam
    is_trash BOOLEAN DEFAULT FALSE, -- trash
    is_archived BOOLEAN DEFAULT FALSE, -- archived
    is_read BOOLEAN DEFAULT FALSE, -- Is_read
    PRIMARY KEY (user_id, message_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES mail_messages(id) ON DELETE CASCADE
);

-- Attachments Table (Renamed to mail_attachments)
CREATE TABLE mail_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Fid
    file_name VARCHAR(255),
    file_type VARCHAR(50),
    file_size BIGINT, -- file_size_byte
    file_path TEXT, -- link
    message_id INT, -- mid
    FOREIGN KEY (message_id) REFERENCES mail_messages(id) ON DELETE CASCADE
);

-- ==========================================
-- 3. COMMUNITY COMPONENT TABLES
-- ==========================================

-- Messages Table (Renamed to community_messages)
CREATE TABLE community_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT,
    type VARCHAR(20) DEFAULT 'text',
    media_url VARCHAR(255) DEFAULT NULL,
    reply_to_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_saved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES community_messages(id) ON DELETE SET NULL
);

-- Comments Table (Renamed to community_comments)
CREATE TABLE community_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Archived Messages Table (Renamed to community_archived_messages)
CREATE TABLE community_archived_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_message (user_id, message_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE
);

-- Notifications Table (Renamed to community_notifications)
CREATE TABLE community_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    actor_id INT NOT NULL,
    message_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'comment',
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE
);

-- ==========================================
-- 4. ARCHIVE COMPONENT TABLES
-- ==========================================

CREATE TABLE archive_semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE archive_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    semester_id INT,
    FOREIGN KEY (semester_id) REFERENCES archive_semesters(id) ON DELETE CASCADE
);

CREATE TABLE archive_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50), -- 'cours', 'td', 'tp', 'devoir', 'examen', 'rattrapage', 'examen_pratique'
    file_path VARCHAR(255),
    subject_id INT,
    FOREIGN KEY (subject_id) REFERENCES archive_subjects(id) ON DELETE CASCADE
);

-- ==========================================
-- 5. DATA INSERTION (Optional Samples)
-- ==========================================
-- (You can append the INSERT statements from your source file here if needed)
