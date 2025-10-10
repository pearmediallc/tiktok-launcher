<?php
// Disable error display to prevent HTML errors in JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

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

$advertiser_id = $_ENV['TIKTOK_ADVERTISER_ID'] ?? '';

// Logging function
function logToFile($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    error_log($logMessage);  // This will appear in Render logs
    file_put_contents(__DIR__ . '/api_debug.log', $logMessage, FILE_APPEND);
}

// Handle API requests
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Log incoming request
$requestData = json_decode(file_get_contents('php://input'), true);
logToFile("============ INCOMING REQUEST ============");
logToFile("Action: {$action}");
logToFile("Request Data: " . json_encode($requestData, JSON_PRETTY_PRINT));

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'create_campaign':
            $campaign = new Campaign($config);
            $data = json_decode(file_get_contents('php://input'), true);

            $params = [
                'advertiser_id' => $advertiser_id,
                'campaign_name' => $data['campaign_name'],
                'objective_type' => 'WEB_CONVERSIONS',  // For website form conversions
                'budget_mode' => $data['budget_mode'] ?? 'BUDGET_MODE_DAY',  // User selectable
                'budget' => floatval($data['budget']),  // User provided
                'operation_status' => 'ENABLE'  // Enable campaign immediately
            ];

            // Add schedule parameters if provided
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
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate datetime format helper
            function is_valid_datetime($s) {
                return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s);
            }

            // Generate 168-char dayparting string
            function generate_time_series($startHour, $endHour, $days = [0,1,2,3,4,5,6]) {
                $ts = '';
                for ($d = 0; $d < 7; $d++) {
                    for ($h = 0; $h < 24; $h++) {
                        if (in_array($d, $days) && $h >= $startHour && $h < $endHour) {
                            $ts .= '1';
                        } else {
                            $ts .= '0';
                        }
                    }
                }
                return $ts;
            }

            // ========== VALIDATION BASED ON TIKTOK API DOCS ==========

            // 1. REQUIRED FIELDS
            $required_fields = ['campaign_id', 'adgroup_name', 'placement_type', 'placements',
                                'promotion_type', 'optimization_goal', 'billing_event', 'budget_mode', 'budget'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Required field missing: {$field}"]);
                    exit;
                }
            }

            // 2. BUDGET MODE VALIDATION
            // If budget_mode is BUDGET_MODE_TOTAL, schedule_end_time is REQUIRED
            if ($data['budget_mode'] === 'BUDGET_MODE_TOTAL') {
                if (empty($data['schedule_end_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'schedule_end_time is required when budget_mode is BUDGET_MODE_TOTAL']);
                    exit;
                }
            }

            // 3. PIXEL_ID VALIDATION
            // pixel_id is REQUIRED when optimization_goal is CONVERT or VALUE
            if (in_array($data['optimization_goal'], ['CONVERT', 'VALUE'])) {
                if (empty($data['pixel_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'pixel_id is required when optimization_goal is CONVERT or VALUE']);
                    exit;
                }
            }

            // 4. MESSAGING APP VALIDATION
            // For LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE promotion type
            if ($data['promotion_type'] === 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE') {
                if (empty($data['messaging_app_type'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'messaging_app_type is required for LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE']);
                    exit;
                }

                // If optimization_goal is CONVERSATION, messaging_app_type cannot be ZALO, LINE, or IM_URL
                if ($data['optimization_goal'] === 'CONVERSATION') {
                    if (in_array($data['messaging_app_type'], ['ZALO', 'LINE', 'IM_URL'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'When optimization_goal is CONVERSATION, messaging_app_type cannot be ZALO, LINE, or IM_URL']);
                        exit;
                    }

                    // message_event_set_id may be required
                    if (empty($data['message_event_set_id']) && empty($data['messaging_app_account_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'message_event_set_id or messaging_app_account_id is required when optimization_goal is CONVERSATION']);
                        exit;
                    }
                }
            }

            // Build params from frontend - all values configurable
            $params = [
                'advertiser_id'      => $advertiser_id,
                'campaign_id'        => $data['campaign_id'],
                'adgroup_name'       => $data['adgroup_name'],

                // OPTIMIZATION
                'promotion_type'     => $data['promotion_type'],
                'optimization_goal'  => $data['optimization_goal'],
                'billing_event'      => $data['billing_event'],

                // PLACEMENTS
                'placement_type'     => $data['placement_type'],
                'placements'         => $data['placements'],

                // BUDGET AND SCHEDULE
                'budget_mode'        => $data['budget_mode'],
                'budget'             => floatval($data['budget']),
                'bid_type'           => $data['bid_type'],

                // TARGETING
                'location_ids'       => $data['location_ids'] ?? ['6252001'],
            ];

            // Add bid price if provided
            if (!empty($data['conversion_bid_price']) && floatval($data['conversion_bid_price']) > 0) {
                $params['conversion_bid_price'] = floatval($data['conversion_bid_price']);
            } elseif (!empty($data['bid']) && floatval($data['bid']) > 0) {
                $params['bid'] = floatval($data['bid']);
            }

            // Add optional fields if present
            if (!empty($data['lead_gen_form_id'])) {
                $params['lead_gen_form_id'] = $data['lead_gen_form_id'];
            }
            if (!empty($data['pixel_id'])) {
                // Log pixel ID for debugging
                error_log("AdGroup Creation - Pixel ID received: " . $data['pixel_id'] . " (type: " . gettype($data['pixel_id']) . ")");

                // Ensure pixel_id is a string (TikTok expects string)
                $params['pixel_id'] = strval($data['pixel_id']);

                error_log("AdGroup Creation - Pixel ID after conversion: " . $params['pixel_id']);
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
            // Attribution settings
            if (!empty($data['click_attribution_window'])) {
                $params['click_attribution_window'] = $data['click_attribution_window'];
            }
            if (!empty($data['view_attribution_window'])) {
                $params['view_attribution_window'] = $data['view_attribution_window'];
            }
            if (!empty($data['attribution_event_count'])) {
                $params['attribution_event_count'] = $data['attribution_event_count'];
            }
            // Demographics
            if (!empty($data['age_groups'])) {
                $params['age_groups'] = $data['age_groups'];
            }
            if (!empty($data['gender'])) {
                $params['gender'] = $data['gender'];
            }
            // Pacing
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

            // --- Scheduling ---
            if (!empty($data['schedule_type'])) {
                $params['schedule_type'] = $data['schedule_type'];
            }

            // Add schedule_start_time if provided
            if (!empty($data['schedule_start_time'])) {
                // Validate format
                if (!is_valid_datetime($data['schedule_start_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'schedule_start_time must be YYYY-MM-DD HH:MM:SS (UTC)']);
                    exit;
                }
                $params['schedule_start_time'] = $data['schedule_start_time'];
            }

            // Add schedule_end_time if provided
            if (!empty($data['schedule_end_time'])) {
                // Validate format
                if (!is_valid_datetime($data['schedule_end_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'schedule_end_time must be YYYY-MM-DD HH:MM:SS (UTC)']);
                    exit;
                }
                $params['schedule_end_time'] = $data['schedule_end_time'];
            }

            // --- Dayparting ---
            if (!empty($data['dayparting'])) {
                if (strlen($data['dayparting']) !== 168) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'dayparting must be 168-char binary string']);
                    exit;
                }
                $params['time_series_type'] = 'CUSTOMIZED';
                $params['time_series']      = $data['dayparting'];
            } elseif (isset($data['daypart_start_hour']) && isset($data['daypart_end_hour'])) {
                $params['time_series_type'] = 'CUSTOMIZED';
                $params['time_series']      = generate_time_series(
                    intval($data['daypart_start_hour']),
                    intval($data['daypart_end_hour']),
                    $data['daypart_days'] ?? [0,1,2,3,4,5,6]
                );
            }

            // --- Call TikTok API ---
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
            $data = json_decode(file_get_contents('php://input'), true);

            $params = [
                'advertiser_id' => $advertiser_id,
                'adgroup_id' => $data['adgroup_id'],
                'ad_name' => $data['ad_name'],
                'ad_format' => $data['ad_format'] ?? 'SINGLE_VIDEO',
                'ad_text' => $data['ad_text'],
                'call_to_action' => $data['call_to_action'] ?? 'APPLY_NOW',
                'landing_page_url' => $data['landing_page_url'],
                'identity_id' => $data['identity_id'],
                'identity_type' => 'CUSTOMIZED_USER',
                'is_smart_creative' => false
            ];

            // Add creative (video or image)
            if (!empty($data['video_id'])) {
                $params['video_id'] = $data['video_id'];
            } elseif (!empty($data['image_ids'])) {
                $params['image_ids'] = $data['image_ids'];
            }

            // Add tracking parameters
            if (!empty($data['tracking_url'])) {
                $params['tracking_pixel_id'] = $data['tracking_url'];
            }

            $response = $ad->create($params);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Ad created successfully'
            ]);
            break;

        case 'upload_image':
            $file = new File($config);

            if (!isset($_FILES['image'])) {
                throw new Exception('No image file provided');
            }

            $fileName = $_FILES['image']['name'];
            $imageSignature = md5_file($_FILES['image']['tmp_name']);

            logToFile("Image Upload - File: " . $fileName);
            logToFile("Image Upload - Advertiser ID: " . $advertiser_id);
            logToFile("Image Upload - Signature: " . $imageSignature);

            $params = [
                'advertiser_id' => $advertiser_id,
                'file_name' => $fileName,
                'image_file' => new CURLFile($_FILES['image']['tmp_name'], $_FILES['image']['type'], $fileName),
                'image_signature' => $imageSignature
            ];

            logToFile("Image Upload Params: " . json_encode(array_diff_key($params, ['image_file' => '']), JSON_PRETTY_PRINT));

            $response = $file->uploadImage($params);

            logToFile("Image Upload Response: " . json_encode($response, JSON_PRETTY_PRINT));

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Image uploaded successfully',
                'code' => $response->code ?? null
            ]);
            break;

        case 'upload_video':
            $file = new File($config);

            if (!isset($_FILES['video'])) {
                throw new Exception('No video file provided');
            }

            $fileName = $_FILES['video']['name'];
            $videoSignature = md5_file($_FILES['video']['tmp_name']);

            logToFile("Video Upload - File: " . $fileName);
            logToFile("Video Upload - Advertiser ID: " . $advertiser_id);
            logToFile("Video Upload - Signature: " . $videoSignature);

            $params = [
                'advertiser_id' => $advertiser_id,
                'file_name' => $fileName,
                'upload_type' => 'UPLOAD_BY_FILE',
                'video_file' => new CURLFile($_FILES['video']['tmp_name'], $_FILES['video']['type'], $fileName),
                'video_signature' => $videoSignature,
                'flaw_detect' => 'true',
                'auto_fix_enabled' => 'true',
                'auto_bind_enabled' => 'true'
            ];

            logToFile("Video Upload Params: " . json_encode(array_diff_key($params, ['video_file' => '']), JSON_PRETTY_PRINT));

            $response = $file->uploadVideo($params);

            logToFile("Video Upload Response: " . json_encode($response, JSON_PRETTY_PRINT));

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Video uploaded successfully',
                'code' => $response->code ?? null
            ]);
            break;

        case 'get_identities':
            $identity = new Identity($config);
            $response = $identity->getSelf([
                'advertiser_id' => $advertiser_id
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null
            ]);
            break;

        case 'get_pixels':
            $tools = new Tools($config);
            $response = $tools->getPixels([
                'advertiser_id' => $advertiser_id
            ]);

            logToFile("Get Pixels Response: " . json_encode($response, JSON_PRETTY_PRINT));
            logToFile("Response Code: " . ($response->code ?? 'null'));
            logToFile("Response Message: " . ($response->message ?? 'null'));

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? null,
                'code' => $response->code ?? null
            ]);
            break;

        case 'get_images':
            // Note: TikTok API requires image_ids to get image info
            // For now, return empty array - media is tracked after upload
            logToFile("Get Images - Advertiser ID: " . $advertiser_id);

            echo json_encode([
                'success' => true,
                'data' => ['list' => []],
                'message' => 'Image library - upload images to populate'
            ]);
            break;

        case 'get_videos':
            // Note: TikTok API requires video_ids to get video info
            // For now, return empty array - media is tracked after upload
            logToFile("Get Videos - Advertiser ID: " . $advertiser_id);

            echo json_encode([
                'success' => true,
                'data' => ['list' => []],
                'message' => 'Video library - upload videos to populate'
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
            $data = json_decode(file_get_contents('php://input'), true);

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
            $data = json_decode(file_get_contents('php://input'), true);

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
            $data = json_decode(file_get_contents('php://input'), true);

            $response = $ad->statusUpdate([
                'advertiser_id' => $advertiser_id,
                'ad_ids' => $data['ad_ids'],
                'opt_status' => 'ENABLE'
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Ads published successfully'
            ]);
            break;

        case 'duplicate_ad':
            $ad = new Ad($config);
            $data = json_decode(file_get_contents('php://input'), true);

            // Get the original ad details
            $originalAd = $ad->getSelf([
                'advertiser_id' => $advertiser_id,
                'ad_ids' => [$data['ad_id']]
            ]);

            if ($originalAd->hasErrors() || empty($originalAd->data->list)) {
                throw new Exception('Original ad not found');
            }

            $originalAdData = $originalAd->data->list[0];

            // Create new ad with same settings
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
            $data = json_decode(file_get_contents('php://input'), true);

            // Get the original adgroup details
            $originalAdGroup = $adGroup->getSelf([
                'advertiser_id' => $advertiser_id,
                'adgroup_ids' => [$data['adgroup_id']]
            ]);

            if ($originalAdGroup->hasErrors() || empty($originalAdGroup->data->list)) {
                throw new Exception('Original ad group not found');
            }

            $originalData = $originalAdGroup->data->list[0];

            // Create new ad group with same settings
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

        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
