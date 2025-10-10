<?php
// Test script to verify API endpoints are working

session_start();
$_SESSION['authenticated'] = true; // Bypass auth for testing

// Load environment variables
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

echo "Testing TikTok API Endpoints\n";
echo "============================\n\n";

// Test 1: Get Identities
echo "1. Testing Get Identities...\n";
$url = 'http://localhost/api.php?action=get_identities';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
echo "   Raw Response: " . substr($response, 0, 200) . "\n";

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "   JSON Error: " . json_last_error_msg() . "\n";
    $result = ['success' => false, 'message' => 'Invalid JSON response'];
}

if ($result['success']) {
    echo "✓ Identities loaded successfully\n";
    if (isset($result['data']['list']) && count($result['data']['list']) > 0) {
        echo "  Found " . count($result['data']['list']) . " identities:\n";
        foreach ($result['data']['list'] as $identity) {
            echo "  - ID: " . $identity['identity_id'] . " | Name: " . $identity['identity_name'] . "\n";
        }
    } else {
        echo "  No identities found (you may need to create one in TikTok Ads Manager)\n";
    }
} else {
    echo "✗ Failed to get identities: " . ($result['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// Test 2: Get Images
echo "2. Testing Get Images...\n";
$ch = curl_init('http://localhost/api.php?action=get_images');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

if ($result['success']) {
    echo "✓ Images endpoint working\n";
    if (isset($result['data']['list']) && count($result['data']['list']) > 0) {
        echo "  Found " . count($result['data']['list']) . " images in library\n";
    } else {
        echo "  No images in library (upload some to test)\n";
    }
} else {
    echo "✗ Failed to get images: " . ($result['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// Test 3: Get Videos
echo "3. Testing Get Videos...\n";
$ch = curl_init('http://localhost/api.php?action=get_videos');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

if ($result['success']) {
    echo "✓ Videos endpoint working\n";
    if (isset($result['data']['list']) && count($result['data']['list']) > 0) {
        echo "  Found " . count($result['data']['list']) . " videos in library\n";
    } else {
        echo "  No videos in library (upload some to test)\n";
    }
} else {
    echo "✗ Failed to get videos: " . ($result['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// Test 4: Get Pixels
echo "4. Testing Get Pixels...\n";
$ch = curl_init('http://localhost/api.php?action=get_pixels');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

if ($result['success']) {
    echo "✓ Pixels loaded successfully\n";
    if (isset($result['data']['list']) && count($result['data']['list']) > 0) {
        echo "  Found " . count($result['data']['list']) . " pixels:\n";
        foreach ($result['data']['list'] as $pixel) {
            echo "  - ID: " . $pixel['pixel_id'] . " | Name: " . ($pixel['pixel_name'] ?? 'Unnamed') . "\n";
        }
    } else {
        echo "  No pixels found (you may need to create one in TikTok Ads Manager)\n";
    }
} else {
    echo "✗ Failed to get pixels: " . ($result['message'] ?? 'Unknown error') . "\n";
}

echo "\n============================\n";
echo "Tests complete!\n";
echo "\nNote: To test file uploads, use the web interface.\n";
echo "Make sure your TikTok account has:\n";
echo "- At least one custom identity created\n";
echo "- At least one pixel configured\n";
echo "- Some images/videos uploaded (optional)\n";

?>