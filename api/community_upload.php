<?php
include_once 'db.php';

// Increase limits for this script execution
ini_set('upload_max_filesize', '100M'); 
ini_set('post_max_size', '100M');
ini_set('max_execution_time', 600);

$uploadId = $_POST['upload_id'] ?? null;
$chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : null;
$totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : null;
$fileName = $_POST['file_name'] ?? null;
$file = $_FILES['chunk'] ?? null;

if (!$uploadId || $chunkIndex === null || !$totalChunks || !$fileName || !$file) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing upload parameters',
        'debug' => [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'file_name' => $fileName,
            'file_received' => !!$file
        ]
    ]);
    exit;
}

$tempDir = __DIR__ . '/../uploads/community/temp';
if (!file_exists($tempDir)) {
    if (!mkdir($tempDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create temp directory', 'path' => $tempDir]);
        exit;
    }
}

// Sanitize uploadId
$uploadId = preg_replace('/[^a-zA-Z0-9]/', '', $uploadId);
$tempFilePath = $tempDir . '/' . $uploadId . '.part';

$chunkData = file_get_contents($file['tmp_name']);
if ($chunkData === false) {
     http_response_code(500);
     echo json_encode(['error' => 'Failed to read chunk from ' . $file['tmp_name']]);
     exit;
}

$mode = ($chunkIndex === 0) ? 'wb' : 'ab';
$handle = fopen($tempFilePath, $mode);
if (!$handle) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not open temp file for writing', 'path' => $tempFilePath, 'mode' => $mode]);
    exit;
}
fwrite($handle, $chunkData);
fclose($handle);

// Check if complete
if ($chunkIndex + 1 >= $totalChunks) {
    $finalDir = __DIR__ . '/../uploads/community';
    if (!file_exists($finalDir)) {
        if (!mkdir($finalDir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create uploads directory', 'path' => $finalDir]);
            exit;
        }
    }
    
    $safeFileName = time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $finalPath = $finalDir . '/' . $safeFileName;

    if (rename($tempFilePath, $finalPath)) {
        $mediaUrl = '/uploads/community/' . $safeFileName;
        
        $mime = mime_content_type($finalPath);
        $type = 'file';
        if (strpos($mime, 'image/') === 0) $type = 'image';
        if (strpos($mime, 'video/') === 0) $type = 'video';

        echo json_encode(['status' => 'done', 'media_url' => $mediaUrl, 'type' => $type]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to rename finalized file',
            'from' => $tempFilePath,
            'to' => $finalPath
        ]);
    }
} else {
    echo json_encode([
        'status' => 'chunk_uploaded', 
        'percent' => round((($chunkIndex + 1) / $totalChunks) * 100),
        'chunk_index' => $chunkIndex,
        'total_chunks' => $totalChunks
    ]);
}
?>
