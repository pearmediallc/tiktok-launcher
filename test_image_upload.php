<?php
// Test image upload with fixed multipart handling

// Include the SDK
require_once 'sdk/src/TikTokAds/autoload.php';
use TikTokAds\File\File;

// Configuration
$config = [
    'access_token' => 'e285e1288dbb1d9eff2e1924431917edbebfad31',
    'advertiser_id' => '7552160383491112961'
];

// Create a test image if it doesn't exist
$testImagePath = __DIR__ . '/test_image.png';
if (!file_exists($testImagePath)) {
    // Create a simple 100x100 red square PNG
    $img = imagecreatetruecolor(100, 100);
    $red = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $red);
    imagepng($img, $testImagePath);
    imagedestroy($img);
    echo "Created test image: $testImagePath\n";
}

// Calculate MD5 signature
$imageSignature = md5_file($testImagePath);
echo "Image MD5 signature: $imageSignature\n";

// Prepare upload parameters
$params = [
    'advertiser_id' => $config['advertiser_id'],
    'file_name' => 'test_image.png',
    'image_file' => new CURLFile($testImagePath, 'image/png', 'test_image.png'),
    'image_signature' => $imageSignature
];

echo "\nUpload parameters:\n";
echo "- advertiser_id: " . $params['advertiser_id'] . "\n";
echo "- file_name: " . $params['file_name'] . "\n";
echo "- image_signature: " . $params['image_signature'] . "\n";

// Initialize File SDK
$file = new File($config);

echo "\nSending image upload request to TikTok API...\n";
echo "Endpoint: https://business-api.tiktok.com/open_api/v1.3/file/image/ad/upload/\n";

// Upload the image
$response = $file->uploadImage($params);

echo "\n=== RESPONSE ===\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

// Check if successful
if (empty($response->code) || $response->code == 0) {
    echo "\n✅ Image uploaded successfully!\n";
    if (isset($response->data->image_id)) {
        echo "Image ID: " . $response->data->image_id . "\n";
    }
    if (isset($response->data->url)) {
        echo "Image URL: " . $response->data->url . "\n";
    }
} else {
    echo "\n❌ Upload failed!\n";
    echo "Error code: " . $response->code . "\n";
    echo "Error message: " . $response->message . "\n";
}

// Clean up test image
unlink($testImagePath);
echo "\nTest image deleted.\n";