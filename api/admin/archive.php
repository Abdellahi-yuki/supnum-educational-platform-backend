<?php
include_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Check if we want stats or list
    if (isset($_GET['action']) && $_GET['action'] === 'stats') {
        try {
            $stats = [];
            
            $stmt = $conn->query("SELECT COUNT(*) FROM archive_semesters");
            $stats['semesters'] = $stmt->fetchColumn();
            
            $stmt = $conn->query("SELECT COUNT(*) FROM archive_subjects");
            $stats['subjects'] = $stmt->fetchColumn();
            
            $stmt = $conn->query("SELECT COUNT(*) FROM archive_materials");
            $stats['documents'] = $stmt->fetchColumn();
            
            echo json_encode($stats);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        // Full Hierarchy: Semesters -> Subjects -> Files
        try {
            // Check if tables exist first to avoid 500 if migration failed
            // Actually, best to just try-catch
            
            $stmt = $conn->query("SELECT * FROM archive_semesters ORDER BY name");
            $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($semesters as &$sem) {
                $stmt = $conn->prepare("SELECT * FROM archive_subjects WHERE semester_id = ? ORDER BY name");
                $stmt->execute([$sem['id']]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($subjects as &$sub) {
                    $fStmt = $conn->prepare("SELECT * FROM archive_materials WHERE subject_id = ? ORDER BY uploaded_at DESC");
                    try {
                        $fStmt->execute([$sub['id']]);
                        $sub['files'] = $fStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                         $sub['files'] = [];
                    }
                }
                $sem['subjects'] = $subjects;
            }
            
            echo json_encode($semesters);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
} elseif ($method === 'POST') {
    // Determine action
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_semester') {
        $name = $_POST['name'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO archive_semesters (name) VALUES (?)");
            $stmt->execute([$name]);
            echo json_encode(["status" => "success", "message" => "Semester created", "id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } elseif ($action === 'create_subject') {
        $semester_id = $_POST['semester_id'];
        $name = $_POST['name'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO archive_subjects (semester_id, name) VALUES (?, ?)");
            $stmt->execute([$semester_id, $name]);
            echo json_encode(["status" => "success", "message" => "Subject created", "id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } elseif ($action === 'upload_file') {
        $subject_id = $_POST['subject_id'];
        $title = $_POST['title'];
        $category = $_POST['category'] ?? 'cours';
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/archive/'; 
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $originalName = $_FILES['file']['name'];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $filename = uniqid('doc_') . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                $dbPath = '/uploads/archive/' . $filename; 
                
                try {
                    $stmt = $conn->prepare("INSERT INTO archive_materials (subject_id, title, file_path, file_type, category) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$subject_id, $title, $dbPath, $ext, $category]);
                    echo json_encode(["status" => "success", "message" => "File uploaded", "id" => $conn->lastInsertId()]);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                }
            } else {
                 http_response_code(500);
                 echo json_encode(["status" => "error", "message" => "Failed to move uploaded file"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "No file uploaded or upload error: " . ($_FILES['file']['error'] ?? 'unknown')]);
        }
    }
} elseif ($method === 'DELETE') {
    $type = $_GET['type'] ?? '';
    $id = $_GET['id'] ?? '';
    
    try {
        if ($type === 'semester') {
            $stmt = $conn->prepare("DELETE FROM archive_semesters WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($type === 'subject') {
            $stmt = $conn->prepare("DELETE FROM archive_subjects WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($type === 'file') {
            $stmt = $conn->prepare("SELECT file_path FROM archive_materials WHERE id = ?");
            $stmt->execute([$id]);
            $path = $stmt->fetchColumn();
            
            if ($path) {
                $fullPath = '../..' . $path;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            $delStmt = $conn->prepare("DELETE FROM archive_materials WHERE id = ?");
            $delStmt->execute([$id]);
        }
        echo json_encode(["status" => "success", "message" => "Item deleted"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
