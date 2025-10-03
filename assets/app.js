// Global state
let state = {
    currentStep: 1,
    campaignId: null,
    adGroupId: null,
    ads: [],
    identities: [],
    mediaLibrary: [],
    selectedMedia: null,
    currentAdIndex: null
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDayparting();
    loadIdentities();
    loadMediaLibrary();
    addFirstAd();

    // Set default start date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);
    document.getElementById('start-date').value = formatDateTimeLocal(tomorrow);
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

    if (!campaignName) {
        showToast('Please enter a campaign name', 'error');
        return;
    }

    showLoading();

    try {
        const response = await apiRequest('create_campaign', {
            campaign_name: campaignName
        });

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

// Ad Group creation
async function createAdGroup() {
    const adGroupName = document.getElementById('adgroup-name').value.trim();
    const budget = parseFloat(document.getElementById('budget').value);
    const startDate = document.getElementById('start-date').value;
    const timezone = document.getElementById('timezone').value;
    const bidPrice = parseFloat(document.getElementById('bid-price').value);

    if (!adGroupName || !budget || !startDate || !bidPrice) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    if (budget < 20) {
        showToast('Minimum daily budget is $20', 'error');
        return;
    }

    showLoading();

    try {
        // Convert datetime to TikTok format: "YYYY-MM-DD HH:MM:SS" in UTC
        const startDateTime = new Date(startDate);
        const year = startDateTime.getUTCFullYear();
        const month = String(startDateTime.getUTCMonth() + 1).padStart(2, '0');
        const day = String(startDateTime.getUTCDate()).padStart(2, '0');
        const hours = String(startDateTime.getUTCHours()).padStart(2, '0');
        const minutes = String(startDateTime.getUTCMinutes()).padStart(2, '0');
        const seconds = '00';
        const scheduleStartTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;

        const params = {
            campaign_id: state.campaignId,
            adgroup_name: adGroupName,
            budget: budget,
            schedule_start_time: scheduleStartTime,
            timezone: timezone,
            bid_price: bidPrice,
            dayparting: getDaypartingData()
        };

        const response = await apiRequest('create_adgroup', params);

        if (response.success && response.data && response.data.adgroup_id) {
            state.adGroupId = response.data.adgroup_id;
            showToast('Ad group created successfully', 'success');
            nextStep();
        } else {
            showToast(response.message || 'Failed to create ad group', 'error');
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
            <label>Creative (Image or Video)</label>
            <div class="creative-placeholder" onclick="openMediaModal(${index})">
                <span id="creative-placeholder-${index}">Click to select media</span>
            </div>
            <img id="creative-preview-${index}" class="creative-preview" style="display: none;">
            <input type="hidden" id="creative-id-${index}">
            <input type="hidden" id="creative-type-${index}">
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
function openMediaModal(adIndex) {
    state.currentAdIndex = adIndex;
    state.selectedMedia = null;

    const modal = document.getElementById('media-modal');
    modal.classList.add('show');

    loadMediaLibrary();
}

function closeMediaModal() {
    const modal = document.getElementById('media-modal');
    modal.classList.remove('show');
    state.currentAdIndex = null;
    state.selectedMedia = null;
}

function switchMediaTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.media-tab').forEach(tabContent => tabContent.classList.remove('active'));

    event.target.classList.add('active');
    document.getElementById(`media-${tab}-tab`).classList.add('active');
}

// Load media library
async function loadMediaLibrary() {
    try {
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
    } catch (error) {
        console.error('Error loading media library:', error);
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
        item.dataset.id = media.id || media.video_id || media.image_id;
        item.dataset.type = media.type;
        item.onclick = () => selectMedia(media);

        if (media.type === 'image') {
            item.innerHTML = `<img src="${media.image_url || media.url}" alt="Image">`;
        } else {
            item.innerHTML = `<video src="${media.video_url || media.url}" muted></video>`;
        }

        grid.appendChild(item);
    });
}

// Select media from library
function selectMedia(media) {
    state.selectedMedia = media;

    // Update UI
    document.querySelectorAll('.media-item').forEach(item => item.classList.remove('selected'));
    const selectedItem = document.querySelector(`.media-item[data-id="${media.id || media.video_id || media.image_id}"]`);
    if (selectedItem) {
        selectedItem.classList.add('selected');
    }
}

// Confirm media selection
function confirmMediaSelection() {
    if (!state.selectedMedia) {
        showToast('Please select a media file', 'error');
        return;
    }

    const adIndex = state.currentAdIndex;
    const media = state.selectedMedia;

    // Update ad form
    document.getElementById(`creative-id-${adIndex}`).value = media.id || media.video_id || media.image_id;
    document.getElementById(`creative-type-${adIndex}`).value = media.type;

    // Show preview
    const preview = document.getElementById(`creative-preview-${adIndex}`);
    preview.src = media.image_url || media.video_url || media.url;
    preview.style.display = 'block';

    // Hide placeholder
    document.getElementById(`creative-placeholder-${adIndex}`).parentElement.style.display = 'none';

    closeMediaModal();
    showToast('Media selected', 'success');
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

    const formData = new FormData();
    formData.append(isVideo ? 'video' : 'image', file);

    document.getElementById('upload-progress').style.display = 'block';
    document.getElementById('upload-area').style.display = 'none';

    try {
        const response = await fetch(`api.php?action=${isVideo ? 'upload_video' : 'upload_image'}`, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('File uploaded successfully', 'success');

            // Reload media library
            await loadMediaLibrary();

            // Switch back to library tab
            switchMediaTab('library');

            // Reset upload form
            document.getElementById('upload-progress').style.display = 'none';
            document.getElementById('upload-area').style.display = 'block';
            event.target.value = '';
        } else {
            showToast(result.message || 'Upload failed', 'error');
        }
    } catch (error) {
        showToast('Error uploading file: ' + error.message, 'error');
    } finally {
        document.getElementById('upload-progress').style.display = 'none';
        document.getElementById('upload-area').style.display = 'block';
    }
}

// Load identities
async function loadIdentities() {
    try {
        const response = await apiRequest('get_identities', {}, 'GET');

        if (response.success && response.data && response.data.list) {
            state.identities = response.data.list;
        }
    } catch (error) {
        console.error('Error loading identities:', error);
    }
}

// Populate identities dropdown for an ad
function populateIdentitiesForAd(adIndex) {
    const select = document.getElementById(`identity-${adIndex}`);

    state.identities.forEach(identity => {
        const option = document.createElement('option');
        option.value = identity.identity_id;
        option.textContent = identity.identity_name || identity.display_name;
        select.appendChild(option);
    });
}

// Review ads before publishing
function reviewAds() {
    // Validate all ads
    let allValid = true;

    for (let i = 0; i < state.ads.length; i++) {
        const adIndex = state.ads[i].index;

        const adName = document.getElementById(`ad-name-${adIndex}`).value.trim();
        const adText = document.getElementById(`ad-text-${adIndex}`).value.trim();
        const creativeId = document.getElementById(`creative-id-${adIndex}`).value;
        const identityId = document.getElementById(`identity-${adIndex}`).value;
        const destinationUrl = document.getElementById(`destination-url-${adIndex}`).value.trim();

        if (!adName || !adText || !creativeId || !identityId || !destinationUrl) {
            showToast(`Please complete all fields for Ad #${adIndex + 1}`, 'error');
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
    if (!confirm('Are you sure you want to publish all ads? This cannot be undone.')) {
        return;
    }

    showLoading();

    try {
        const createdAdIds = [];

        // Create all ads
        for (let i = 0; i < state.ads.length; i++) {
            const adIndex = state.ads[i].index;

            const adData = {
                adgroup_id: state.adGroupId,
                ad_name: document.getElementById(`ad-name-${adIndex}`).value,
                ad_text: document.getElementById(`ad-text-${adIndex}`).value,
                call_to_action: document.getElementById(`cta-${adIndex}`).value,
                landing_page_url: document.getElementById(`destination-url-${adIndex}`).value,
                identity_id: document.getElementById(`identity-${adIndex}`).value
            };

            const creativeType = document.getElementById(`creative-type-${adIndex}`).value;
            const creativeId = document.getElementById(`creative-id-${adIndex}`).value;

            if (creativeType === 'video') {
                adData.video_id = creativeId;
                adData.ad_format = 'SINGLE_VIDEO';
            } else {
                adData.image_ids = [creativeId];
                adData.ad_format = 'SINGLE_IMAGE';
            }

            const response = await apiRequest('create_ad', adData);

            if (response.success && response.data && response.data.ad_id) {
                createdAdIds.push(response.data.ad_id);
            } else {
                throw new Error(`Failed to create ad: ${response.message}`);
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

    const response = await fetch(url, options);

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
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
