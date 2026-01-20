<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once 'db.php';

try {
    // Fetch semesters
    $stmt = $conn->query("SELECT id, name as nom FROM archive_semesters ORDER BY id ASC");
    $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($semesters as &$sem) {
        // Fetch subjects for this semester
        $subStmt = $conn->prepare("SELECT id, name as nom, semester_id as id_semestre FROM archive_subjects WHERE semester_id = ?");
        $subStmt->execute([$sem['id']]);
        $subjects = $subStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjects as &$sub) {
            // Fetch materials for this subject
            $matStmt = $conn->prepare("SELECT id, title as nom, category as type, file_path as chemin_fichier, subject_id as id_matiere FROM archive_materials WHERE subject_id = ? ORDER BY uploaded_at DESC");
            $matStmt->execute([$sub['id']]);
            $sub['supports'] = $matStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $sem['matieres'] = $subjects;
    }

    // Prepare the exact structure Archive.jsx expects or just the hierarchy
    // Archive.jsx currently expects { semestres: [], matieres: [], supports: [] }
    // Let's flatten it for easier migration of Archive.jsx
    
    $finalData = [
        "semestres" => [],
        "matieres" => [],
        "supports" => []
    ];

    foreach ($semesters as $sem) {
        $finalData['semestres'][] = ["id" => (int)$sem['id'], "nom" => $sem['nom']];
        foreach ($sem['matieres'] as $sub) {
            $finalData['matieres'][] = ["id" => (int)$sub['id'], "nom" => $sub['nom'], "id_semestre" => (int)$sub['id_semestre']];
            foreach ($sub['supports'] as $sup) {
                // Prepend base URL for file path if needed
                $finalData['supports'][] = [
                    "id" => (int)$sup['id'],
                    "nom" => $sup['nom'],
                    "type" => $sup['type'],
                    "chemin_fichier" => $sup['chemin_fichier'],
                    "id_matiere" => (int)$sup['id_matiere']
                ];
            }
        }
    }

    echo json_encode($finalData);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
