<?php
// Test script to fetch and display existing identities

require_once 'sdk/src/TikTokAds/autoload.php';
use TikTokAds\Identity\Identity;

$config = [
    'access_token' => 'e285e1288dbb1d9eff2e1924431917edbebfad31',
    'advertiser_id' => '7552160383491112961'
];

echo "Fetching identities for advertiser: " . $config['advertiser_id'] . "\n\n";

$identity = new Identity($config);

// Get CUSTOMIZED_USER identities
echo "=== CUSTOMIZED_USER Identities ===\n";
$params = [
    'advertiser_id' => $config['advertiser_id'],
    'identity_type' => 'CUSTOMIZED_USER',
    'page' => 1,
    'page_size' => 100
];

$response = $identity->getSelf($params);

if (empty($response->code) || $response->code == 0) {
    $identityList = $response->data->identity_list ?? $response->data->list ?? [];
    
    if (count($identityList) > 0) {
        foreach ($identityList as $id) {
            echo "Identity ID: " . $id->identity_id . "\n";
            echo "Name: " . ($id->identity_name ?? $id->display_name ?? 'Unknown') . "\n";
            echo "Type: " . ($id->identity_type ?? 'CUSTOMIZED_USER') . "\n";
            echo "---\n";
        }
    } else {
        echo "No CUSTOMIZED_USER identities found.\n";
    }
} else {
    echo "Error fetching identities: " . ($response->message ?? 'Unknown error') . "\n";
}

// Get TT_USER identities
echo "\n=== TT_USER Identities ===\n";
$params['identity_type'] = 'TT_USER';
$response = $identity->getSelf($params);

if (empty($response->code) || $response->code == 0) {
    $identityList = $response->data->identity_list ?? $response->data->list ?? [];
    
    if (count($identityList) > 0) {
        foreach ($identityList as $id) {
            echo "Identity ID: " . $id->identity_id . "\n";
            echo "Name: " . ($id->identity_name ?? $id->display_name ?? 'Unknown') . "\n";
            echo "Type: " . ($id->identity_type ?? 'TT_USER') . "\n";
            echo "---\n";
        }
    } else {
        echo "No TT_USER identities found.\n";
    }
} else {
    echo "Error fetching identities: " . ($response->message ?? 'Unknown error') . "\n";
}

echo "\n✅ Use one of the Identity IDs above in your ad creation.\n";
