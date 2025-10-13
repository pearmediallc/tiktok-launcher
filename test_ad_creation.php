<?php
// Test ad creation directly
require_once 'api.php';

$testData = [
    'adgroup_id' => '1234567890', // Replace with actual adgroup ID
    'ad_name' => 'Test Ad',
    'ad_text' => 'This is a test ad',
    'call_to_action' => 'APPLY_NOW',
    'landing_page_url' => 'https://example.com',
    'video_id' => 'v10033g50000d3knarvog65q5f7mgm20',
    'ad_format' => 'SINGLE_VIDEO'
];

echo "Testing ad creation with data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Simulate the API call
$_GET['action'] = 'create_ad';
$input = json_encode($testData);

echo "Response would be processed through api.php\n";
echo "To test: \n";
echo "1. Create a campaign first\n";
echo "2. Create an ad group\n";
echo "3. Use the ad group ID in the ad creation\n";
