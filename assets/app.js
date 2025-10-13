// Global state
let state = {
    currentStep: 1,
    campaignId: null,
    adGroupId: null,
    ads: [],
    identities: [],
    mediaLibrary: [],
    selectedMedia: [],
    currentAdIndex: null,
    mediaSelectionMode: 'multiple' // Allow multiple selection
};

// API Logger Functions
function addLog(type, message, details = null) {
    const logsContent = document.getElementById('logs-content');
    const logEntry = document.createElement('div');
    logEntry.className = `log-entry log-${type}`;

    const now = new Date();
    const time = now.toTimeString().split(' ')[0];

    let typeLabel = '';
    if (type === 'request' || type === 'response' || type === 'error') {
        typeLabel = `<span class="log-type ${type}">${type.toUpperCase()}</span>`;
    }

    logEntry.innerHTML = `
        <span class="log-time">${time}</span>
        ${typeLabel}
        <span class="log-message">${message}</span>
    `;

    if (details) {
        const detailsDiv = document.createElement('div');
        detailsDiv.className = 'log-details';
        detailsDiv.innerHTML = `<pre>${JSON.stringify(details, null, 2)}</pre>`;
        logEntry.appendChild(detailsDiv);
    }

    logsContent.appendChild(logEntry);
    logsContent.scrollTop = logsContent.scrollHeight;
}

function clearLogs() {
    const logsContent = document.getElementById('logs-content');
    logsContent.innerHTML = `
        <div class="log-entry log-info">
            <span class="log-time">${new Date().toTimeString().split(' ')[0]}</span>
            <span class="log-message">Logs cleared - Ready for new requests</span>
        </div>
    `;
}

function toggleLogsPanel() {
    const logsPanel = document.getElementById('logs-panel');
    const toggleIcon = document.getElementById('logs-toggle-icon');
    const toggleBtn = document.querySelector('.btn-toggle-logs');

    logsPanel.classList.toggle('collapsed');

    if (logsPanel.classList.contains('collapsed')) {
        toggleIcon.textContent = '▲ Show Logs';
        toggleBtn.textContent = '▲';
    } else {
        toggleIcon.textContent = '▼ Hide Logs';
        toggleBtn.textContent = '▼';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDayparting();
    loadIdentities();
    loadMediaLibrary();
    loadPixels();  // Load available pixels
    addFirstAd();

    // Set default start date to tomorrow for both campaign and ad group
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);

    // Campaign start date
    if (document.getElementById('campaign-start-date')) {
        document.getElementById('campaign-start-date').value = formatDateTimeLocal(tomorrow);
    }

    // Ad group start date
    if (document.getElementById('start-date')) {
        document.getElementById('start-date').value = formatDateTimeLocal(tomorrow);
    }
});

// Format date for datetime-local input
function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Initialize dayparting grid
function initializeDayparting() {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const tbody = document.getElementById('dayparting-body');

    days.forEach((day, dayIndex) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><strong>${day}</strong></td>`;

        for (let hour = 0; hour < 24; hour++) {
            const td = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'hour-checkbox';
            checkbox.dataset.day = dayIndex;
            checkbox.dataset.hour = hour;
            // Only check Monday (index 1) by default
            checkbox.checked = (dayIndex === 1);
            td.appendChild(checkbox);
            tr.appendChild(td);
        }

        tbody.appendChild(tr);
    });
}

// Toggle dayparting section
function toggleDayparting() {
    const enabled = document.getElementById('enable-dayparting').checked;
    document.getElementById('dayparting-section').style.display = enabled ? 'block' : 'none';
}

// Get dayparting data
function getDaypartingData() {
    if (!document.getElementById('enable-dayparting').checked) {
        return null;
    }

    // TikTok format: 168 characters (7 days × 24 hours)
    // Each character represents 1 hour slot
    // '1' = enabled, '0' = disabled
    let dayparting = '';

    // Days: 0=Monday, 1=Tuesday, ..., 6=Sunday (TikTok ordering)
    // Our UI: 0=Sunday, 1=Monday, ..., 6=Saturday
    // Need to reorder: [1,2,3,4,5,6,0] to convert Sunday-Saturday to Monday-Sunday
    const dayOrder = [1, 2, 3, 4, 5, 6, 0]; // Mon, Tue, Wed, Thu, Fri, Sat, Sun

    for (let i = 0; i < 7; i++) {
        const day = dayOrder[i];
        for (let hour = 0; hour < 24; hour++) {
            const checkbox = document.querySelector(`.hour-checkbox[data-day="${day}"][data-hour="${hour}"]`);
            dayparting += (checkbox && checkbox.checked) ? '1' : '0';
        }
    }

    // Must be exactly 168 characters (7 × 24)
    return dayparting.length === 168 ? dayparting : null;
}

// Step navigation
function nextStep() {
    if (state.currentStep < 4) {
        document.querySelector(`.step[data-step="${state.currentStep}"]`).classList.remove('active');
        document.querySelector(`.step[data-step="${state.currentStep}"]`).classList.add('completed');
        document.getElementById(`step-${state.currentStep}`).classList.remove('active');

        state.currentStep++;

        document.querySelector(`.step[data-step="${state.currentStep}"]`).classList.add('active');
        document.getElementById(`step-${state.currentStep}`).classList.add('active');

        window.scrollTo(0, 0);
    }
}

function prevStep() {
    if (state.currentStep > 1) {
        document.querySelector(`.step[data-step="${state.currentStep}"]`).classList.remove('active');
        document.getElementById(`step-${state.currentStep}`).classList.remove('active');

        state.currentStep--;

        document.querySelector(`.step[data-step="${state.currentStep}"]`).classList.remove('completed');
        document.querySelector(`.step[data-step="${state.currentStep}"]`).classList.add('active');
        document.getElementById(`step-${state.currentStep}`).classList.add('active');

        window.scrollTo(0, 0);
    }
}

// Campaign creation
async function createCampaign() {
    const campaignName = document.getElementById('campaign-name').value.trim();
    const campaignBudgetMode = document.getElementById('campaign-budget-mode').value;
    const campaignBudget = parseFloat(document.getElementById('campaign-budget').value);
    const startDate = document.getElementById('campaign-start-date').value;
    const endDate = document.getElementById('campaign-end-date').value;

    if (!campaignName || !campaignBudget || !startDate) {
        showToast('Please enter campaign name, budget, and start date', 'error');
        return;
    }

    if (campaignBudget < 20) {
        showToast('Minimum campaign budget is $20', 'error');
        return;
    }

    showLoading();

    try {
        // Convert datetime to TikTok format: "YYYY-MM-DD HH:MM:SS" in UTC
        const startDateTime = new Date(startDate);
        const scheduleStartTime = formatToTikTokDateTime(startDateTime);

        const params = {
            campaign_name: campaignName,
            budget_mode: campaignBudgetMode,
            budget: campaignBudget,
            schedule_start_time: scheduleStartTime
        };

        // Store budget mode for ad group to use
        state.campaignBudgetMode = campaignBudgetMode;

        // Add end time if provided
        if (endDate) {
            const endDateTime = new Date(endDate);
            params.schedule_end_time = formatToTikTokDateTime(endDateTime);
        }

        const response = await apiRequest('create_campaign', params);

        if (response.success && response.data && response.data.campaign_id) {
            state.campaignId = response.data.campaign_id;
            // Display campaign ID on ad group step
            document.getElementById('display-campaign-id').textContent = state.campaignId;
            showToast('Campaign created successfully', 'success');
            nextStep();
        } else {
            showToast(response.message || 'Failed to create campaign', 'error');
        }
    } catch (error) {
        showToast('Error creating campaign: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Helper function to format date to TikTok format
function formatToTikTokDateTime(date) {
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    const hours = String(date.getUTCHours()).padStart(2, '0');
    const minutes = String(date.getUTCMinutes()).padStart(2, '0');
    const seconds = '00';
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// Ad Group creation
async function createAdGroup() {
    const adGroupName = document.getElementById('adgroup-name').value.trim();

    // Get pixel ID from either dropdown or manual input based on selection
    const pixelMethodRadio = document.querySelector('input[name="pixel-method"]:checked');
    const pixelMethod = pixelMethodRadio ? pixelMethodRadio.value : 'dropdown';
    const pixelId = pixelMethod === 'manual'
        ? document.getElementById('pixel-manual-input').value.trim()
        : document.getElementById('lead-gen-form-id').value.trim();

    const budgetMode = document.getElementById('budget-mode').value;
    const budget = parseFloat(document.getElementById('budget').value);
    const startDate = document.getElementById('start-date').value;
    const bidPrice = parseFloat(document.getElementById('bid-price').value);

    console.log('=== AD GROUP CREATION DEBUG ===');
    console.log('Pixel Method:', pixelMethod);
    console.log('Pixel ID:', pixelId);
    console.log('Pixel ID type:', typeof pixelId);
    console.log('Pixel ID length:', pixelId ? pixelId.length : 0);
    console.log('Dropdown value:', document.getElementById('lead-gen-form-id').value);
    console.log('Manual input value:', document.getElementById('pixel-manual-input').value);
    console.log('================================');

    if (!adGroupName || !pixelId || !budget || !startDate || !bidPrice) {
        showToast('Please fill in all required fields (including pixel ID)', 'error');
        console.error('Missing fields - Pixel ID:', pixelId);
        return;
    }

    // Validate pixel_id is numeric
    if (!/^\d+$/.test(pixelId)) {
        showToast('Pixel ID must be numeric (e.g., 1234567890)', 'error');
        console.error('Invalid pixel ID format:', pixelId);
        return;
    }

    if (budget < 20) {
        showToast('Minimum budget is $20', 'error');
        return;
    }

    showLoading();

    try {
        // Convert datetime to TikTok format: "YYYY-MM-DD HH:MM:SS" in UTC
        const startDateTime = new Date(startDate);
        const scheduleStartTime = formatToTikTokDateTime(startDateTime);

        // Based on TikTok screenshots: Complete ad group configuration
        const params = {
            // BASIC INFO
            campaign_id: state.campaignId,
            adgroup_name: adGroupName,

            // OPTIMIZATION (Website Form - Submit form event)
            promotion_type: 'WEBSITE',  // Website conversion
            promotion_target_type: 'EXTERNAL_WEBSITE',  // Website Form
            pixel_id: pixelId,  // Data connection pixel
            optimization_event: 'FORM',  // Form submission event - EXACTLY as Postman
            optimization_goal: 'CONVERT',  // Conversion goal (matches WEB_CONVERSIONS)
            billing_event: 'OCPM',

            // ATTRIBUTION SETTINGS
            click_attribution_window: 'SEVEN_DAYS',  // 7-day click
            view_attribution_window: 'ONE_DAY',  // 1-day view
            attribution_event_count: 'EVERY',  // Event count: Every

            // PLACEMENTS
            placement_type: 'PLACEMENT_TYPE_NORMAL',  // Select placement
            placements: ['PLACEMENT_TIKTOK'],  // TikTok only

            // DEMOGRAPHICS - TARGETING
            location_ids: ['6252001'],  // United States
            age_groups: ['AGE_18_24', 'AGE_25_34', 'AGE_35_44', 'AGE_45_54', 'AGE_55_100'],
            gender: 'GENDER_UNLIMITED',  // All genders

            // BUDGET AND SCHEDULE (must match campaign budget_mode)
            budget_mode: state.campaignBudgetMode || budgetMode,  // Use campaign's budget mode
            budget: budget,
            schedule_type: 'SCHEDULE_FROM_NOW',  // Set start time and run continuously
            schedule_start_time: scheduleStartTime,

            // BIDDING
            bid_type: 'BID_TYPE_CUSTOM',  // Always use custom bidding for conversions
            conversion_bid_price: bidPrice,  // Target CPA for conversions

            // PACING
            pacing: 'PACING_MODE_SMOOTH',  // Standard delivery

            // DAYPARTING (optional)
            ...getDaypartingData()
        };

        console.log('Sending ad group params:', JSON.stringify(params, null, 2));

        const response = await apiRequest('create_adgroup', params);

        console.log('Ad group API response:', response);

        if (response.success && response.data && response.data.adgroup_id) {
            state.adGroupId = response.data.adgroup_id;
            showToast('Ad group created successfully', 'success');
            nextStep();
        } else {
            const errorMsg = response.message || 'Failed to create ad group';
            console.error('Ad group creation failed:', errorMsg);
            console.error('Full error response:', response);
            showToast(errorMsg, 'error');
        }
    } catch (error) {
        showToast('Error creating ad group: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Add first ad
function addFirstAd() {
    const adIndex = state.ads.length;
    addAdForm(adIndex);
    state.ads.push({ index: adIndex });
}

// Add ad form
function addAdForm(index, duplicateFrom = null) {
    const container = document.getElementById('ads-container');

    const adCard = document.createElement('div');
    adCard.className = 'ad-card';
    adCard.id = `ad-${index}`;

    const defaultCTAs = ['APPLY_NOW', 'SIGN_UP', 'LEARN_MORE', 'DOWNLOAD', 'SHOP_NOW', 'WATCH_NOW'];

    adCard.innerHTML = `
        <div class="ad-card-header">
            <h3>Ad #${index + 1}</h3>
            <div class="ad-card-actions">
                ${index > 0 ? '<button class="btn-icon" onclick="removeAd(' + index + ')" title="Delete">🗑️</button>' : ''}
            </div>
        </div>

        <div class="form-group">
            <label>Ad Name</label>
            <input type="text" id="ad-name-${index}" placeholder="Enter ad name" required>
        </div>

        <div class="form-group">
            <label>Creative Media (Video or Image)</label>
            <div class="creative-placeholder" onclick="openMediaModal(${index}, 'primary')">
                <span id="creative-placeholder-${index}">Click to select video or image</span>
            </div>
            <img id="creative-preview-${index}" class="creative-preview" style="display: none;">
            <input type="hidden" id="creative-id-${index}">
            <input type="hidden" id="creative-type-${index}">
        </div>

        <div class="form-group" id="cover-image-group-${index}" style="display: none;">
            <label>Cover Image (Required for Video Ads)</label>
            <div class="creative-placeholder" onclick="openMediaModal(${index}, 'cover')" style="border-color: #667eea;">
                <span id="cover-placeholder-${index}">Click to select cover image</span>
            </div>
            <img id="cover-preview-${index}" class="creative-preview" style="display: none;">
            <input type="hidden" id="cover-image-id-${index}">
        </div>

        <div class="form-group">
            <label>Identity</label>
            <select id="identity-${index}" required>
                <option value="">Select identity...</option>
            </select>
        </div>

        <div class="form-group">
            <label>Ad Copy (Text)</label>
            <textarea id="ad-text-${index}" placeholder="Enter your ad copy" rows="3" required></textarea>
        </div>

        <div class="form-group">
            <label>Call to Action</label>
            <div class="cta-chips" id="cta-chips-${index}">
                ${defaultCTAs.map(cta => `
                    <div class="cta-chip" data-cta="${cta}" onclick="selectCTA(${index}, '${cta}')">
                        ${cta.replace(/_/g, ' ')}
                    </div>
                `).join('')}
            </div>
            <input type="hidden" id="cta-${index}" value="APPLY_NOW">
        </div>

        <div class="form-group">
            <label>Destination URL</label>
            <input type="text" id="destination-url-${index}" placeholder="https://example.com" required>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="auto-url-params-${index}" checked>
                Automatically add URL parameters
            </label>
        </div>
    `;

    container.appendChild(adCard);

    // Populate identities
    populateIdentitiesForAd(index);

    // Select first CTA by default
    setTimeout(() => selectCTA(index, 'APPLY_NOW'), 100);

    // If duplicating, copy values
    if (duplicateFrom !== null) {
        setTimeout(() => {
            document.getElementById(`ad-name-${index}`).value =
                document.getElementById(`ad-name-${duplicateFrom}`).value + ' (Copy)';
            document.getElementById(`ad-text-${index}`).value =
                document.getElementById(`ad-text-${duplicateFrom}`).value;
            document.getElementById(`destination-url-${index}`).value =
                document.getElementById(`destination-url-${duplicateFrom}`).value;
            document.getElementById(`identity-${index}`).value =
                document.getElementById(`identity-${duplicateFrom}`).value;

            const creativeId = document.getElementById(`creative-id-${duplicateFrom}`).value;
            const creativeType = document.getElementById(`creative-type-${duplicateFrom}`).value;

            if (creativeId) {
                document.getElementById(`creative-id-${index}`).value = creativeId;
                document.getElementById(`creative-type-${index}`).value = creativeType;

                const preview = document.getElementById(`creative-preview-${duplicateFrom}`);
                if (preview && preview.style.display !== 'none') {
                    const newPreview = document.getElementById(`creative-preview-${index}`);
                    newPreview.src = preview.src;
                    newPreview.style.display = 'block';
                    document.getElementById(`creative-placeholder-${index}`).parentElement.style.display = 'none';
                }
            }
        }, 100);
    }
}

// Duplicate ad
function duplicateAd() {
    const lastAdIndex = state.ads.length - 1;
    const newIndex = state.ads.length;

    addAdForm(newIndex, lastAdIndex);
    state.ads.push({ index: newIndex });

    showToast('Ad duplicated', 'success');
}

// Remove ad
function removeAd(index) {
    if (confirm('Are you sure you want to delete this ad?')) {
        const adCard = document.getElementById(`ad-${index}`);
        adCard.remove();

        state.ads = state.ads.filter(ad => ad.index !== index);

        showToast('Ad removed', 'success');
    }
}

// Select CTA
function selectCTA(adIndex, cta) {
    // Remove selected class from all CTAs for this ad
    const chips = document.querySelectorAll(`#cta-chips-${adIndex} .cta-chip`);
    chips.forEach(chip => chip.classList.remove('selected'));

    // Add selected class to clicked CTA
    const selectedChip = document.querySelector(`#cta-chips-${adIndex} .cta-chip[data-cta="${cta}"]`);
    if (selectedChip) {
        selectedChip.classList.add('selected');
    }

    // Update hidden input
    document.getElementById(`cta-${adIndex}`).value = cta;
}

// Media modal
function openMediaModal(adIndex, selectionType = 'primary') {
    state.currentAdIndex = adIndex;
    state.currentSelectionType = selectionType;
    state.selectedMedia = []; // Reset selection

    const modal = document.getElementById('media-modal');
    modal.classList.add('show');

    // Update modal title and tabs based on selection type
    const modalTitle = modal.querySelector('.modal-header h3');
    const modalTabs = modal.querySelector('.modal-tabs');
    
    if (selectionType === 'cover') {
        modalTitle.innerHTML = 'Select Cover Image <span style="font-size: 14px; color: #667eea; margin-left: 10px;">(Images only)</span>';
        
        // Update tabs for image-only selection
        modalTabs.innerHTML = `
            <button class="tab-btn active" onclick="switchMediaTab('library', event)">Image Library</button>
            <button class="tab-btn" onclick="switchMediaTab('upload', event)">Upload Image</button>
            <button class="btn-secondary btn-sm" onclick="loadImageLibrary()" style="margin-left: auto;">🔄 Refresh</button>
            <button class="btn-secondary btn-sm" onclick="syncImagesFromTikTok()">📥 Sync from TikTok</button>
        `;
        
        loadImageLibrary(); // Load only images for cover selection
    } else {
        modalTitle.innerHTML = 'Select Media <span id="selection-counter" style="font-size: 14px; color: #667eea; margin-left: 10px; display: none;"></span>';
        
        // Restore default tabs
        modalTabs.innerHTML = `
            <button class="tab-btn active" onclick="switchMediaTab('library', event)">Library</button>
            <button class="tab-btn" onclick="switchMediaTab('upload', event)">Upload New</button>
            <button class="btn-secondary btn-sm" onclick="refreshMediaLibrary()" style="margin-left: auto;">🔄 Refresh</button>
            <button class="btn-secondary btn-sm" onclick="syncTikTokLibrary()">📥 Sync from TikTok</button>
        `;
        
        loadMediaLibrary(); // Load all media
    }
    
    updateSelectionCounter();
}

function closeMediaModal() {
    const modal = document.getElementById('media-modal');
    modal.classList.remove('show');
    state.currentAdIndex = null;
    state.selectedMedia = [];
}

function switchMediaTab(tab, evt) {
    // Get event from parameter
    const clickEvent = evt;
    
    // Remove active class from all tabs and buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.media-tab').forEach(tabContent => tabContent.classList.remove('active'));

    // Add active class to clicked button (if event exists) and corresponding tab
    if (clickEvent && clickEvent.target) {
        clickEvent.target.classList.add('active');
    } else {
        // If no event, find the button by text content
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.textContent.toLowerCase().includes(tab.toLowerCase())) {
                btn.classList.add('active');
            }
        });
    }
    
    // Show the correct tab content
    const tabElement = document.getElementById(`media-${tab}-tab`);
    if (tabElement) {
        tabElement.classList.add('active');
    }
}

// Load media library
async function loadImageLibrary() {
    const mediaGrid = document.getElementById('media-grid');
    mediaGrid.innerHTML = '<div class="loading">Loading images from TikTok...</div>';

    try {
        const response = await apiRequest('get_images', {}, 'GET');
        
        if (response.success && response.data && response.data.list) {
            const images = response.data.list;
            
            if (images.length === 0) {
                mediaGrid.innerHTML = `
                    <div style="grid-column: 1 / -1;">
                        <div style="padding: 15px; background: #f0f8ff; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;">Manual Image ID Entry</h4>
                            <p style="font-size: 12px; color: #666; margin: 0 0 10px 0;">
                                Enter your image ID from TikTok Ads Manager:
                            </p>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="manual-image-id" placeholder="Enter TikTok Image ID" 
                                       style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <button onclick="useManualImageId()" class="btn-primary" style="padding: 8px 16px;">
                                    Use This Image
                                </button>
                            </div>
                        </div>
                        <div class="empty-state">
                            <p>No images found in TikTok library</p>
                            <small>Upload images to TikTok first or enter an image ID manually above</small>
                        </div>
                    </div>`;
                return;
            }

            console.log(`Loaded ${images.length} images from library`);
            
            // Add manual input option at the top
            const manualInputHtml = `
                <div style="grid-column: 1 / -1; padding: 15px; background: #f0f8ff; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">Manual Image ID Entry</h4>
                    <p style="font-size: 12px; color: #666; margin: 0 0 10px 0;">
                        If you know your image ID from TikTok Ads Manager, enter it here:
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="manual-image-id" placeholder="Enter TikTok Image ID" 
                               style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <button onclick="useManualImageId()" class="btn-primary" style="padding: 8px 16px;">
                            Use This Image
                        </button>
                    </div>
                </div>
            `;
            
            const imagesHtml = images.map(image => {
                // Create a safe ID for the onclick
                const safeImage = {
                    image_id: image.image_id,
                    url: image.url,
                    file_name: image.file_name,
                    type: 'image'
                };
                
                return `
                <div class="media-item" onclick='selectMedia(${JSON.stringify(safeImage)})' data-id="${image.image_id}">
                    ${image.url ? 
                        `<img src="${image.url}" alt="${image.file_name || 'Image'}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML='<div class=\\'media-placeholder\\' style=\\'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\\'>🖼️</div>'">` : 
                        `<div class="media-placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="media-icon">🖼️</div>
                        </div>`
                    }
                    <div class="media-info">
                        <div class="media-name" title="${image.file_name || 'Image'}">${(image.file_name || 'Image').substring(0, 20)}${(image.file_name || '').length > 20 ? '...' : ''}</div>
                        <div class="media-type">Image</div>
                        <div style="font-size: 10px; opacity: 0.7;">ID: ${image.image_id.substring(0, 8)}...</div>
                    </div>
                </div>`;
            }).join('');
            
            mediaGrid.innerHTML = manualInputHtml + imagesHtml;
            
            if (images.length > 0) {
                showToast(`Found ${images.length} uploaded images`, 'success');
            }
        } else {
            mediaGrid.innerHTML = `
                <div class="error">
                    <p>Failed to load images from TikTok</p>
                    <button class="btn-secondary" onclick="loadImageLibrary()">Try Again</button>
                </div>`;
        }
    } catch (error) {
        console.error('Error loading images:', error);
        mediaGrid.innerHTML = `
            <div class="error">
                <p>Error loading images: ${error.message}</p>
                <button class="btn-secondary" onclick="loadImageLibrary()">Try Again</button>
            </div>`;
    }
}

async function loadMediaLibrary() {
    try {
        showToast('Loading media library...', 'info');
        
        const [imagesResponse, videosResponse] = await Promise.all([
            apiRequest('get_images', {}, 'GET'),
            apiRequest('get_videos', {}, 'GET')
        ]);

        state.mediaLibrary = [];

        if (imagesResponse.success && imagesResponse.data && imagesResponse.data.list) {
            state.mediaLibrary.push(...imagesResponse.data.list.map(img => ({
                ...img,
                type: 'image'
            })));
        }

        if (videosResponse.success && videosResponse.data && videosResponse.data.list) {
            state.mediaLibrary.push(...videosResponse.data.list.map(vid => ({
                ...vid,
                type: 'video'
            })));
        }

        renderMediaGrid();
        
        if (state.mediaLibrary.length === 0) {
            showToast('No media found. Upload files to add to library.', 'info');
        } else {
            showToast(`Loaded ${state.mediaLibrary.length} media file(s)`, 'success');
        }
    } catch (error) {
        console.error('Error loading media library:', error);
        showToast('Error loading media library', 'error');
    }
}

// Use manually entered image ID
function useManualImageId() {
    const imageIdInput = document.getElementById('manual-image-id');
    if (!imageIdInput) return;
    
    const imageId = imageIdInput.value.trim();
    if (!imageId) {
        showToast('Please enter an image ID', 'error');
        return;
    }
    
    // Create a media object with just the ID
    const manualImage = {
        image_id: imageId,
        url: '',
        file_name: `Manual Image (${imageId})`,
        type: 'image'
    };
    
    // Select this image
    selectMedia(manualImage);
    
    showToast(`Selected image ID: ${imageId}`, 'success');
    
    // Clear the input
    imageIdInput.value = '';
}

// Sync images from TikTok
async function syncImagesFromTikTok() {
    showLoading();
    try {
        const response = await apiRequest('sync_images_from_tiktok');
        
        if (response.success) {
            showToast(response.message || 'Images synced successfully', 'success');
            // Reload the image library
            if (state.currentSelectionType === 'cover') {
                loadImageLibrary();
            }
        } else {
            showToast(response.message || 'Failed to sync images', 'error');
        }
    } catch (error) {
        showToast('Error syncing images: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Refresh media library
async function refreshMediaLibrary() {
    await loadMediaLibrary();
}

// Sync with TikTok library
async function syncTikTokLibrary() {
    try {
        showToast('Syncing with TikTok library...', 'info');
        
        const response = await apiRequest('sync_tiktok_library', {}, 'POST');
        
        if (response.success) {
            showToast(response.message + ` (Total: ${response.total_videos} videos)`, 'success');
            // Reload the media library to show new items
            await loadMediaLibrary();
        } else {
            showToast('Failed to sync with TikTok', 'error');
        }
    } catch (error) {
        console.error('Error syncing with TikTok:', error);
        showToast('Error syncing with TikTok library', 'error');
    }
}

// Render media grid
function renderMediaGrid() {
    const grid = document.getElementById('media-grid');
    grid.innerHTML = '';

    if (state.mediaLibrary.length === 0) {
        grid.innerHTML = '<p style="text-align: center; color: #999;">No media in library. Upload some files to get started.</p>';
        return;
    }

    state.mediaLibrary.forEach(media => {
        const item = document.createElement('div');
        item.className = 'media-item';
        item.dataset.id = media.video_id || media.image_id || media.id;
        item.dataset.type = media.type;
        item.onclick = () => selectMedia(media);

        if (media.type === 'image') {
            // For images, use the URL provided by TikTok
            const imgUrl = media.url || media.image_url;
            if (imgUrl) {
                item.innerHTML = `
                    <img src="${imgUrl}" 
                         alt="${media.file_name || 'Image'}" 
                         style="width: 100%; height: 100%; object-fit: cover;"
                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23ddd\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3E${media.image_id || 'Image'}%3C/text%3E%3C/svg%3E'">
                    <div class="media-info">
                        <span class="media-id">${media.image_id}</span>
                        <span class="media-name">${media.file_name || 'Image'}</span>
                    </div>`;
            } else {
                item.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #999;">
                        <div>Image ID: ${media.image_id}</div>
                        <div>${media.file_name || 'No preview'}</div>
                    </div>`;
            }
        } else {
            // For videos, show preview image or placeholder
            const previewUrl = media.preview_url || media.thumbnail_url || media.poster_url || media.cover_url;
            const videoUrl = media.url || media.video_url;
            
            if (previewUrl && previewUrl !== '') {
                item.innerHTML = `
                    <div style="position: relative; width: 100%; height: 150px; background: linear-gradient(135deg, #667eea, #764ba2);">
                        <img src="${previewUrl}" 
                             alt="Video: ${media.file_name || media.video_id}" 
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none';">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                                    background: rgba(0,0,0,0.6); border-radius: 50%; width: 40px; height: 40px; 
                                    display: flex; align-items: center; justify-content: center;">
                            <span style="color: white; font-size: 18px; margin-left: 2px;">▶</span>
                        </div>
                        <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); 
                                    padding: 2px 6px; border-radius: 3px; font-size: 10px; color: white;">
                            ${media.duration ? `${Math.round(media.duration)}s` : 'Video'}
                        </div>
                    </div>
                    <div class="media-info" style="padding: 5px; background: rgba(0,0,0,0.05);">
                        <div style="font-weight: 600; font-size: 12px;">${media.file_name || 'Video'}</div>
                        <div style="font-size: 9px; opacity: 0.7; margin-top: 2px;">ID: ${media.video_id}</div>
                    </div>`;
            } else if (videoUrl) {
                item.innerHTML = `
                    <div style="position: relative; width: 100%; height: 150px; background: #000;">
                        <video src="${videoUrl}" 
                               style="width: 100%; height: 100%; object-fit: cover;"
                               muted
                               onloadedmetadata="this.currentTime=1"
                               onerror="this.style.display='none'; this.parentElement.classList.add('video-no-preview');"></video>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                                    background: rgba(0,0,0,0.6); border-radius: 50%; width: 40px; height: 40px; 
                                    display: flex; align-items: center; justify-content: center;">
                            <span style="color: white; font-size: 18px; margin-left: 2px;">▶</span>
                        </div>
                    </div>
                    <div class="media-info">
                        <span class="media-id" style="font-size: 9px;">${media.video_id}</span>
                        <span class="media-name">${media.file_name || 'Video'}</span>
                        ${media.duration ? `<span class="media-duration">${Math.round(media.duration)}s</span>` : ''}
                    </div>`;
            } else {
                // Fallback display for videos without thumbnails
                item.innerHTML = `
                    <div style="width: 100%; height: 150px; background: linear-gradient(135deg, #667eea, #764ba2); 
                                display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; position: relative;">
                        <div style="font-size: 40px; margin-bottom: 5px;">🎬</div>
                        <div style="font-size: 12px; font-weight: 600;">${media.file_name || 'Video'}</div>
                        ${media.duration ? `<div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.5); 
                                                        padding: 2px 6px; border-radius: 3px; font-size: 10px;">
                            ${Math.round(media.duration)}s</div>` : ''}
                    </div>
                    <div class="media-info" style="padding: 5px; background: rgba(0,0,0,0.05);">
                        <div style="font-weight: 600; font-size: 12px;">${media.file_name || 'Video'}</div>
                        <div style="font-size: 9px; opacity: 0.7; margin-top: 2px; word-break: break-all;">ID: ${media.video_id}</div>
                    </div>`;
            }
        }

        // Add file info as tooltip
        const dimensions = (media.width && media.height) ? ` (${media.width}x${media.height})` : '';
        item.title = `${media.file_name || 'Media'} - ID: ${media.video_id || media.image_id}${dimensions}`;

        grid.appendChild(item);
    });
}

// Select media from library
function selectMedia(media) {
    const mediaId = media.video_id || media.image_id || media.id;
    
    // For cover image selection, only allow single selection
    if (state.currentSelectionType === 'cover') {
        // Only allow images for cover selection
        if (media.type !== 'image') {
            showToast('Please select an image for the cover', 'error');
            return;
        }
        
        // Single selection for cover
        state.selectedMedia = [media];
        
        // Update UI - clear all selections and select only this one
        document.querySelectorAll('.media-item').forEach(item => {
            item.classList.remove('selected');
        });
        document.querySelector(`.media-item[data-id="${mediaId}"]`)?.classList.add('selected');
    } else {
        // For primary media, single selection
        state.selectedMedia = [media];
        
        // Update UI - clear all selections and select only this one
        document.querySelectorAll('.media-item').forEach(item => {
            item.classList.remove('selected');
        });
        document.querySelector(`.media-item[data-id="${mediaId}"]`)?.classList.add('selected');
    }
    
    // Update selection counter
    updateSelectionCounter();
}

// Update selection counter in modal
function updateSelectionCounter() {
    const counter = document.getElementById('selection-counter');
    if (counter) {
        const count = state.selectedMedia.length;
        counter.textContent = count > 0 ? `${count} selected` : '';
        counter.style.display = count > 0 ? 'inline' : 'none';
    }
}

// Confirm media selection
function confirmMediaSelection() {
    if (!state.selectedMedia || state.selectedMedia.length === 0) {
        showToast('Please select media', 'error');
        return;
    }

    const adIndex = state.currentAdIndex;
    const selectionType = state.currentSelectionType;
    const selectedMedia = state.selectedMedia[0]; // Single selection

    if (selectionType === 'cover') {
        // Handle cover image selection
        if (selectedMedia.type !== 'image') {
            showToast('Please select an image for the cover', 'error');
            return;
        }

        const coverImageId = selectedMedia.image_id;
        document.getElementById(`cover-image-id-${adIndex}`).value = coverImageId;
        
        // Update cover placeholder
        const coverPlaceholder = document.getElementById(`cover-placeholder-${adIndex}`);
        const coverContainer = coverPlaceholder.parentElement;
        
        coverContainer.classList.add('has-media');
        if (selectedMedia.url) {
            coverContainer.style.backgroundImage = `url(${selectedMedia.url})`;
            coverContainer.style.backgroundSize = 'cover';
            coverContainer.style.backgroundPosition = 'center';
        }
        
        coverPlaceholder.innerHTML = `
            <div class="media-selected-info">
                <div class="media-type-badge">🖼️</div>
                <div class="media-name">${selectedMedia.file_name || 'Cover Image'}</div>
                <div class="media-id" style="font-size: 11px;">ID: ${coverImageId}</div>
            </div>`;

        closeMediaModal();
        showToast('Cover image selected successfully', 'success');
        
    } else {
        // Handle primary media selection
        const mediaId = selectedMedia.video_id || selectedMedia.image_id;
        if (!mediaId) {
            showToast('Invalid media selection', 'error');
            return;
        }

        document.getElementById(`creative-id-${adIndex}`).value = mediaId;
        document.getElementById(`creative-type-${adIndex}`).value = selectedMedia.type;

        // Update primary media placeholder
        const placeholder = document.getElementById(`creative-placeholder-${adIndex}`);
        const placeholderContainer = placeholder.parentElement;
        
        placeholderContainer.classList.add('has-media');
        
        // Show or hide cover image field based on media type
        const coverImageGroup = document.getElementById(`cover-image-group-${adIndex}`);
        
        if (selectedMedia.type === 'video') {
            // Show cover image field for video
            if (coverImageGroup) {
                coverImageGroup.style.display = 'block';
            }
            
            const previewUrl = selectedMedia.preview_url || selectedMedia.thumbnail_url || selectedMedia.video_cover_url;
            if (previewUrl) {
                placeholderContainer.style.backgroundImage = `url(${previewUrl})`;
                placeholderContainer.style.backgroundSize = 'cover';
            } else {
                placeholderContainer.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
            
            placeholder.innerHTML = `
                <div class="media-selected-info">
                    <div class="media-type-badge">🎥</div>
                    <div class="media-name">${selectedMedia.file_name || 'Video'}</div>
                    ${selectedMedia.duration ? `<div style="font-size: 11px;">⏱ ${Math.round(selectedMedia.duration)}s</div>` : ''}
                    <div class="media-id" style="font-size: 11px;">ID: ${mediaId}</div>
                </div>`;
                
            showToast('Video selected. Now select a cover image below.', 'info');
            
        } else {
            // Hide cover image field for image ads
            if (coverImageGroup) {
                coverImageGroup.style.display = 'none';
            }
            
            if (selectedMedia.url) {
                placeholderContainer.style.backgroundImage = `url(${selectedMedia.url})`;
                placeholderContainer.style.backgroundSize = 'cover';
            }
            
            placeholder.innerHTML = `
                <div class="media-selected-info">
                    <div class="media-type-badge">📷</div>
                    <div class="media-name">${selectedMedia.file_name || 'Image'}</div>
                    <div class="media-id" style="font-size: 11px;">ID: ${mediaId}</div>
                </div>`;
                
            showToast('Image selected successfully', 'success');
        }

        closeMediaModal();
    }
}

// Handle media upload
async function handleMediaUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const isVideo = file.type.startsWith('video/');
    const isImage = file.type.startsWith('image/');

    if (!isImage && !isVideo) {
        showToast('Please upload an image or video file', 'error');
        return;
    }

    // Check file size
    const maxSize = isVideo ? 500 * 1024 * 1024 : 10 * 1024 * 1024; // 500MB for video, 10MB for image
    if (file.size > maxSize) {
        showToast(`File too large. Maximum size is ${isVideo ? '500MB' : '10MB'}`, 'error');
        return;
    }

    const formData = new FormData();
    formData.append(isVideo ? 'video' : 'image', file);

    document.getElementById('upload-progress').style.display = 'block';
    document.getElementById('upload-area').style.display = 'none';
    
    // Add upload status message
    const progressDiv = document.getElementById('upload-progress');
    progressDiv.innerHTML = `<p>Uploading ${file.name}...</p><div class="progress-bar"><div class="progress-fill" style="width: 0%"></div></div>`;

    try {
        addLog('request', `Uploading ${isVideo ? 'video' : 'image'}: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
        
        const response = await fetch(`api.php?action=${isVideo ? 'upload_video' : 'upload_image'}`, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        
        addLog(result.success ? 'response' : 'error', `Upload ${result.success ? 'successful' : 'failed'}`, result);

        if (result.success) {
            showToast(`${isVideo ? 'Video' : 'Image'} uploaded successfully`, 'success');

            // Reload media library to show the new upload
            await loadMediaLibrary();

            // Switch to library tab to show the uploaded file
            document.querySelector('.tab-btn[onclick*="library"]').click();

            // Reset upload form
            event.target.value = '';
        } else {
            const errorMsg = result.message || `Failed to upload ${isVideo ? 'video' : 'image'}`;
            showToast(errorMsg, 'error');
            console.error('Upload error:', result);
        }
    } catch (error) {
        addLog('error', 'Upload failed', { error: error.message });
        showToast('Error uploading file: ' + error.message, 'error');
        console.error('Upload exception:', error);
    } finally {
        document.getElementById('upload-progress').style.display = 'none';
        document.getElementById('upload-area').style.display = 'block';
        progressDiv.innerHTML = '<p>Processing...</p>';
    }
}

// Load identities
async function loadIdentities() {
    try {
        const response = await apiRequest('get_identities', {}, 'GET');
        
        console.log('Identities Response:', response);

        if (response.success && response.data && response.data.list) {
            state.identities = response.data.list;
            console.log('Loaded identities:', state.identities);
            
            // Re-populate all existing ad forms with identities
            state.ads.forEach(ad => {
                populateIdentitiesForAd(ad.index);
            });
        } else {
            console.warn('No identities found in response');
            state.identities = [];
        }
    } catch (error) {
        console.error('Error loading identities:', error);
        state.identities = [];
    }
}

// Load pixels from TikTok account
async function loadPixels() {
    const pixelSelect = document.getElementById('lead-gen-form-id');

    try {
        const response = await apiRequest('get_pixels', {}, 'GET');

        console.log('Pixels API Response:', response);

        // Clear loading state
        pixelSelect.innerHTML = '<option value="">Select a pixel...</option>';

        if (response.success && response.data) {
            // TikTok API might return pixels in different formats
            const pixels = response.data.list || response.data.pixels || [response.data];

            if (pixels && pixels.length > 0) {
                pixels.forEach(pixel => {
                    const option = document.createElement('option');
                    option.value = pixel.pixel_id;  // Use the numeric pixel_id
                    option.textContent = `${pixel.pixel_name || 'Unnamed Pixel'} (${pixel.pixel_code || pixel.pixel_id})`;
                    pixelSelect.appendChild(option);
                });
            } else {
                pixelSelect.innerHTML = '<option value="">No pixels found - Check your account</option>';
            }
        } else {
            console.error('Pixel API failed:', response);
            const errorMsg = response.message || 'No pixels found - Check your account';
            pixelSelect.innerHTML = `<option value="">Error: ${errorMsg}</option>`;
        }
    } catch (error) {
        console.error('Error loading pixels:', error);
        pixelSelect.innerHTML = '<option value="">Error loading pixels</option>';
    }
}

// Populate identities dropdown for an ad
function populateIdentitiesForAd(adIndex) {
    const select = document.getElementById(`identity-${adIndex}`);
    
    // Clear existing options except the first one
    while (select.options.length > 1) {
        select.remove(1);
    }

    if (state.identities && state.identities.length > 0) {
        state.identities.forEach(identity => {
            const option = document.createElement('option');
            option.value = identity.identity_id;
            option.setAttribute('data-identity-type', identity.identity_type || 'CUSTOMIZED_USER');
            
            // Show both identity name and display name if different
            const name = identity.identity_name || identity.display_name || 'Custom Identity';
            const displayName = identity.display_name || '';
            const typeLabel = identity.identity_type === 'TT_USER' ? ' (TikTok)' : ' (Custom)';
            
            if (displayName && displayName !== name) {
                option.textContent = `${name} (${displayName})${typeLabel}`;
            } else {
                option.textContent = name + typeLabel;
            }
            select.appendChild(option);
        });
        
        // Select first identity by default if available
        if (state.identities.length > 0 && select.options.length > 1) {
            select.selectedIndex = 1;
        }
    } else {
        // Add helpful messages for no identities
        const option1 = document.createElement('option');
        option1.value = '';
        option1.textContent = '⚠️ No identities found';
        option1.disabled = true;
        select.appendChild(option1);
        
        const option2 = document.createElement('option');
        option2.value = '';
        option2.textContent = '→ Create one in TikTok Ads Manager';
        option2.disabled = true;
        select.appendChild(option2);
        
        const option3 = document.createElement('option');
        option3.value = '';
        option3.textContent = '→ Or link a TikTok account';
        option3.disabled = true;
        select.appendChild(option3);
    }
}

// Review ads before publishing
function reviewAds() {
    console.log('=====================================');
    console.log('Review Ads button clicked');
    console.log('Current state:', state);
    console.log('Number of ads:', state.ads.length);
    console.log('Campaign ID:', state.campaignId);
    console.log('Ad Group ID:', state.adGroupId);
    console.log('=====================================');
    
    // Check if we have campaign and ad group
    if (!state.campaignId) {
        showToast('Please create a campaign first (Step 1)', 'error');
        console.error('No campaign ID found');
        return;
    }
    
    if (!state.adGroupId) {
        showToast('Please create an ad group first (Step 2)', 'error');
        console.error('No ad group ID found');
        return;
    }
    
    // Check if we have any ads
    if (state.ads.length === 0) {
        showToast('Please add at least one ad before continuing', 'error');
        console.error('No ads found');
        return;
    }
    
    // Validate all ads
    let allValid = true;

    for (let i = 0; i < state.ads.length; i++) {
        const adIndex = state.ads[i].index;
        console.log(`Validating ad index ${adIndex}`);

        const adNameEl = document.getElementById(`ad-name-${adIndex}`);
        const adTextEl = document.getElementById(`ad-text-${adIndex}`);
        const creativeIdEl = document.getElementById(`creative-id-${adIndex}`);
        const identityEl = document.getElementById(`identity-${adIndex}`);
        const destinationUrlEl = document.getElementById(`destination-url-${adIndex}`);
        
        if (!adNameEl || !adTextEl || !creativeIdEl || !destinationUrlEl) {
            console.error(`Missing form elements for ad ${adIndex}`);
            showToast(`Error: Missing form elements for Ad #${adIndex + 1}`, 'error');
            allValid = false;
            break;
        }
        
        const adName = adNameEl.value.trim();
        const adText = adTextEl.value.trim();
        const creativeId = creativeIdEl.value;
        const creativeType = document.getElementById(`creative-type-${adIndex}`).value;
        const coverImageId = document.getElementById(`cover-image-id-${adIndex}`).value;
        const identityId = identityEl ? identityEl.value : '';
        const destinationUrl = destinationUrlEl.value.trim();

        if (!adName) {
            showToast(`Please enter ad name for Ad #${adIndex + 1}`, 'error');
            allValid = false;
            break;
        }
        if (!adText) {
            showToast(`Please enter ad copy for Ad #${adIndex + 1}`, 'error');
            allValid = false;
            break;
        }
        if (!creativeId) {
            showToast(`Please select media for Ad #${adIndex + 1}`, 'error');
            allValid = false;
            break;
        }
        // Check for cover image on video ads
        if (creativeType === 'video' && !coverImageId) {
            showToast(`Please select a cover image for video Ad #${adIndex + 1}. Cover image is required for video ads.`, 'error');
            allValid = false;
            break;
        }
        if (!destinationUrl) {
            showToast(`Please enter destination URL for Ad #${adIndex + 1}`, 'error');
            allValid = false;
            break;
        }
        // Identity is REQUIRED according to TikTok API docs
        if (!identityId) {
            showToast(`Please select an identity for Ad #${adIndex + 1}. Identity is required for ad creation.`, 'error');
            allValid = false;
            break;
        }
    }

    if (!allValid) return;

    // Generate review summaries
    generateReviewSummary();

    nextStep();
}

// Generate review summary
function generateReviewSummary() {
    // Campaign summary
    const campaignSummary = document.getElementById('campaign-summary');
    campaignSummary.innerHTML = `
        <p><strong>Campaign Name:</strong> ${document.getElementById('campaign-name').value}</p>
        <p><strong>Objective:</strong> Lead Generation</p>
        <p><strong>Type:</strong> Manual Campaign</p>
    `;

    // Ad Group summary
    const adGroupSummary = document.getElementById('adgroup-summary');
    const startDate = new Date(document.getElementById('start-date').value);
    adGroupSummary.innerHTML = `
        <p><strong>Ad Group Name:</strong> ${document.getElementById('adgroup-name').value}</p>
        <p><strong>Daily Budget:</strong> $${document.getElementById('budget').value}</p>
        <p><strong>Start Date:</strong> ${startDate.toLocaleString()}</p>
        <p><strong>Timezone:</strong> ${document.getElementById('timezone').options[document.getElementById('timezone').selectedIndex].text}</p>
        <p><strong>Bid Price:</strong> $${document.getElementById('bid-price').value}</p>
        <p><strong>Location:</strong> United States</p>
        <p><strong>Placement:</strong> TikTok</p>
    `;

    // Ads summary
    const adsSummary = document.getElementById('ads-summary');
    adsSummary.innerHTML = '';

    state.ads.forEach(ad => {
        const adIndex = ad.index;
        const adName = document.getElementById(`ad-name-${adIndex}`).value;
        const adText = document.getElementById(`ad-text-${adIndex}`).value;
        const cta = document.getElementById(`cta-${adIndex}`).value;
        const destinationUrl = document.getElementById(`destination-url-${adIndex}`).value;

        const adItem = document.createElement('div');
        adItem.className = 'summary-ad-item';
        adItem.innerHTML = `
            <h4>${adName}</h4>
            <p><strong>Ad Copy:</strong> ${adText.substring(0, 100)}${adText.length > 100 ? '...' : ''}</p>
            <p><strong>CTA:</strong> ${cta.replace(/_/g, ' ')}</p>
            <p><strong>URL:</strong> ${destinationUrl}</p>
        `;

        adsSummary.appendChild(adItem);
    });
}

// Publish all ads
async function publishAll() {
    console.log('=====================================');
    console.log('Publish All button clicked');
    console.log('State before publishing:', state);
    console.log('=====================================');
    
    if (!confirm('Are you sure you want to publish all ads? This cannot be undone.')) {
        console.log('User cancelled publish');
        return;
    }

    console.log('Starting ad creation process...');
    showLoading();

    try {
        const createdAdIds = [];

        // Create all ads
        for (let i = 0; i < state.ads.length; i++) {
            const adIndex = state.ads[i].index;

            const identitySelect = document.getElementById(`identity-${adIndex}`);
            const selectedIdentity = identitySelect.value;
            const selectedOption = identitySelect.options[identitySelect.selectedIndex];
            const identityType = selectedOption ? selectedOption.getAttribute('data-identity-type') : 'CUSTOMIZED_USER';
            
            const adData = {
                adgroup_id: state.adGroupId,
                ad_name: document.getElementById(`ad-name-${adIndex}`).value,
                ad_text: document.getElementById(`ad-text-${adIndex}`).value,
                call_to_action: document.getElementById(`cta-${adIndex}`).value,
                landing_page_url: document.getElementById(`destination-url-${adIndex}`).value,
                identity_id: selectedIdentity,
                identity_type: identityType || 'CUSTOMIZED_USER'
            };

            const creativeType = document.getElementById(`creative-type-${adIndex}`).value;
            const creativeId = document.getElementById(`creative-id-${adIndex}`).value;
            const coverImageId = document.getElementById(`cover-image-id-${adIndex}`)?.value;

            if (creativeType === 'video') {
                adData.video_id = creativeId;
                adData.ad_format = 'SINGLE_VIDEO';
                
                // For video ads, image_ids is REQUIRED for the video cover
                if (coverImageId) {
                    adData.image_ids = [coverImageId];
                    console.log(`Using cover image_id for video ad: ${coverImageId}`);
                } else {
                    console.warn('No cover image_id for video ad - this may cause the ad creation to fail');
                    // Try to use a default or placeholder image_id if available
                    adData.image_ids = []; // This will likely fail, but TikTok will provide error details
                }
            } else {
                adData.image_ids = [creativeId];
                adData.ad_format = 'SINGLE_IMAGE';
            }

            console.log(`Creating ad ${i+1}/${state.ads.length}:`, adData);
            const response = await apiRequest('create_ad', adData);
            console.log(`Ad creation response:`, response);

            if (response.success && response.data && response.data.ad_id) {
                createdAdIds.push(response.data.ad_id);
                showToast(`Ad ${i+1} created successfully`, 'success');
            } else {
                console.error('Ad creation failed:', response);
                throw new Error(`Failed to create ad ${i+1}: ${response.message || 'Unknown error'}`);
            }
        }

        // Publish all ads
        if (createdAdIds.length > 0) {
            const publishResponse = await apiRequest('publish_ads', {
                ad_ids: createdAdIds
            });

            if (publishResponse.success) {
                showToast('All ads published successfully! 🎉', 'success');

                // Show success message and reset
                setTimeout(() => {
                    if (confirm('Campaign launched successfully! Do you want to create another campaign?')) {
                        location.reload();
                    }
                }, 2000);
            } else {
                showToast('Ads created but failed to publish: ' + publishResponse.message, 'error');
            }
        }
    } catch (error) {
        showToast('Error publishing ads: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// API request helper
async function apiRequest(action, data = {}, method = 'POST') {
    const url = `api.php?action=${action}`;

    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (method === 'POST') {
        options.body = JSON.stringify(data);
    }

    // Log the request
    addLog('request', `${method} ${action}`, method === 'POST' ? data : null);

    try {
        const response = await fetch(url, options);

        if (!response.ok) {
            addLog('error', `HTTP ${response.status} error for ${action}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const jsonResponse = await response.json();

        // Log the response
        if (jsonResponse.success === false) {
            addLog('error', `API Error: ${action}`, jsonResponse);
        } else {
            addLog('response', `${action} completed`, jsonResponse);
        }

        return jsonResponse;
    } catch (error) {
        addLog('error', `Request failed: ${error.message}`, { action, error: error.message });
        throw error;
    }
}

// Logout
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        await apiRequest('logout');
        window.location.href = 'index.php';
    }
}

// Show loading overlay
function showLoading() {
    document.getElementById('loading-overlay').classList.add('show');
}

// Hide loading overlay
function hideLoading() {
    document.getElementById('loading-overlay').classList.remove('show');
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast show ${type}`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Toggle between pixel dropdown and manual input
function togglePixelInput() {
    const pixelMethod = document.querySelector('input[name="pixel-method"]:checked')?.value;
    const dropdownContainer = document.getElementById('pixel-dropdown-container');
    const manualContainer = document.getElementById('pixel-manual-container');

    console.log('Toggle pixel input - Method:', pixelMethod);

    if (!dropdownContainer || !manualContainer) {
        console.error('Pixel containers not found');
        return;
    }

    if (pixelMethod === 'manual') {
        dropdownContainer.style.display = 'none';
        manualContainer.style.display = 'block';
        console.log('Showing manual input');
    } else {
        dropdownContainer.style.display = 'block';
        manualContainer.style.display = 'none';
        console.log('Showing dropdown');
    }
}
