<?php
session_start();

// Check authentication
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TikTok Campaign Launcher - Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>🚀 TikTok Campaign Launcher</h1>
            <button class="btn-logout" onclick="logout()">Logout</button>
        </header>

        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">Campaign</div>
            </div>
            <div class="step" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">Ad Group</div>
            </div>
            <div class="step" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">Ads</div>
            </div>
            <div class="step" data-step="4">
                <div class="step-number">4</div>
                <div class="step-label">Review & Publish</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Step 1: Campaign Creation -->
            <div class="step-content active" id="step-1">
                <h2>Create Campaign</h2>
                <div class="form-group">
                    <label>Campaign Name</label>
                    <input type="text" id="campaign-name" placeholder="Enter campaign name" required>
                </div>

                <div class="form-section">
                    <h3>Schedule</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date & Time (UTC)</label>
                            <input type="datetime-local" id="campaign-start-date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date & Time (UTC)</label>
                            <input type="datetime-local" id="campaign-end-date">
                        </div>
                    </div>
                </div>

                <div class="form-info">
                    <p><strong>Objective:</strong> Lead Generation</p>
                    <p><strong>Type:</strong> Manual Campaign</p>
                </div>
                <button class="btn-primary" onclick="createCampaign()">Continue to Ad Group →</button>
            </div>

            <!-- Step 2: Ad Group Creation -->
            <div class="step-content" id="step-2">
                <h2>Create Ad Group</h2>
                <div class="form-info" style="margin-bottom: 20px; background: #e8f5e9; padding: 12px; border-radius: 6px;">
                    <p><strong>Campaign ID:</strong> <span id="display-campaign-id">-</span></p>
                </div>
                <div class="form-group">
                    <label>Ad Group Name</label>
                    <input type="text" id="adgroup-name" placeholder="Enter ad group name" required>
                </div>

                <div class="form-section">
                    <h3>Optimization & Location</h3>
                    <div class="form-info">
                        <p><strong>Promotion Type:</strong> Website</p>
                        <p><strong>Optimization Goal:</strong> Conversion</p>
                        <p><strong>Event:</strong> Lead</p>
                        <p><strong>Location:</strong> United States</p>
                        <p><strong>Placement:</strong> TikTok</p>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Budget & Schedule</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Daily Budget ($)</label>
                            <input type="number" id="budget" placeholder="50" min="20" required>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="datetime-local" id="start-date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Timezone</label>
                        <select id="timezone">
                            <option value="America/Panama">UTC -5:00 East Standard Time (Panama)</option>
                            <option value="America/New_York">UTC -5:00 Eastern Time (New York)</option>
                            <option value="America/Chicago">UTC -6:00 Central Time (Chicago)</option>
                            <option value="America/Denver">UTC -7:00 Mountain Time (Denver)</option>
                            <option value="America/Los_Angeles">UTC -8:00 Pacific Time (Los Angeles)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="enable-dayparting" onchange="toggleDayparting()">
                            Enable Dayparting (Select specific hours)
                        </label>
                    </div>

                    <div id="dayparting-section" style="display: none;">
                        <div class="dayparting-grid">
                            <table class="dayparting-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th colspan="24">Hours (0-23)</th>
                                    </tr>
                                </thead>
                                <tbody id="dayparting-body">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Bid Amount ($)</label>
                        <input type="number" id="bid-price" placeholder="1.00" step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="button-row">
                    <button class="btn-secondary" onclick="prevStep()">← Back</button>
                    <button class="btn-primary" onclick="createAdGroup()">Continue to Ads →</button>
                </div>
            </div>

            <!-- Step 3: Ads Creation -->
            <div class="step-content" id="step-3">
                <h2>Create Ads</h2>

                <div class="ads-container" id="ads-container">
                    <!-- Ad forms will be dynamically added here -->
                </div>

                <div class="button-row">
                    <button class="btn-secondary" onclick="duplicateAd()">+ Duplicate Last Ad</button>
                </div>

                <div class="button-row" style="margin-top: 20px;">
                    <button class="btn-secondary" onclick="prevStep()">← Back</button>
                    <button class="btn-primary" onclick="reviewAds()">Review & Publish →</button>
                </div>
            </div>

            <!-- Step 4: Review & Publish -->
            <div class="step-content" id="step-4">
                <h2>Review & Publish</h2>

                <div class="review-section">
                    <h3>Campaign Summary</h3>
                    <div id="campaign-summary" class="summary-card">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="review-section">
                    <h3>Ad Group Summary</h3>
                    <div id="adgroup-summary" class="summary-card">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="review-section">
                    <h3>Ads Summary</h3>
                    <div id="ads-summary" class="summary-list">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="button-row">
                    <button class="btn-secondary" onclick="prevStep()">← Back</button>
                    <button class="btn-success" onclick="publishAll()">✓ Publish All</button>
                </div>
            </div>
        </div>

        <!-- Media Library Modal -->
        <div id="media-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Select Media</h3>
                    <span class="modal-close" onclick="closeMediaModal()">&times;</span>
                </div>
                <div class="modal-tabs">
                    <button class="tab-btn active" onclick="switchMediaTab('library')">Library</button>
                    <button class="tab-btn" onclick="switchMediaTab('upload')">Upload New</button>
                </div>
                <div class="modal-body">
                    <div id="media-library-tab" class="media-tab active">
                        <div class="media-grid" id="media-grid">
                            <!-- Media items will be loaded here -->
                        </div>
                    </div>
                    <div id="media-upload-tab" class="media-tab">
                        <div class="upload-area" id="upload-area">
                            <input type="file" id="media-file-input" accept="image/*,video/*" onchange="handleMediaUpload(event)">
                            <label for="media-file-input">
                                <div class="upload-icon">📁</div>
                                <p>Click to upload or drag and drop</p>
                                <p class="upload-hint">Images or Videos</p>
                            </label>
                        </div>
                        <div id="upload-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill" id="upload-progress-fill"></div>
                            </div>
                            <p id="upload-status">Uploading...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="closeMediaModal()">Cancel</button>
                    <button class="btn-primary" onclick="confirmMediaSelection()">Select</button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="loading-overlay">
            <div class="spinner"></div>
            <p>Processing...</p>
        </div>

        <!-- Toast Notification -->
        <div id="toast" class="toast"></div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
