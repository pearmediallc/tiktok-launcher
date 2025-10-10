<?php
// Test script to add sample media IDs to storage

// Initialize session
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

if (!$advertiser_id) {
    die("No advertiser ID found in .env\n");
}

// Sample video ID from your Postman data
$testVideoId = "v10033g50000t3kmrrvog65vr9p010vg";

// Add to storage
$storageFile = __DIR__ . '/media_storage.json';
$storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];

// Check if already exists
$exists = false;
foreach ($storage['videos'] as $video) {
    if ($video['video_id'] === $testVideoId && $video['advertiser_id'] === $advertiser_id) {
        $exists = true;
        break;
    }
}

if (!$exists) {
    $storage['videos'][] = [
        'video_id' => $testVideoId,
        'file_name' => 'Test Video',
        'upload_time' => time(),
        'advertiser_id' => $advertiser_id
    ];
    
    file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
    echo "Added test video ID: $testVideoId\n";
} else {
    echo "Test video ID already exists: $testVideoId\n";
}

echo "Current storage:\n";
echo json_encode($storage, JSON_PRETTY_PRINT) . "\n";

echo "\nTo test in browser:\n";
echo "1. Open the app and go to Ad creation step\n";
echo "2. Click 'Select Media' button\n";
echo "3. You should see the test video in the library\n";
?>