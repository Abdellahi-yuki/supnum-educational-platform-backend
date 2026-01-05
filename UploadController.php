<?php
// backend_php/UploadController.php

require_once 'db.php';

class UploadController {
    public function uploadChunk() {
        // Increase limits for this script execution
        ini_set('upload_max_filesize', '50M'); // For individual chunks
        ini_set('post_max_size', '50M');
        ini_set('max_execution_time', 300);

        $uploadId = $_POST['upload_id'] ?? null;
        $chunkIndex = $_POST['chunk_index'] ?? null;
        $totalChunks = $_POST['total_chunks'] ?? null;
        $fileName = $_POST['file_name'] ?? null;
        $file = $_FILES['chunk'] ?? null;

        if (!$uploadId || $chunkIndex === null || !$totalChunks || !$fileName || !$file) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing upload parameters']);
            return;
        }

        $tempDir = __DIR__ . '/uploads/temp';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Sanitize uploadId to be safe (alphanumeric only)
        $uploadId = preg_replace('/[^a-zA-Z0-9]/', '', $uploadId);
        $tempFilePath = $tempDir . '/' . $uploadId . '.part';

        // Read chunk data
        $chunkData = file_get_contents($file['tmp_name']);
        if ($chunkData === false) {
             http_response_code(500);
             echo json_encode(['error' => 'Failed to read chunk']);
             return;
        }

        // Append to temp file
        // If it's the first chunk, opening with 'ab' creates it. 
        // Note: Chunks must be sent sequentially by the frontend for this to work simply.
        // A more robust way allows parallel chunks by writing to offsets, but simple append 'FILE_APPEND' matches sequential logic.
        
        $mode = ($chunkIndex == 0) ? 'wb' : 'ab';
        $handle = fopen($tempFilePath, $mode);
        if (!$handle) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not open temp file']);
            return;
        }
        fwrite($handle, $chunkData);
        fclose($handle);

        // Check if complete
        if ($chunkIndex + 1 == $totalChunks) {
            // Finalize
            $finalDir = __DIR__ . '/uploads';
            
            // Clean filename
            $safeFileName = time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            $finalPath = $finalDir . '/' . $safeFileName;

            if (rename($tempFilePath, $finalPath)) {
                $mediaUrl = '/uploads/' . $safeFileName;
                
                // Determine mime type
                $mime = mime_content_type($finalPath);
                $type = 'file';
                if (strpos($mime, 'image/') === 0) $type = 'image';
                if (strpos($mime, 'video/') === 0) $type = 'video';

                echo json_encode(['status' => 'done', 'media_url' => $mediaUrl, 'type' => $type]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to rename finalized file']);
            }
        } else {
            echo json_encode(['status' => 'chunk_uploaded', 'percent' => round((($chunkIndex + 1) / $totalChunks) * 100)]);
        }
    }
}
