<?php
// Test script to verify Lead Generation ad creation logic

// Simulate the ad creation request data
$testData = [
    'action' => 'create_ad',
    'adgroup_id' => 'test_adgroup_123',
    'ad_name' => 'Test Lead Gen Ad',
    'ad_format' => 'SINGLE_VIDEO',
    'ad_text' => 'Test ad text for lead generation',
    'identity_id' => 'test_identity_123',
    'identity_type' => 'CUSTOMIZED_USER',
    'call_to_action' => 'SIGN_UP',
    'landing_page_url' => 'https://example.com/landing',
    'video_id' => 'test_video_123',
    'image_ids' => ['test_image_123'],
    'is_lead_gen' => true
];

// Test the creative building logic
$isLeadGen = isset($testData['is_lead_gen']) && $testData['is_lead_gen'];

$creative = [
    'ad_name' => $testData['ad_name'],
    'ad_format' => $testData['ad_format'] ?? 'SINGLE_VIDEO',
    'ad_text' => $testData['ad_text'],
    'identity_type' => $testData['identity_type'] ?? 'CUSTOMIZED_USER',
    'identity_id' => $testData['identity_id']
];

// Add call_to_action
if (!empty($testData['call_to_action'])) {
    $creative['call_to_action'] = $testData['call_to_action'];
} else {
    $creative['call_to_action'] = $isLeadGen ? 'SIGN_UP' : 'LEARN_MORE';
}

// Check landing_page_url logic
if (!$isLeadGen && !empty($testData['landing_page_url'])) {
    $creative['landing_page_url'] = $testData['landing_page_url'];
}

// Add media
if ($testData['ad_format'] === 'SINGLE_VIDEO') {
    if (!empty($testData['video_id'])) {
        $creative['video_id'] = $testData['video_id'];
    }
    if (!empty($testData['image_ids'])) {
        $creative['image_ids'] = is_array($testData['image_ids']) ? $testData['image_ids'] : [$testData['image_ids']];
    }
}

// Output test results
echo "Lead Generation Ad Creation Test\n";
echo "================================\n\n";

echo "Input Data:\n";
echo "- is_lead_gen: " . ($isLeadGen ? 'true' : 'false') . "\n";
echo "- landing_page_url provided: " . $testData['landing_page_url'] . "\n\n";

echo "Creative Object Built:\n";
echo json_encode($creative, JSON_PRETTY_PRINT) . "\n\n";

echo "Key Findings:\n";
echo "- Campaign Type: " . ($isLeadGen ? "Lead Generation" : "Standard") . "\n";
echo "- Call to Action: " . $creative['call_to_action'] . "\n";
echo "- Landing Page URL included: " . (isset($creative['landing_page_url']) ? "Yes - " . $creative['landing_page_url'] : "No - CORRECTLY EXCLUDED for Lead Gen") . "\n\n";

if ($isLeadGen && isset($creative['landing_page_url'])) {
    echo "❌ ERROR: landing_page_url should NOT be included for Lead Generation campaigns!\n";
} elseif ($isLeadGen && !isset($creative['landing_page_url'])) {
    echo "✅ SUCCESS: landing_page_url correctly excluded for Lead Generation campaign\n";
} elseif (!$isLeadGen && isset($creative['landing_page_url'])) {
    echo "✅ SUCCESS: landing_page_url correctly included for non-Lead Gen campaign\n";
} else {
    echo "⚠️  WARNING: Non-Lead Gen campaign without landing_page_url\n";
}