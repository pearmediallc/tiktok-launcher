<?php
// Test script to directly call TikTok video info API

// Load environment
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$advertiser_id = $_ENV['TIKTOK_ADVERTISER_ID'] ?? '';
$access_token = $_ENV['TIKTOK_ACCESS_TOKEN'] ?? '';

if (!$advertiser_id || !$access_token) {
    die("Missing TIKTOK_ADVERTISER_ID or TIKTOK_ACCESS_TOKEN in .env file\n");
}

// Test video ID from your Postman
$video_id = "v10033g50000t3kmrrvog65vr9p010vg";

echo "Testing TikTok Video Info API\n";
echo "==============================\n";
echo "Advertiser ID: $advertiser_id\n";
echo "Video ID: $video_id\n\n";

// Method 1: Using GET with query parameters
$url = 'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/info/';
$params = http_build_query([
    'advertiser_id' => $advertiser_id,
    'video_ids' => json_encode([$video_id])
]);

$ch = curl_init($url . '?' . $params);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Access-Token: ' . $access_token
    ]
]);

echo "Making GET request to: $url?$params\n\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";
$decoded = json_decode($response, true);
echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";

// If first method fails, try without JSON encoding
if (!empty($decoded['code']) && $decoded['code'] != 0) {
    echo "First attempt failed, trying with plain array...\n\n";
    
    $params = http_build_query([
        'advertiser_id' => $advertiser_id,
        'video_ids' => [$video_id]
    ]);
    
    $ch = curl_init($url . '?' . $params);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Access-Token: ' . $access_token
        ]
    ]);
    
    echo "Making GET request with array format\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    echo "Response:\n";
    echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "\n";
}
?>