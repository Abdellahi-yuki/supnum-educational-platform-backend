<?php
// test_backend_post.php

$url = 'http://localhost:8000/api/messages';
$data = [
    'user_id' => 2, // Assuming user 2 exists from previous dumps (24212)
    'content' => 'Automated Backend Test ' . date('H:i:s'),
    'file' => null
];

// Use curl for POST
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Send as multipart/form-data for the file handling logic
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 200) {
    echo "TEST PASSED: Message created.\n";
} else {
    echo "TEST FAILED.\n";
}
