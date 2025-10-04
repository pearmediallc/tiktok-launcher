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
                'objective_type' => 'LEAD_GENERATION',
                'budget_mode' => 'BUDGET_MODE_INFINITE'
            ];

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

            // --- REQUIRED fields per TikTok API (exact format from curl example) ---
            $params = [
                'advertiser_id'         => $advertiser_id,
                'campaign_id'           => $data['campaign_id'],
                'adgroup_name'          => $data['adgroup_name'],
                'promotion_type'        => 'WEBSITE',
                'placement_type'        => 'PLACEMENT_TYPE_NORMAL',
                'placements'            => ['PLACEMENT_TIKTOK'],
                'video_download_disabled' => false,
                'location_ids'          => ['6252001'],
                'gender'                => 'GENDER_UNLIMITED',
                'operating_systems'     => ['ANDROID'],
                'budget_mode'           => 'BUDGET_MODE_TOTAL',
                'budget'                => floatval($data['budget'] ?? 100),
                'optimization_goal'     => 'CLICK',
                'bid_type'              => 'BID_TYPE_NO_BID',
                'billing_event'         => 'CPC',
                'pacing'                => 'PACING_MODE_SMOOTH',
                'operation_status'      => 'ENABLE',
            ];

            // --- Scheduling ---
            if (!empty($data['schedule_start_time']) && !empty($data['schedule_end_time'])) {
                // Validate format
                if (!is_valid_datetime($data['schedule_start_time']) || !is_valid_datetime($data['schedule_end_time'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Datetime must be YYYY-MM-DD HH:MM:SS (UTC)']);
                    exit;
                }
                $params['schedule_type'] = 'SCHEDULE_START_END';
                $params['schedule_start_time'] = $data['schedule_start_time']; // "2025-10-04 00:00:00"
                $params['schedule_end_time']   = $data['schedule_end_time'];   // "2025-10-10 23:59:59"
            } else {
                $params['schedule_type'] = 'SCHEDULE_FROM_NOW';
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

            $response = $file->uploadImage([
                'advertiser_id' => $advertiser_id,
                'image_file' => new CURLFile($_FILES['image']['tmp_name'], $_FILES['image']['type'], $_FILES['image']['name']),
                'upload_type' => 'UPLOAD_BY_FILE'
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Image uploaded successfully'
            ]);
            break;

        case 'upload_video':
            $file = new File($config);

            if (!isset($_FILES['video'])) {
                throw new Exception('No video file provided');
            }

            $response = $file->uploadVideo([
                'advertiser_id' => $advertiser_id,
                'video_file' => new CURLFile($_FILES['video']['tmp_name'], $_FILES['video']['type'], $_FILES['video']['name']),
                'upload_type' => 'UPLOAD_BY_FILE'
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null,
                'message' => $response->message ?? 'Video uploaded successfully'
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

        case 'get_images':
            $file = new File($config);
            $response = $file->getImageInfo([
                'advertiser_id' => $advertiser_id
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null
            ]);
            break;

        case 'get_videos':
            $file = new File($config);
            $response = $file->getVideoInfo([
                'advertiser_id' => $advertiser_id
            ]);

            echo json_encode([
                'success' => empty($response->code),
                'data' => $response->data ?? null
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
