<?php
require_once 'db.php';

// get_results.php
// Returns student results for a specific matricule and semester

header('Content-Type: application/json');

$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : (isset($_POST['matricule']) ? $_POST['matricule'] : '');
$semester = isset($_GET['semester']) ? intval($_GET['semester']) : (isset($_POST['semester']) ? intval($_POST['semester']) : 0);

if (!$matricule || !$semester) {
    echo json_encode(["status" => "error", "message" => "Missing matricule or semester"]);
    exit;
}

$table = "";
switch ($semester) {
    case 1: $table = "notes_s1_r"; break;
    case 2: $table = "notes_s2_r"; break;
    case 3: $table = "notes_s3"; break;
    case 4: $table = "notes_s4"; break;
    default:
        echo json_encode(["status" => "error", "message" => "Invalid semester"]);
        exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM $table WHERE Matricule = ?");
    $stmt->execute([$matricule]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(["status" => "error", "message" => "Student not found for this semester"]);
        exit;
    }

    // Fetch subject names and credits for mapping
    $subjects_stmt = $conn->prepare("SELECT code, name, credits FROM archive_subjects WHERE semester_id = ?");
    $subjects_stmt->execute([$semester]);
    $subjects_data = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subjects_map = [];
    foreach ($subjects_data as $row) {
        $subjects_map[$row['code']] = [
            'name' => $row['name'],
            'credits' => $row['credits']
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $result,
        "subjects_map" => $subjects_map,
        "metadata" => [
            "semester" => $semester,
            "matricule" => $matricule
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
