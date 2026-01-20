<?php
include_once 'api/db.php';

try {
    // 1. Update Users Table for Advanced Banning
    $conn->exec("ALTER TABLE users ADD COLUMN ban_expires_at DATETIME NULL DEFAULT NULL");
    $conn->exec("ALTER TABLE users ADD COLUMN ban_reason TEXT NULL");
    echo "Added ban_expires_at and ban_reason to users table.\n";

    // 2. Ensure Archive Tables Exist
    // Semesters
    $conn->exec("CREATE TABLE IF NOT EXISTS archive_semesters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    )");

    // Populate default semesters if empty
    $stmt = $conn->query("SELECT COUNT(*) FROM archive_semesters");
    if ($stmt->fetchColumn() == 0) {
        $semesters = ['L1 Semestre 1', 'L1 Semestre 2', 'L2 Semestre 3', 'L2 Semestre 4', 'L3 Semestre 5', 'L3 Semestre 6'];
        $ins = $conn->prepare("INSERT INTO archive_semesters (name) VALUES (?)");
        foreach ($semesters as $sem) {
            $ins->execute([$sem]);
        }
        echo "Populated default semesters.\n";
    }

    // Subjects
    $conn->exec("CREATE TABLE IF NOT EXISTS archive_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        semester_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20),
        FOREIGN KEY (semester_id) REFERENCES archive_semesters(id) ON DELETE CASCADE
    )");

    // Materials (PDFs)
    $conn->exec("CREATE TABLE IF NOT EXISTS archive_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) DEFAULT 'pdf',
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subject_id) REFERENCES archive_subjects(id) ON DELETE CASCADE
    )");

    echo "Archive tables checked/created.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
