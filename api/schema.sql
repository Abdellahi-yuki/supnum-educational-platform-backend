-- Database Name: main.db
-- You may need to run: CREATE DATABASE `main.db`;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    verification_code VARCHAR(10),
    is_verified TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mail_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255),
    body TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    parent_id INT DEFAULT 0,
    sender_id INT,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);
