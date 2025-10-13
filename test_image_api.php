<?php
/**
 * Test TikTok Image Search API - Following exact TikTok documentation
 */

$ACCESS_TOKEN = "e285e1288dbb1d9eff2e1924431917edbebfad31";
$ADVERTISER_ID = "7552160383491112961";
$PATH = "/open_api/v1.3/file/image/ad/search/";

function build_url($path) {
    return "https://business-api.tiktok.com" . $path;
}

// Test 1: Simple request with just advertiser_id
echo "Test 1: Simple image search\n";
echo "================================\n";

$url = build_url($PATH) . "?advertiser_id=" . $ADVERTISER_ID . "&page=1&page_size=10";

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
        "Access-Token: " . $ACCESS_TOKEN,
    ),
));

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "URL: " . $url . "\n";
echo "HTTP Code: " . $httpCode . "\n";
if ($error) {
    echo "CURL Error: " . $error . "\n";
}

$data = json_decode($response, true);

if ($data) {
    echo "Response Code: " . ($data['code'] ?? 'N/A') . "\n";
    echo "Response Message: " . ($data['message'] ?? 'N/A') . "\n";
    
    if (isset($data['data']['list'])) {
        echo "Images Found: " . count($data['data']['list']) . "\n\n";
        
        echo "First 3 images:\n";
        $count = 0;
        foreach ($data['data']['list'] as $image) {
            if ($count >= 3) break;
            echo "  - ID: " . $image['image_id'] . "\n";
            echo "    Name: " . ($image['file_name'] ?? 'N/A') . "\n";
            echo "    URL: " . ($image['image_url'] ?? 'N/A') . "\n";
            echo "    Size: " . ($image['width'] ?? '?') . "x" . ($image['height'] ?? '?') . "\n\n";
            $count++;
        }
    } else {
        echo "No images found in response\n";
    }
    
    if (isset($data['data']['page_info'])) {
        echo "Page Info:\n";
        echo "  - Current Page: " . $data['data']['page_info']['page'] . "\n";
        echo "  - Total Pages: " . $data['data']['page_info']['total_page'] . "\n";
        echo "  - Total Images: " . $data['data']['page_info']['total_number'] . "\n";
    }
} else {
    echo "Failed to parse JSON response\n";
    echo "Raw Response:\n" . $response . "\n";
}