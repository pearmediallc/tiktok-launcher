<?php
// Disable error display to prevent HTML errors in JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Increase PHP limits for video uploads
// Note: ini_set doesn't work for upload_max_filesize and post_max_size
// Use .htaccess or php.ini file in this directory instead
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');

session_start();

// Check authentication
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Load TikTok SDK
require_once __DIR__ . '/sdk/vendor/autoload.php';

use TikTokAds\Campaign\Campaign;
use TikTokAds\AdGroup\AdGroup;
use TikTokAds\Ad\Ad;
use TikTokAds\File\File;
use TikTokAds\Identity\Identity;
use TikTokAds\Tools\Tools;

// SDK Configuration
$config = [
    'access_token' => $_ENV['TIKTOK_ACCESS_TOKEN'] ?? '',
    'environment'  => $_ENV['TIKTOK_ENVIRONMENT'] ?? 'production',
    'api_version'  => 'v1.3'
];

// Get advertiser ID from session or environment variable
$advertiser_id = $_SESSION['selected_advertiser_id'] ?? $_ENV['TIKTOK_ADVERTISER_ID'] ?? '';

// Logging function
function logToFile($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    error_log($logMessage);  // This will appear in Render logs
    file_put_contents(__DIR__ . '/api_debug.log', $logMessage, FILE_APPEND);
}

// Handle API requests
$requestData = json_decode(file_get_contents('php://input'), true);

// Get action from GET, POST, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? $requestData['action'] ?? '';

// Log incoming request
logToFile("============ INCOMING REQUEST ============");
logToFile("Action: {$action}");
logToFile("=== API REQUEST RECEIVED ===");
logToFile("Action: " . $action);
logToFile("Advertiser ID: " . $advertiser_id);
logToFile("Request Data: " . json_encode($requestData, JSON_PRETTY_PRINT));
logToFile("HTTP Headers: " . json_encode(getallheaders(), JSON_PRETTY_PRINT));
logToFile("==============================");

header('Content-Type: application/json');

try {
    switch ($action) {
        // Smart+ Campaign actions
        case 'create_smart_campaign':
            logToFile("Processing Smart+ Campaign Creation...");
            $data = $requestData;
            
            // Smart+ campaigns don't need budget at campaign level
            $params = [
                'advertiser_id' => $advertiser_id,
                'campaign_name' => $data['campaign_name'],
                'objective_type' => 'LEAD_GENERATION',
                'operation_status' => 'ENABLE',
                'is_smart_performance_campaign' => true // Required for Smart+ campaigns
            ];
            
            // Add schedule times if provided
            if (!empty($data['schedule_start_time'])) {
                $params['schedule_start_time'] = $data['schedule_start_time'];
            }
            if (!empty($data['schedule_end_time'])) {
                $params['schedule_end_time'] = $data['schedule_end_time'];
            }
            
            logToFile("=== TIKTOK API CALL DETAILS ===");
            logToFile("SDK Config: " . json_encode($config, JSON_PRETTY_PRINT));
            logToFile("TikTok API Endpoint: Campaign Create");
            logToFile("TikTok API Params: " . json_encode($params, JSON_PRETTY_PRINT));
            logToFile("================================");
            
            $campaign = new Campaign($config);
            $result = $campaign->create($params);
            
            logToFile("=== TIKTOK API RESPONSE ===");
            logToFile("TikTok Response: " . json_encode($result, JSON_PRETTY_PRINT));
            logToFile("===========================");
            
            $response = null;
            if ($result['code'] == 0) {
                $response = [
                    'success' => true,
                    'data' => $result['data'],
                    'message' => 'Smart+ Campaign created successfully'
                ];
                logToFile("=== SUCCESS RESPONSE ===");
                logToFile("Sending success response: " . json_encode($response, JSON_PRETTY_PRINT));
                echo json_encode($response);
            } else {
                $response = [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create Smart+ campaign',
                    'error' => $result
                ];
                logToFile("=== ERROR RESPONSE ===");
                logToFile("Sending error response: " . json_encode($response, JSON_PRETTY_PRINT));
                echo json_encode($response);
            }
            logToFile("Response sent, exiting...");
            exit;
            
        case 'create_smart_adgroup':
            $data = $requestData;
            
            // Smart+ Ad Group parameters with budget
            $params = [
                'advertiser_id' => $advertiser_id,
                'campaign_id' => $data['campaign_id'],
                'adgroup_name' => $data['adgroup_name'],
                'promotion_type' => 'LEAD_GENERATION',
                'promotion_target_type' => 'EXTERNAL_WEBSITE',
                'pixel_id' => $data['pixel_id'],
                'optimization_goal' => 'CONVERT',
                'optimization_event' => 'FORM',
                'billing_event' => 'OCPM',
                'placement_type' => 'PLACEMENT_TYPE_AUTOMATIC',
                'location_ids' => $data['location_ids'] ?? ['6252001'],
                'age_groups' => ['AGE_18_24', 'AGE_25_34', 'AGE_35_44', 'AGE_45_54', 'AGE_55_100'],
                'gender' => 'GENDER_UNLIMITED',
                'budget_mode' => 'BUDGET_MODE_DAY',
                'budget' => floatval($data['budget'] ?? 50),
                'schedule_type' => 'SCHEDULE_FROM_NOW',
                'schedule_start_time' => $data['schedule_start_time'],
                'bid_type' => 'BID_TYPE_CUSTOM',
                'conversion_bid_price' => floatval($data['conversion_bid_price'] ?? 10),
                'pacing' => 'PACING_MODE_SMOOTH',
                'click_attribution_window' => 'SEVEN_DAYS',
                'view_attribution_window' => 'ONE_DAY',
                'attribution_event_count' => 'EVERY'
            ];
            
            logToFile("Smart+ Ad Group Params: " . json_encode($params, JSON_PRETTY_PRINT));
            
            $adGroup = new AdGroup($config);
            $result = $adGroup->create($params);
            
            if ($result['code'] == 0) {
                echo json_encode([
                    'success' => true,
                    'data' => $result['data'],
                    'message' => 'Smart+ Ad Group created successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create Smart+ ad group',
                    'error' => $result
                ]);
            }
            exit;
            
        case 'create_smart_ad':
            $data = $requestData;
            $mediaList = $data['media_list'] ?? [];
            $textList = $data['ad_texts'] ?? [];
            
            // For now, create a single ad with first media and text
            $params = [
                'advertiser_id' => $advertiser_id,
                'adgroup_id' => $data['adgroup_id'],
                'ad_name' => $data['ad_name'],
                'ad_text' => $textList[0] ?? 'Ad text',
                'ad_format' => 'SINGLE_VIDEO',
                'video_id' => $mediaList[0] ?? '',
                'identity_id' => $data['identity_id'],
                'identity_type' => 'CUSTOMIZED',
                'call_to_action' => $data['call_to_action'] ?? 'LEARN_MORE',
                'landing_page_url' => $data['landing_page_url']
            ];
            
            logToFile("Smart+ Ad Params: " . json_encode($params, JSON_PRETTY_PRINT));
            
            $ad = new Ad($config);
            $result = $ad->create($params);
            
            if ($result['code'] == 0) {
                echo json_encode([
                    'success' => true,
                    'data' => $result['data'],
                    'message' => 'Smart+ Ad created successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create Smart+ ad',
                    'error' => $result
                ]);
            }
            exit;
        case 'get_advertisers':
            // Get list of advertiser accounts for the authenticated user
            $appId = $_ENV['TIKTOK_APP_ID'] ?? '';
            $secret = $_ENV['TIKTOK_APP_SECRET'] ?? '';
            $accessToken = $_ENV['TIKTOK_ACCESS_TOKEN'] ?? '';
            
            if (empty($appId) || empty($secret)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'App ID or Secret not configured'
                ]);
                exit;
            }
            
            $url = "https://business-api.tiktok.com/open_api/v1.3/oauth2/advertiser/get/";
            
            // Add query parameters
            $params = [
                'app_id' => $appId,
                'secret' => $secret
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url . '?' . http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Access-Token: " . $accessToken,
                    "Content-Type: application/json"
                ],
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            logToFile("Get Advertisers Response - HTTP Code: {$httpCode}");
            logToFile("Response: " . $result);
            
            if ($httpCode === 200) {
                $response = json_decode($result, true);
                if ($response && isset($response['code']) && $response['code'] == 0) {
                    echo json_encode([
                        'success' => true,
                        'data' => $response['data']['list'] ?? []
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => $response['message'] ?? 'Failed to get advertisers'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'API request failed with HTTP code: ' . $httpCode
                ]);
            }
            break;
            
        case 'set_advertiser':
            // Set the selected advertiser ID for the session
            $selectedAdvertiserId = $requestData['advertiser_id'] ?? '';
            
            logToFile("Set Advertiser Request - ID: {$selectedAdvertiserId}");
            
            if (empty($selectedAdvertiserId)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Advertiser ID is required'
                ]);
                exit;
            }
            
            // Store in session
            $_SESSION['selected_advertiser_id'] = $selectedAdvertiserId;
            
            logToFile("Advertiser ID stored in session: {$selectedAdvertiserId}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Advertiser selected successfully',
                'advertiser_id' => $selectedAdvertiserId,
                'redirect' => 'campaign-select.php'
            ]);
            exit; // Ensure we exit to prevent any additional output
            
        case 'test_image_search':
            // Direct test of image search API - matching TikTok docs exactly
            header('Content-Type: application/json');
            
            $url = "https://business-api.tiktok.com/open_api/v1.3/file/image/ad/search/?" . 
                   "advertiser_id={$advertiser_id}&" .
                   "page=1&" .
                   "page_size=10";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Access-Token: " . ($_ENV['TIKTOK_ACCESS_TOKEN'] ?? '')
                ]
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $response = json_decode($result);
            
            echo json_encode([
                'success' => $httpCode == 200 && isset($response->data),
                'http_code' => $httpCode,
                'image_count' => isset($response->data->list) ? count($response->data->list) : 0,
                'images' => $response->data->list ?? [],
                'raw' => $response
            ], JSON_PRETTY_PRINT);
            exit;
            
        case 'create_campaign':
            $campaign = new Campaign($config);
            $data = $requestData;

            // Base parameters
            $params = [
                'advertiser_id' => $advertiser_id,
                'campaign_name' => $data['campaign_name'],
                'objective_type' => 'LEAD_GENERATION',
                'budget_mode' => $data['budget_mode'] ?? 'BUDGET_MODE_DAY',
                'budget' => floatval($data['budget'] ?? 20),
                'operation_status' => 'ENABLE'
            ];

            // Schedule times are optional
            if (!empty($data['schedule_start_time'])) {
                $params['schedule_start_time'] = $data['schedule_start_time'];
            }
            if (!empty($data['schedule_end_time'])) {
                $params['schedule_end_time'] = $data['schedule_end_time'];
            }

            logToFile("TikTok API: POST /open_api/v1.3/campaign/create/");
            logToFile("Campaign Params: " . json_encode($params, JSON_PRETTY_PRINT));

            $response = $campaign->create($params);

            logToFile("Campaign Response: " . json_encode($response, JSON_PRETTY_PRINT));
            logToFile("Response Code: " . ($response->code ?? 'null'));
            logToFile("Response Message: " . ($response->message ?? 'null'));

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Campaign created successfully'
            ]);
            break;

        case 'create_adgroup':
            $adGroup = new AdGroup($config);
            $data = $requestData;

            function is_valid_datetime($s) {
                return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s);
            }

            function generate_time_series($startHour, $endHour, $days = [0,1,2,3,4,5,6]) {
                // Generate 336 character string (7 days × 48 half-hour slots)
                $ts = '';
                for ($d = 0; $d < 7; $d++) {
                    for ($h = 0; $h < 24; $h++) {
                        // Each hour has 2 half-hour slots
                        if (in_array($d, $days) && $h >= $startHour && $h < $endHour) {
                            $ts .= '11'; // Both half-hour slots enabled
                        } else {
                            $ts .= '00'; // Both half-hour slots disabled
                        }
                    }
                }
                return $ts;
            }

            $required_fields = ['campaign_id', 'adgroup_name', 'placement_type', 'placements',
                                'promotion_type', 'optimization_goal', 'billing_event', 'budget_mode', 'budget'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Required field missing: {$field}"]);
                    exit;
                }
            }

            if ($data['budget_mode'] === 'BUDGET_MODE_TOTAL') {
                if (empty($data['schedule_end_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'schedule_end_time is required when budget_mode is BUDGET_MODE_TOTAL']);
                    exit;
                }
            }

            if (in_array($data['optimization_goal'], ['CONVERT', 'VALUE'])) {
                if (empty($data['pixel_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'pixel_id is required when optimization_goal is CONVERT or VALUE']);
                    exit;
                }
            }
            
            // For Lead Generation via website forms (CONVERT + FORM event)
            if ($data['optimization_goal'] === 'CONVERT' && 
                isset($data['optimization_event']) && $data['optimization_event'] === 'FORM') {
                if (empty($data['pixel_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'pixel_id is required for Lead Generation campaigns with FORM optimization']);
                    exit;
                }
            }
            
            // For instant form Lead Generation (if using lead_gen_form_id)
            if ($data['optimization_goal'] === 'LEAD_GENERATION' && 
                (!isset($data['promotion_target_type']) || $data['promotion_target_type'] === 'INSTANT_PAGE')) {
                if (empty($data['lead_gen_form_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'lead_gen_form_id is required for Lead Generation campaigns with Instant Forms']);
                    exit;
                }
            }

            if ($data['promotion_type'] === 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE') {
                if (empty($data['messaging_app_type'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'messaging_app_type is required for LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE']);
                    exit;
                }

                if ($data['optimization_goal'] === 'CONVERSATION') {
                    if (in_array($data['messaging_app_type'], ['ZALO', 'LINE', 'IM_URL'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'When optimization_goal is CONVERSATION, messaging_app_type cannot be ZALO, LINE, or IM_URL']);
                        exit;
                    }

                    if (empty($data['message_event_set_id']) && empty($data['messaging_app_account_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'message_event_set_id or messaging_app_account_id is required when optimization_goal is CONVERSATION']);
                        exit;
                    }
                }
            }

            $params = [
                'advertiser_id'      => $advertiser_id,
                'campaign_id'        => $data['campaign_id'],
                'adgroup_name'       => $data['adgroup_name'],
                'promotion_type'     => $data['promotion_type'],
                'optimization_goal'  => $data['optimization_goal'],
                'billing_event'      => $data['billing_event'],
                'placement_type'     => $data['placement_type'],
                'placements'         => $data['placements'],
                'budget_mode'        => $data['budget_mode'],
                'budget'             => floatval($data['budget']),
                'bid_type'           => $data['bid_type'],
                'location_ids'       => $data['location_ids'] ?? ['6252001'],
            ];

            if (!empty($data['conversion_bid_price']) && floatval($data['conversion_bid_price']) > 0) {
                $params['conversion_bid_price'] = floatval($data['conversion_bid_price']);
            } elseif (!empty($data['bid']) && floatval($data['bid']) > 0) {
                $params['bid'] = floatval($data['bid']);
            }

            if (!empty($data['lead_gen_form_id'])) {
                $params['lead_gen_form_id'] = $data['lead_gen_form_id'];
            }
            if (!empty($data['pixel_id'])) {
                $params['pixel_id'] = strval($data['pixel_id']);
            }
            if (!empty($data['promotion_target_type'])) {
                $params['promotion_target_type'] = $data['promotion_target_type'];
            }
            if (!empty($data['optimization_event'])) {
                $params['optimization_event'] = $data['optimization_event'];
            }
            if (!empty($data['custom_conversion_id'])) {
                $params['custom_conversion_id'] = $data['custom_conversion_id'];
            }
            if (!empty($data['click_attribution_window'])) {
                $params['click_attribution_window'] = $data['click_attribution_window'];
            }
            if (!empty($data['view_attribution_window'])) {
                $params['view_attribution_window'] = $data['view_attribution_window'];
            }
            if (!empty($data['attribution_event_count'])) {
                $params['attribution_event_count'] = $data['attribution_event_count'];
            }
            if (!empty($data['age_groups'])) {
                $params['age_groups'] = $data['age_groups'];
            }
            if (!empty($data['gender'])) {
                $params['gender'] = $data['gender'];
            }
            if (!empty($data['pacing'])) {
                $params['pacing'] = $data['pacing'];
            }
            if (!empty($data['messaging_app_type'])) {
                $params['messaging_app_type'] = $data['messaging_app_type'];
            }
            if (!empty($data['messaging_app_account_id'])) {
                $params['messaging_app_account_id'] = $data['messaging_app_account_id'];
            }
            if (!empty($data['message_event_set_id'])) {
                $params['message_event_set_id'] = $data['message_event_set_id'];
            }
            if (!empty($data['deep_funnel_optimization_status'])) {
                $params['deep_funnel_optimization_status'] = $data['deep_funnel_optimization_status'];
            }
            if (isset($data['search_result_enabled'])) {
                $params['search_result_enabled'] = (bool)$data['search_result_enabled'];
            }
            if (isset($data['share_disabled'])) {
                $params['share_disabled'] = (bool)$data['share_disabled'];
            }
            if (!empty($data['purchase_intention_keyword_ids'])) {
                $params['purchase_intention_keyword_ids'] = $data['purchase_intention_keyword_ids'];
            }
            if (!empty($data['category_exclusion_ids'])) {
                $params['category_exclusion_ids'] = $data['category_exclusion_ids'];
            }

            if (!empty($data['schedule_type'])) {
                $params['schedule_type'] = $data['schedule_type'];
            }

            if (!empty($data['schedule_start_time'])) {
                if (!is_valid_datetime($data['schedule_start_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'schedule_start_time must be YYYY-MM-DD HH:MM:SS (UTC)']);
                    exit;
                }
                $params['schedule_start_time'] = $data['schedule_start_time'];
            }

            if (!empty($data['schedule_end_time'])) {
                if (!is_valid_datetime($data['schedule_end_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'schedule_end_time must be YYYY-MM-DD HH:MM:SS (UTC)']);
                    exit;
                }
                $params['schedule_end_time'] = $data['schedule_end_time'];
            }

            // Handle dayparting
            if (!empty($data['dayparting'])) {
                logToFile("Dayparting received: length=" . strlen($data['dayparting']));
                logToFile("First 48 chars (Monday 00:00-23:59): " . substr($data['dayparting'], 0, 48));
                
                // TikTok expects 336 characters (7 days × 48 half-hour slots)
                if (strlen($data['dayparting']) !== 336) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'dayparting must be 336-char binary string (7 days × 48 half-hour slots, got ' . strlen($data['dayparting']) . ' chars)']);
                    exit;
                }
                
                // Validate that it only contains 0s and 1s
                if (!preg_match('/^[01]{336}$/', $data['dayparting'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'dayparting must contain only 0s and 1s']);
                    exit;
                }
                
                // Only set dayparting if at least one slot is selected
                if (strpos($data['dayparting'], '1') !== false) {
                    $params['dayparting'] = $data['dayparting'];
                    logToFile("Dayparting will be sent to TikTok API (336 chars)");
                } else {
                    logToFile("Dayparting string has no selected time slots, skipping");
                }
            } elseif (isset($data['daypart_start_hour']) && isset($data['daypart_end_hour'])) {
                $params['dayparting'] = generate_time_series(
                    intval($data['daypart_start_hour']),
                    intval($data['daypart_end_hour']),
                    $data['daypart_days'] ?? [0,1,2,3,4,5,6]
                );
                logToFile("Generated dayparting from hours: " . $params['dayparting']);
            }

            logToFile("TikTok API: POST /open_api/v1.3/adgroup/create/");
            logToFile("AdGroup Params: " . json_encode($params, JSON_PRETTY_PRINT));

            $response = $adGroup->create($params);

            logToFile("AdGroup Response: " . json_encode($response, JSON_PRETTY_PRINT));
            logToFile("Response Code: " . ($response->code ?? 'null'));
            logToFile("Response Message: " . ($response->message ?? 'null'));

            echo json_encode([
                'success' => empty($response->code),
                'data'    => $response->data ?? null,
                'message' => $response->message ?? 'Ad group created',
                'code'    => $response->code ?? null
            ]);
            break;

        case 'create_ad':
            $ad = new Ad($config);
            $data = $requestData;

            // TikTok API expects creatives array structure
            // According to docs: identity_type and identity_id are REQUIRED
            
            // Validate required fields
            if (empty($data['identity_id'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Identity is required for ad creation. Please select an identity or create one in TikTok Ads Manager.',
                    'code' => 40001
                ]);
                exit;
            }
            
            // Check if this is a Lead Generation campaign
            $isLeadGen = isset($data['is_lead_gen']) && $data['is_lead_gen'];
            
            // Build creative object according to TikTok documentation
            $creative = [
                'ad_name' => $data['ad_name'],
                'ad_format' => $data['ad_format'] ?? 'SINGLE_VIDEO',
                'ad_text' => $data['ad_text'],
                'identity_type' => $data['identity_type'] ?? 'CUSTOMIZED_USER',
                'identity_id' => $data['identity_id']
            ];
            
            // Add call_to_action - required field
            if (!empty($data['call_to_action'])) {
                $creative['call_to_action'] = $data['call_to_action'];
            } else {
                // Default CTAs based on campaign type
                $creative['call_to_action'] = $isLeadGen ? 'SIGN_UP' : 'LEARN_MORE';
            }
            
            // According to TikTok docs: landing_page_url is REQUIRED when promotion_type is WEBSITE
            // This applies to Lead Generation campaigns with WEBSITE promotion_type
            if (!empty($data['landing_page_url'])) {
                // Validate URL format
                if (!filter_var($data['landing_page_url'], FILTER_VALIDATE_URL)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid landing page URL format. Please provide a valid URL.',
                        'code' => 40003
                    ]);
                    exit;
                }
                $creative['landing_page_url'] = $data['landing_page_url'];
            } else if ($data['promotion_type'] === 'WEBSITE' || !isset($data['promotion_type'])) {
                // For WEBSITE promotion type (including Lead Gen), landing_page_url is required
                echo json_encode([
                    'success' => false,
                    'message' => 'Landing page URL is required for WEBSITE promotion type.',
                    'code' => 40002
                ]);
                exit;
            }
            
            logToFile("Campaign type: " . ($isLeadGen ? "Lead Generation" : "Standard"));
            logToFile("Call to action: " . $creative['call_to_action']);
            logToFile("Landing page URL included: " . (isset($creative['landing_page_url']) ? "Yes - " . $creative['landing_page_url'] : "No"));

            // Add video_id and/or image_ids based on format
            if ($data['ad_format'] === 'SINGLE_VIDEO') {
                if (!empty($data['video_id'])) {
                    $creative['video_id'] = $data['video_id'];
                }
                // For video ads, image_ids is required as the video cover (thumbnail)
                // If not provided, use a default placeholder or generate one
                if (!empty($data['image_ids'])) {
                    $creative['image_ids'] = is_array($data['image_ids']) ? $data['image_ids'] : [$data['image_ids']];
                } else {
                    // You should upload a default image to TikTok and use its ID here
                    // For now, we'll require the frontend to provide it
                    logToFile("Warning: No image_ids provided for video ad - this may cause the ad creation to fail");
                }
            } elseif ($data['ad_format'] === 'SINGLE_IMAGE' && !empty($data['image_ids'])) {
                $creative['image_ids'] = is_array($data['image_ids']) ? $data['image_ids'] : [$data['image_ids']];
            }

            $params = [
                'advertiser_id' => $advertiser_id,
                'adgroup_id' => $data['adgroup_id'],
                'creatives' => [$creative]
            ];

            logToFile("============ CREATE AD REQUEST ============");
            logToFile("Create Ad Request: " . json_encode($params, JSON_PRETTY_PRINT));

            $response = $ad->create($params);

            logToFile("============ CREATE AD RESPONSE ============");
            logToFile("Create Ad Response: " . json_encode($response, JSON_PRETTY_PRINT));
            logToFile("Response Code: " . ($response->code ?? 'null'));
            logToFile("Response Message: " . ($response->message ?? 'null'));
            if (isset($response->errors)) {
                logToFile("Response Errors: " . json_encode($response->errors, JSON_PRETTY_PRINT));
            }

            // Check for success
            $isSuccess = (empty($response->code) || $response->code == 0) && isset($response->data);
            
            // Get error details if failed
            $errorMessage = 'Ad created successfully';
            if (!$isSuccess) {
                $errorMessage = $response->message ?? 'Unknown error occurred';
                if (isset($response->errors) && is_array($response->errors)) {
                    $errorDetails = [];
                    foreach ($response->errors as $error) {
                        $errorDetails[] = $error->field . ': ' . $error->message;
                    }
                    $errorMessage .= ' - ' . implode(', ', $errorDetails);
                }
            }

            echo json_encode([
                'success' => $isSuccess,
                'data' => $response->data ?? null,
                'message' => $isSuccess ? 'Ad created successfully' : $errorMessage,
                'code' => $response->code ?? null,
                'debug' => [
                    'request' => $params,
                    'response' => $response
                ]
            ]);
            break;

        case 'upload_thumbnail_as_cover':
            // Upload video thumbnail URL as cover image to TikTok
            $data = $requestData;
            
            if (empty($data['thumbnail_url']) || empty($data['video_id'])) {
                throw new Exception('thumbnail_url and video_id are required');
            }
            
            $thumbnailUrl = $data['thumbnail_url'];
            $videoId = $data['video_id'];
            
            logToFile("============ THUMBNAIL UPLOAD REQUEST ============");
            logToFile("Video ID: " . $videoId);
            logToFile("Thumbnail URL: " . $thumbnailUrl);
            
            // Download thumbnail from URL
            $tempFile = tempnam(sys_get_temp_dir(), 'thumbnail_');
            $imageData = file_get_contents($thumbnailUrl);
            
            if ($imageData === false) {
                throw new Exception('Failed to download thumbnail from URL: ' . $thumbnailUrl);
            }
            
            file_put_contents($tempFile, $imageData);
            
            // Get image info for filename
            $imageInfo = getimagesize($tempFile);
            if (!$imageInfo) {
                unlink($tempFile);
                throw new Exception('Invalid image format');
            }
            
            $mimeType = $imageInfo['mime'] ?? 'image/jpeg';
            $extension = $mimeType === 'image/png' ? '.png' : '.jpg';
            // Add timestamp to make filename unique and avoid duplicate material errors
            $fileName = 'video_' . substr($videoId, -8) . '_thumb_' . time() . '_' . rand(1000, 9999) . $extension;
            
            $imageSignature = md5_file($tempFile);
            
            logToFile("Image signature: " . $imageSignature);
            logToFile("File name: " . $fileName);
            logToFile("MIME type: " . $mimeType);
            
            $file = new File($config);
            
            $params = [
                'advertiser_id' => $advertiser_id,
                'file_name' => $fileName,
                'image_file' => new CURLFile($tempFile, $mimeType, $fileName),
                'image_signature' => $imageSignature
            ];
            
            $response = $file->uploadImage($params);
            
            // Clean up temp file
            unlink($tempFile);
            
            logToFile("Thumbnail upload response: " . json_encode($response, JSON_PRETTY_PRINT));
            
            $success = empty($response->code) || $response->code == 0;
            
            if ($success && isset($response->data->image_id)) {
                // Store in persistent storage
                $storageFile = __DIR__ . '/media_storage.json';
                $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
                
                $storage['images'][] = [
                    'image_id' => $response->data->image_id,
                    'file_name' => $fileName,
                    'upload_time' => time(),
                    'url' => $response->data->url ?? $thumbnailUrl,
                    'advertiser_id' => $advertiser_id,
                    'source' => 'video_thumbnail',
                    'video_id' => $videoId
                ];
                
                file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
                
                logToFile("Thumbnail uploaded successfully with ID: " . $response->data->image_id);
            }
            
            echo json_encode([
                'success' => $success,
                'data' => $response->data ?? null,
                'message' => $success ? 'Video thumbnail uploaded as cover image' : ($response->message ?? 'Upload failed'),
                'code' => $response->code ?? null
            ]);
            break;
            
        case 'upload_image':
            $file = new File($config);

            logToFile("============ IMAGE UPLOAD REQUEST ============");
            logToFile("Upload Image Request - FILES: " . json_encode($_FILES, JSON_PRETTY_PRINT));

            if (!isset($_FILES['image'])) {
                throw new Exception('No image file provided');
            }

            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
                ];
                $errorMsg = $uploadErrors[$_FILES['image']['error']] ?? 'Unknown error: ' . $_FILES['image']['error'];
                throw new Exception($errorMsg);
            }

            $fileName = $_FILES['image']['name'];
            $tmpPath = $_FILES['image']['tmp_name'];

            if (!file_exists($tmpPath)) {
                throw new Exception('Uploaded file not found at: ' . $tmpPath);
            }

            $imageSignature = md5_file($tmpPath);

            logToFile("Image Upload - File: " . $fileName);
            logToFile("Image Upload - Advertiser ID: " . $advertiser_id);
            logToFile("Image Upload - Signature: " . $imageSignature);

            $params = [
                'advertiser_id' => $advertiser_id,
                'file_name' => $fileName,
                'image_file' => new CURLFile($tmpPath, $_FILES['image']['type'], $fileName),
                'image_signature' => $imageSignature
            ];

            $response = $file->uploadImage($params);

            logToFile("Image Upload Response: " . json_encode($response, JSON_PRETTY_PRINT));

            $success = empty($response->code) || $response->code == 0;
            
            // If upload successful, store the image ID for later retrieval
            if ($success && isset($response->data->image_id)) {
                // Store in persistent storage
                $storageFile = __DIR__ . '/media_storage.json';
                $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
                
                $storage['images'][] = [
                    'image_id' => $response->data->image_id,
                    'file_name' => $fileName,
                    'upload_time' => time(),
                    'url' => $response->data->url ?? null,
                    'advertiser_id' => $advertiser_id
                ];
                
                file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
                
                logToFile("Image uploaded successfully with ID: " . $response->data->image_id);
            }
            
            echo json_encode([
                'success' => $success,
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Image uploaded successfully',
                'code' => $response->code ?? null
            ]);
            break;

        case 'upload_video':
            $file = new File($config);

            logToFile("============ VIDEO UPLOAD REQUEST ============");
            logToFile("Upload Video Request - FILES: " . json_encode($_FILES, JSON_PRETTY_PRINT));

            if (!isset($_FILES['video'])) {
                throw new Exception('No video file provided');
            }

            if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
                ];
                $errorMsg = $uploadErrors[$_FILES['video']['error']] ?? 'Unknown error: ' . $_FILES['video']['error'];
                throw new Exception($errorMsg);
            }

            $fileName = $_FILES['video']['name'];
            $tmpPath = $_FILES['video']['tmp_name'];
            $fileSize = $_FILES['video']['size'];

            if (!file_exists($tmpPath)) {
                throw new Exception('Uploaded file not found at: ' . $tmpPath);
            }

            // Get file MIME type to properly set in CURLFile
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            $videoSignature = md5_file($tmpPath);

            logToFile("Video Upload - File: " . $fileName);
            logToFile("Video Upload - Size: " . $fileSize . " bytes");
            logToFile("Video Upload - MIME Type: " . $mimeType);
            logToFile("Video Upload - Advertiser ID: " . $advertiser_id);
            logToFile("Video Upload - Signature: " . $videoSignature);

            // Try SDK upload first
            $params = [
                'advertiser_id' => $advertiser_id,
                'upload_type' => 'UPLOAD_BY_FILE',
                'video_file' => new CURLFile($tmpPath, $mimeType, $fileName),
                'video_signature' => $videoSignature
            ];

            $response = $file->uploadVideo($params);
            
            logToFile("Video Upload SDK Response: " . json_encode($response, JSON_PRETTY_PRINT));
            
            // If SDK fails, try direct cURL upload
            if (!empty($response->code) && $response->code != 0) {
                logToFile("SDK upload failed with code: " . $response->code . ", trying direct upload...");
                
                $url = 'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/upload/';
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $params,
                    CURLOPT_HTTPHEADER => [
                        'Access-Token: ' . $config['access_token']
                    ],
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_SSL_VERIFYPEER => true
                ]);
                
                $directResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                logToFile("Direct cURL HTTP Code: " . $httpCode);
                logToFile("Direct cURL Response: " . $directResponse);
                
                $response = json_decode($directResponse);
            }

            $success = empty($response->code) || $response->code == 0;
            
            // If upload successful, store the video ID for later retrieval
            if ($success && isset($response->data->video_id)) {
                // Store in persistent storage
                $storageFile = __DIR__ . '/media_storage.json';
                $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
                
                $storage['videos'][] = [
                    'video_id' => $response->data->video_id,
                    'file_name' => $fileName,
                    'upload_time' => time(),
                    'advertiser_id' => $advertiser_id
                ];
                
                file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
                
                logToFile("Video uploaded successfully with ID: " . $response->data->video_id);
            }
            
            echo json_encode([
                'success' => $success,
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Video uploaded successfully',
                'code' => $response->code ?? null
            ]);
            break;

       

        case 'upload_video_direct':
            // Direct cURL implementation - fallback if SDK fails
            logToFile("============ DIRECT VIDEO UPLOAD REQUEST ============");
            
            if (!isset($_FILES['video'])) {
                throw new Exception('No video file provided');
            }

            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
            ];

            if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = $uploadErrors[$_FILES['video']['error']] ?? 'Unknown error';
                throw new Exception($errorMsg);
            }

            $fileName = $_FILES['video']['name'];
            $tmpPath = $_FILES['video']['tmp_name'];
            $fileSize = $_FILES['video']['size'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            
            $videoSignature = md5_file($tmpPath);
            
            logToFile("Direct Upload - File: $fileName, Size: $fileSize bytes, MIME: $mimeType");

            $url = 'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/upload/';
            
            $postFields = [
                'advertiser_id' => $advertiser_id,
                'file_name' => $fileName,
                'upload_type' => 'UPLOAD_BY_FILE',
                'video_file' => new CURLFile($tmpPath, $mimeType, $fileName),
                'video_signature' => $videoSignature,
                'flaw_detect' => 'true',
                'auto_fix_enabled' => 'true',
                'auto_bind_enabled' => 'true'
            ];

            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'Access-Token: ' . $config['access_token']
                ],
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            logToFile("Executing direct cURL request...");
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            curl_close($ch);

            logToFile("HTTP Status: $httpCode");
            logToFile("cURL Error: " . ($curlError ?: 'None'));
            logToFile("Response: " . $response);

            if ($curlError) {
                throw new Exception('cURL Error: ' . $curlError);
            }

            $responseData = json_decode($response, true);

            echo json_encode([
                'success' => isset($responseData['code']) && $responseData['code'] == 0,
                'data' => $responseData['data'] ?? null,
                'message' => $responseData['message'] ?? 'Video upload completed',
                'code' => $responseData['code'] ?? null,
                'http_code' => $httpCode
            ]);
            break;

        case 'get_identities':
            $identity = new Identity($config);
            
            logToFile("Get Identities - Advertiser ID: " . $advertiser_id);
            
            // Try to get both TT_USER and CUSTOMIZED_USER identities
            $allIdentities = [];
            
            // First get TT_USER identities (TikTok accounts)
            $params = [
                'advertiser_id' => $advertiser_id,
                'identity_type' => 'TT_USER',
                'page' => 1,
                'page_size' => 100
            ];
            
            $response = $identity->getSelf($params);
            logToFile("Get TT_USER Identities Response: " . json_encode($response, JSON_PRETTY_PRINT));
            
            // Format the identities properly for frontend - check both list and identity_list
            $identityList = $response->data->identity_list ?? $response->data->list ?? null;
            
            if (empty($response->code) && $identityList) {
                foreach ($identityList as $id) {
                    $allIdentities[] = [
                        'identity_id' => $id->identity_id,
                        'identity_name' => $id->identity_name ?? $id->display_name ?? 'TikTok User',
                        'display_name' => $id->display_name ?? $id->identity_name ?? 'TikTok User',
                        'identity_type' => $id->identity_type ?? 'TT_USER'
                    ];
                }
            }
            
            // Also try to get CUSTOMIZED_USER identities
            $params['identity_type'] = 'CUSTOMIZED_USER';
            $response = $identity->getSelf($params);
            logToFile("Get CUSTOMIZED_USER Identities Response: " . json_encode($response, JSON_PRETTY_PRINT));
            
            $identityList = $response->data->identity_list ?? $response->data->list ?? null;
            if (empty($response->code) && $identityList) {
                foreach ($identityList as $id) {
                    $allIdentities[] = [
                        'identity_id' => $id->identity_id,
                        'identity_name' => $id->identity_name ?? $id->display_name ?? 'Custom User',
                        'display_name' => $id->display_name ?? $id->identity_name ?? 'Custom User',
                        'identity_type' => $id->identity_type ?? 'CUSTOMIZED_USER'
                    ];
                }
            }
            
            // If still no identities, try without identity_type filter
            if (empty($allIdentities)) {
                unset($params['identity_type']);
                $response = $identity->getSelf($params);
                logToFile("Get ALL Identities Response: " . json_encode($response, JSON_PRETTY_PRINT));
                
                $identityList = $response->data->identity_list ?? $response->data->list ?? null;
                if (empty($response->code) && $identityList) {
                    foreach ($identityList as $id) {
                        $allIdentities[] = [
                            'identity_id' => $id->identity_id,
                            'identity_name' => $id->identity_name ?? $id->display_name ?? 'Identity',
                            'display_name' => $id->display_name ?? $id->identity_name ?? 'Identity',
                            'identity_type' => $id->identity_type ?? 'UNKNOWN'
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => ['list' => $allIdentities],
                'message' => empty($allIdentities) ? 'No identities found - Create one in TikTok Ads Manager' : null
            ]);
            break;

        case 'get_pixels':
            $tools = new Tools($config);
            $response = $tools->getPixels([
                'advertiser_id' => $advertiser_id
            ]);

            logToFile("Get Pixels Response: " . json_encode($response, JSON_PRETTY_PRINT));

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? null,
                'code' => $response->code ?? null
            ]);
            break;

        case 'get_images':
            logToFile("Get Images - Advertiser ID: " . $advertiser_id);
            
            $images = [];
            
            // Use the image search endpoint to get ALL images - matching TikTok's exact format
            try {
                $page = 1;
                $pageSize = 100;
                $hasMore = true;
                
                while ($hasMore && $page <= 10) { // Limit to 10 pages for safety
                    // Build URL exactly as TikTok documentation shows
                    $url = "https://business-api.tiktok.com/open_api/v1.3/file/image/ad/search/?" . 
                           "advertiser_id={$advertiser_id}&" .
                           "page={$page}&" .
                           "page_size={$pageSize}";
                    
                    logToFile("Searching images with URL: " . $url);
                    
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => [
                            "Access-Token: " . $accessToken
                            // NO Content-Type header for GET requests per TikTok docs
                        ]
                    ]);
                    
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    
                    logToFile("Image search HTTP Code: " . $httpCode);
                    if ($curlError) {
                        logToFile("CURL Error: " . $curlError);
                    }
                    
                    if ($httpCode == 200) {
                        $response = json_decode($result);
                        logToFile("Image search response page {$page}: " . json_encode($response, JSON_PRETTY_PRINT));
                        
                        if (isset($response->data->list) && is_array($response->data->list)) {
                            foreach ($response->data->list as $image) {
                                // Based on the Postman response, the structure is correct
                                $images[] = [
                                    'image_id' => $image->image_id,
                                    'url' => $image->image_url ?? '',  // image_url is the correct field
                                    'file_name' => $image->file_name ?? $image->material_name ?? 'Image',
                                    'width' => $image->width ?? null,
                                    'height' => $image->height ?? null,
                                    'format' => $image->format ?? null,
                                    'size' => $image->size ?? null,
                                    'create_time' => $image->create_time ?? null,
                                    'modify_time' => $image->modify_time ?? null,
                                    'displayable' => $image->displayable ?? true,
                                    'type' => 'image'
                                ];
                            }
                            
                            // Check if there are more pages
                            if (isset($response->data->page_info)) {
                                $totalNumber = $response->data->page_info->total_number ?? 0;
                                $totalPage = $response->data->page_info->total_page ?? 1;
                                $currentPage = $response->data->page_info->page ?? $page;
                                
                                logToFile("Page {$currentPage} of {$totalPage}, Total images: {$totalNumber}");
                                
                                $hasMore = $currentPage < $totalPage;
                                $page++;
                            } else {
                                // No page info, assume no more pages
                                $hasMore = false;
                            }
                        } else {
                            logToFile("No images found in response or invalid response structure");
                            $hasMore = false;
                        }
                    } else {
                        logToFile("Failed to search images: HTTP {$httpCode}, Response: " . $result);
                        
                        // If search fails, try local storage as fallback
                        $storageFile = __DIR__ . '/media_storage.json';
                        $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
                        
                        $advertiserImages = array_filter($storage['images'] ?? [], function($img) use ($advertiser_id) {
                            return $img['advertiser_id'] === $advertiser_id;
                        });
                        
                        foreach ($advertiserImages as $img) {
                            $images[] = [
                                'image_id' => $img['image_id'],
                                'url' => $img['url'] ?? '',
                                'file_name' => $img['file_name'] ?? 'Image',
                                'type' => 'image'
                            ];
                        }
                        
                        $hasMore = false;
                    }
                }
                
            } catch (Exception $e) {
                logToFile("Error searching images: " . $e->getMessage());
                
                // Fallback to local storage
                $storageFile = __DIR__ . '/media_storage.json';
                $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
                
                $advertiserImages = array_filter($storage['images'] ?? [], function($img) use ($advertiser_id) {
                    return $img['advertiser_id'] === $advertiser_id;
                });
                
                foreach ($advertiserImages as $img) {
                    $images[] = [
                        'image_id' => $img['image_id'],
                        'url' => $img['url'] ?? '',
                        'file_name' => $img['file_name'] ?? 'Image',
                        'type' => 'image'
                    ];
                }
            }
            
            logToFile("Total images found: " . count($images));
            
            echo json_encode([
                'success' => true,
                'data' => ['list' => $images],
                'message' => count($images) > 0 ? null : 'No images found in TikTok library. Please upload images first.'
            ]);
            break;

        case 'get_videos':
            $file = new File($config);
            logToFile("Get Videos - Advertiser ID: " . $advertiser_id);
            
            $videos = [];
            
            // Read from persistent storage
            $storageFile = __DIR__ . '/media_storage.json';
            $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
            
            // Filter videos for current advertiser
            $advertiserVideos = array_filter($storage['videos'] ?? [], function($vid) use ($advertiser_id) {
                return $vid['advertiser_id'] === $advertiser_id;
            });
            
            if (!empty($advertiserVideos)) {
                // Get video details from TikTok for each stored ID
                $video_ids = array_column($advertiserVideos, 'video_id');
                
                if (!empty($video_ids)) {
                    try {
                        $params = [
                            'advertiser_id' => $advertiser_id,
                            'video_ids' => $video_ids
                        ];
                        
                        // Try using the SDK first
                        $response = $file->getVideoInfo($params);
                        logToFile("Get Video Info SDK Response: " . json_encode($response, JSON_PRETTY_PRINT));
                        
                        // If SDK fails, try direct API call
                        if (!empty($response->code) && $response->code != 0) {
                            logToFile("SDK failed, trying direct API call...");
                            
                            $url = 'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/info/';
                            $queryParams = [
                                'advertiser_id' => $advertiser_id,
                                'video_ids' => json_encode($video_ids)
                            ];
                            
                            $ch = curl_init($url . '?' . http_build_query($queryParams));
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => [
                                    'Access-Token: ' . $accessToken
                                ]
                            ]);
                            
                            $result = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            logToFile("Direct API HTTP Code: " . $httpCode);
                            logToFile("Direct API Response: " . $result);
                            
                            if ($httpCode == 200) {
                                $response = json_decode($result);
                            }
                        }
                        
                        // Check if we got valid response or permission errors
                        if (!empty($response->code) && $response->code == 40001) {
                            // Permission error - just use stored data
                            logToFile("Permission error for videos, using stored data");
                            foreach ($advertiserVideos as $vid) {
                                // Try to generate a thumbnail URL if we have the video ID
                                // Some TikTok videos might have predictable thumbnail URLs
                                $fallbackThumbnail = '';
                                
                                $videos[] = [
                                    'video_id' => $vid['video_id'],
                                    'file_name' => $vid['file_name'] ?? 'Video',
                                    'duration' => $vid['duration'] ?? null,
                                    'size' => $vid['size'] ?? null,
                                    'type' => 'video',
                                    'preview_url' => $fallbackThumbnail,
                                    'thumbnail_url' => $fallbackThumbnail,
                                    'has_thumbnail' => false
                                ];
                            }
                        } elseif (empty($response->code) && isset($response->data->list)) {
                            foreach ($response->data->list as $video) {
                                // Find original filename from storage
                                $originalData = null;
                                foreach ($advertiserVideos as $stored) {
                                    if ($stored['video_id'] == $video->video_id) {
                                        $originalData = $stored;
                                        break;
                                    }
                                }
                                
                                // Extract all possible thumbnail URLs - video_cover_url is what TikTok returns
                                $thumbnailUrl = $video->video_cover_url ?? 
                                               $video->poster_url ?? 
                                               $video->cover_image_url ?? 
                                               $video->cover_url ?? 
                                               $video->thumbnail_url ?? 
                                               $video->preview_url ?? '';
                                
                                $videos[] = [
                                    'video_id' => $video->video_id,
                                    'url' => $video->video_url ?? $video->preview_url ?? '',
                                    'preview_url' => $thumbnailUrl,
                                    'poster_url' => $thumbnailUrl,
                                    'thumbnail_url' => $thumbnailUrl,
                                    'video_cover_url' => $video->video_cover_url ?? '',
                                    'file_name' => $originalData['file_name'] ?? $video->file_name ?? $video->video_name ?? 'Video',
                                    'duration' => $video->duration ?? $originalData['duration'] ?? null,
                                    'width' => $video->width ?? null,
                                    'height' => $video->height ?? null,
                                    'type' => 'video',
                                    'has_thumbnail' => !empty($thumbnailUrl)
                                ];
                            }
                        } else {
                            // If TikTok API fails, use stored data
                            foreach ($advertiserVideos as $vid) {
                                $videos[] = [
                                    'video_id' => $vid['video_id'],
                                    'file_name' => $vid['file_name'] ?? 'Video',
                                    'type' => 'video'
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        logToFile("Error fetching video info: " . $e->getMessage());
                        // Fall back to stored data
                        foreach ($advertiserVideos as $vid) {
                            $videos[] = [
                                'video_id' => $vid['video_id'],
                                'file_name' => $vid['file_name'] ?? 'Video',
                                'type' => 'video'
                            ];
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => ['list' => $videos],
                'message' => count($videos) > 0 ? null : 'Upload videos to see them in library'
            ]);
            break;

        case 'get_campaigns':
            $campaign = new Campaign($config);
            $response = $campaign->getSelf([
                'advertiser_id' => $advertiser_id
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null
            ]);
            break;

        case 'get_adgroups':
            $adGroup = new AdGroup($config);
            $data = $requestData;

            $response = $adGroup->getSelf([
                'advertiser_id' => $advertiser_id,
                'campaign_ids' => $data['campaign_ids'] ?? []
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null
            ]);
            break;

        case 'get_ads':
            $ad = new Ad($config);
            $data = $requestData;

            $response = $ad->getSelf([
                'advertiser_id' => $advertiser_id,
                'adgroup_ids' => $data['adgroup_ids'] ?? []
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null
            ]);
            break;

        case 'publish_ads':
            $ad = new Ad($config);
            $data = $requestData;

            $response = $ad->statusUpdate([
                'advertiser_id' => $advertiser_id,
                'ad_ids' => $data['ad_ids'],
                'operation_status' => 'ENABLE'
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Ads published successfully'
            ]);
            break;

        case 'duplicate_ad':
            $ad = new Ad($config);
            $data = $requestData;

            $originalAd = $ad->getSelf([
                'advertiser_id' => $advertiser_id,
                'ad_ids' => [$data['ad_id']]
            ]);

            if ($originalAd->hasErrors() || empty($originalAd->data->list)) {
                throw new Exception('Original ad not found');
            }

            $originalAdData = $originalAd->data->list[0];

            $params = [
                'advertiser_id' => $advertiser_id,
                'adgroup_id' => $originalAdData->adgroup_id,
                'ad_name' => $data['new_ad_name'] ?? $originalAdData->ad_name . ' (Copy)',
                'ad_format' => $originalAdData->ad_format,
                'ad_text' => $data['ad_text'] ?? $originalAdData->ad_text,
                'call_to_action' => $originalAdData->call_to_action,
                'landing_page_url' => $originalAdData->landing_page_url,
                'identity_id' => $originalAdData->identity_id,
                'identity_type' => $originalAdData->identity_type
            ];

            if (!empty($originalAdData->video_id)) {
                $params['video_id'] = $data['video_id'] ?? $originalAdData->video_id;
            }
            if (!empty($originalAdData->image_ids)) {
                $params['image_ids'] = $data['image_ids'] ?? $originalAdData->image_ids;
            }

            $response = $ad->create($params);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Ad duplicated successfully'
            ]);
            break;

        case 'duplicate_adgroup':
            $adGroup = new AdGroup($config);
            $data = $requestData;

            $originalAdGroup = $adGroup->getSelf([
                'advertiser_id' => $advertiser_id,
                'adgroup_ids' => [$data['adgroup_id']]
            ]);

            if ($originalAdGroup->hasErrors() || empty($originalAdGroup->data->list)) {
                throw new Exception('Original ad group not found');
            }

            $originalData = $originalAdGroup->data->list[0];

            $params = [
                'advertiser_id' => $advertiser_id,
                'campaign_id' => $originalData->campaign_id,
                'adgroup_name' => $data['new_adgroup_name'] ?? $originalData->adgroup_name . ' (Copy)',
                'placement_type' => $originalData->placement_type,
                'placements' => $originalData->placements,
                'location_ids' => $originalData->location_ids,
                'optimization_goal' => $originalData->optimization_goal,
                'billing_event' => $originalData->billing_event,
                'bid_type' => $originalData->bid_type,
                'bid_price' => $data['bid_price'] ?? $originalData->bid_price,
                'budget_mode' => $originalData->budget_mode,
                'budget' => $data['budget'] ?? $originalData->budget,
                'schedule_type' => $originalData->schedule_type,
                'timezone' => $originalData->timezone
            ];

            if (!empty($originalData->dayparting)) {
                $params['dayparting'] = $data['dayparting'] ?? $originalData->dayparting;
            }

            $response = $adGroup->create($params);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Ad group duplicated successfully'
            ]);
            break;

        case 'sync_images_from_tiktok':
            logToFile("Syncing images from TikTok - Advertiser ID: " . $advertiser_id);
            
            $allImages = [];
            $syncedCount = 0;
            
            try {
                // Use the search endpoint to fetch all images from TikTok
                $page = 1;
                $pageSize = 100;
                $hasMore = true;
                
                while ($hasMore && $page <= 20) {
                    $url = "https://business-api.tiktok.com/open_api/v1.3/file/image/ad/search/?" . 
                           "advertiser_id={$advertiser_id}&" .
                           "page={$page}&" .
                           "page_size={$pageSize}";
                    
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => [
                            "Access-Token: " . $accessToken
                        ]
                    ]);
                    
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode == 200) {
                        $response = json_decode($result);
                        
                        if (isset($response->data->list) && is_array($response->data->list)) {
                            foreach ($response->data->list as $image) {
                                $allImages[] = [
                                    'image_id' => $image->image_id,
                                    'url' => $image->image_url ?? '',  // image_url is the correct field
                                    'file_name' => $image->file_name ?? $image->material_name ?? 'Image',
                                    'width' => $image->width ?? null,
                                    'height' => $image->height ?? null,
                                    'format' => $image->format ?? null,
                                    'displayable' => $image->displayable ?? true,
                                    'type' => 'image'
                                ];
                                $syncedCount++;
                            }
                            
                            // Check pagination
                            if (isset($response->data->page_info)) {
                                $totalPage = $response->data->page_info->total_page ?? 1;
                                $currentPage = $response->data->page_info->page ?? $page;
                                $hasMore = $currentPage < $totalPage;
                                $page++;
                            } else {
                                $hasMore = false;
                            }
                        } else {
                            $hasMore = false;
                        }
                    } else {
                        throw new Exception("Failed to fetch images: HTTP {$httpCode}");
                    }
                }
                
                logToFile("Synced {$syncedCount} images from TikTok");
                
                echo json_encode([
                    'success' => true,
                    'data' => ['images' => $allImages, 'count' => $syncedCount],
                    'message' => "Found {$syncedCount} images in TikTok library"
                ]);
                
            } catch (Exception $e) {
                logToFile("Error syncing images: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to sync images: ' . $e->getMessage()
                ]);
            }
            break;

        case 'sync_tiktok_library':
            // Sync with TikTok's actual media library
            logToFile("Syncing TikTok media library for advertiser: " . $advertiser_id);
            
            $storageFile = __DIR__ . '/media_storage.json';
            $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
            
            // Search for videos using TikTok's search endpoint
            $url = 'https://business-api.tiktok.com/open_api/v1.3/file/video/ad/search/';
            $params = http_build_query([
                'advertiser_id' => $advertiser_id,
                'page' => 1,
                'page_size' => 100
            ]);
            
            $ch = curl_init($url . '?' . $params);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Access-Token: ' . $config['access_token']
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            $videoCount = 0;
            
            if ($httpCode == 200 && isset($result['data']['list'])) {
                foreach ($result['data']['list'] as $video) {
                    // Check if already exists
                    $exists = false;
                    foreach ($storage['videos'] as $stored) {
                        if ($stored['video_id'] === $video['video_id'] && $stored['advertiser_id'] === $advertiser_id) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $storage['videos'][] = [
                            'video_id' => $video['video_id'],
                            'file_name' => $video['video_name'] ?? $video['file_name'] ?? 'Video',
                            'duration' => $video['duration'] ?? null,
                            'size' => $video['size'] ?? null,
                            'upload_time' => time(),
                            'advertiser_id' => $advertiser_id
                        ];
                        $videoCount++;
                    }
                }
            }
            
            file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'message' => "Synced $videoCount new videos from TikTok",
                'total_videos' => count(array_filter($storage['videos'], function($v) use ($advertiser_id) {
                    return $v['advertiser_id'] === $advertiser_id;
                }))
            ]);
            break;
            
        case 'add_existing_media':
            // Allow manual addition of existing TikTok media IDs
            $data = $requestData;
            
            $storageFile = __DIR__ . '/media_storage.json';
            $storage = json_decode(file_get_contents($storageFile), true) ?? ['images' => [], 'videos' => []];
            
            if (!empty($data['video_ids'])) {
                foreach ($data['video_ids'] as $video_id) {
                    $storage['videos'][] = [
                        'video_id' => $video_id,
                        'file_name' => $data['file_names'][$video_id] ?? 'Video',
                        'upload_time' => time(),
                        'advertiser_id' => $advertiser_id
                    ];
                }
            }
            
            if (!empty($data['image_ids'])) {
                foreach ($data['image_ids'] as $image_id) {
                    $storage['images'][] = [
                        'image_id' => $image_id,
                        'file_name' => $data['file_names'][$image_id] ?? 'Image',
                        'upload_time' => time(),
                        'advertiser_id' => $advertiser_id
                    ];
                }
            }
            
            file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'message' => 'Media IDs added successfully'
            ]);
            break;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        case 'get_debug_logs':
            // Return recent log entries for debugging
            $logFile = __DIR__ . '/api_debug.log';
            if (file_exists($logFile)) {
                $logs = file_get_contents($logFile);
                // Get last 50 lines
                $lines = explode("\n", $logs);
                $lastLines = array_slice($lines, -50);
                echo json_encode([
                    'success' => true,
                    'logs' => implode("\n", $lastLines)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No log file found'
                ]);
            }
            exit;
            
        default:
            logToFile("Unknown action received: " . $action);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action: ' . $action
            ]);
    }

} catch (Exception $e) {
    logToFile("EXCEPTION: " . $e->getMessage());
    logToFile("Stack Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>