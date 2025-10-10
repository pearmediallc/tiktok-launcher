<?php
// Script to get TikTok media library using search endpoint

session_start();
$_SESSION['authenticated'] = true;

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
    die("Missing credentials in .env file\n");
}

echo "Fetching TikTok Media Library\n";
echo "==============================\n\n";

// Try to search for videos in the account
$url = 'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/search/';

$params = [
    'advertiser_id' => $advertiser_id,
    'page' => 1,
    'page_size' => 20
];

$ch = curl_init($url . '?' . http_build_query($params));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Access-Token: ' . $access_token
    ]
]);

echo "Searching for videos...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode == 200 && isset($result['data']['list'])) {
    echo "Found " . count($result['data']['list']) . " videos:\n\n";
    
    $videos = [];
    foreach ($result['data']['list'] as $video) {
        echo "Video ID: " . $video['video_id'] . "\n";
        echo "  Name: " . ($video['video_name'] ?? 'N/A') . "\n";
        echo "  Size: " . ($video['size'] ?? 'N/A') . " bytes\n";
        echo "  Duration: " . ($video['duration'] ?? 'N/A') . " seconds\n";
        echo "  Status: " . ($video['status'] ?? 'N/A') . "\n";
        echo "\n";
        
        $videos[] = [
            'video_id' => $video['video_id'],
            'file_name' => $video['video_name'] ?? 'Video',
            'duration' => $video['duration'] ?? null,
            'size' => $video['size'] ?? null
        ];
    }
    
    // Update storage with found videos
    if (!empty($videos)) {
        $storageFile = __DIR__ . '/media_storage.json';
        $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
        
        foreach ($videos as $video) {
            // Check if already exists
            $exists = false;
            foreach ($storage['videos'] as $stored) {
                if ($stored['video_id'] === $video['video_id']) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $storage['videos'][] = array_merge($video, [
                    'upload_time' => time(),
                    'advertiser_id' => $advertiser_id
                ]);
            }
        }
        
        file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
        echo "Updated media_storage.json with found videos\n";
    }
} else {
    echo "No videos found or API error:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

// Try images too
echo "\n\nSearching for images...\n";
$url = 'https://business-api.tiktok.com/open_api/v1.3/file/image/ad/get/';

$ch = curl_init($url . '?' . http_build_query($params));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Access-Token: ' . $access_token
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode == 200 && isset($result['data']['list'])) {
    echo "Found " . count($result['data']['list']) . " images:\n\n";
    
    foreach ($result['data']['list'] as $image) {
        echo "Image ID: " . $image['image_id'] . "\n";
        echo "  URL: " . ($image['image_url'] ?? 'N/A') . "\n";
        echo "  Size: " . ($image['width'] ?? '?') . "x" . ($image['height'] ?? '?') . "\n";
        echo "\n";
    }
} else {
    echo "No images found or API error:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
?>