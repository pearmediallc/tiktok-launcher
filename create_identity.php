<?php
// Helper script to create a CUSTOMIZED_USER identity for TikTok ads

require_once 'sdk/src/TikTokAds/autoload.php';
use TikTokAds\Identity\Identity;

$config = [
    'access_token' => 'e285e1288dbb1d9eff2e1924431917edbebfad31',
    'advertiser_id' => '7552160383491112961'
];

echo "Creating CUSTOMIZED_USER identity...\n";

$identity = new Identity($config);

// Create a custom identity
$params = [
    'advertiser_id' => $config['advertiser_id'],
    'display_name' => 'Campaign Launcher',
    'image_uri' => '' // You can add a profile image URL here if needed
];

$response = $identity->create($params);

echo "Response:\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

if (empty($response->code) || $response->code == 0) {
    echo "\n✅ Identity created successfully!\n";
    if (isset($response->data->identity_id)) {
        echo "Identity ID: " . $response->data->identity_id . "\n";
        echo "\n⚠️  IMPORTANT: Update this identity ID in api.php line 328\n";
    }
} else {
    echo "\n❌ Failed to create identity\n";
    echo "Error: " . ($response->message ?? 'Unknown error') . "\n";
}
