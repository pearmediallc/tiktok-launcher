# TikTok Campaign Launcher - Debugging Guide

## Issue: Ad Creation Not Working

### 1. Check Browser Console
Open browser DevTools (F12) and look for these console logs:

When clicking "Review & Publish":
```javascript
=====================================
Review Ads button clicked
Current state: {campaignId: "...", adGroupId: "...", ads: [...]}
Number of ads: X
Campaign ID: XXXXXXXXX
Ad Group ID: XXXXXXXXX
=====================================
```

### 2. Common Issues & Solutions

#### No Campaign/Ad Group ID
**Error:** "Please create a campaign first (Step 1)"
**Solution:** Complete Steps 1 and 2 before trying to create ads

#### No Identity Selected
**Error:** "Please select an identity for Ad #1"
**Solution:** Select an identity from the dropdown (it's required by TikTok)

#### API Not Responding
**Check:** Look at the API Logs panel at the bottom of the page
**Solution:** Verify your access token is valid

### 3. Video Thumbnails
Videos should now display with thumbnails using `video_cover_url` from the API.
If not showing:
- Check console for API response
- Look for `video_cover_url` in the response
- Fallback to gradient placeholder if no URL

### 4. Required Fields for Ad Creation

According to TikTok API v1.3 documentation:
- **identity_type**: REQUIRED (usually "CUSTOMIZED_USER")
- **identity_id**: REQUIRED (select from dropdown)
- **video_id**: REQUIRED for video ads
- **image_ids**: REQUIRED for video cover/thumbnail
- **ad_name**: Can be empty string for auto-generation
- **ad_text**: REQUIRED for non-Spark ads
- **landing_page_url**: REQUIRED

### 5. Testing Flow

1. **Create Campaign (Step 1)**
   - Fill all fields
   - Click "Create Campaign"
   - Note the Campaign ID in console

2. **Create Ad Group (Step 2)**
   - Fill all fields
   - Click "Create Ad Group"
   - Note the Ad Group ID in console

3. **Create Ads (Step 3)**
   - Add ad name and text
   - Select media (video/image)
   - **SELECT IDENTITY** (required!)
   - Add landing page URL
   - Click "Review & Publish"

4. **Review & Publish (Step 4)**
   - Review all details
   - Click "Publish All"
   - Confirm in popup

### 6. API Logs Panel

The logs panel at the bottom shows:
- REQUEST: API calls being made
- RESPONSE: Server responses
- ERROR: Any errors

Toggle with ▼/▲ button to show/hide.

### 7. Check Server Logs

SSH to server and check:
```bash
tail -f api_log.txt
```

This shows server-side API calls to TikTok.

### 8. Common API Errors

- **40001**: Permission denied - Check access token
- **40002**: Required parameter missing
- **Identity not found**: Create identity in TikTok Ads Manager
- **Invalid video_id**: Video doesn't exist or wrong advertiser

### 9. Quick Test

Run this to test identity fetching:
```bash
php test_identities.php
```

This shows all available identities for your account.

### 10. If Nothing Works

1. Clear browser cache
2. Check access token is valid
3. Verify advertiser ID is correct
4. Create a custom identity in TikTok Ads Manager
5. Check network tab in DevTools for failed requests

### Debug Mode

Add `?debug=1` to URL to see more detailed logging:
```
https://tiktok-launcher.onrender.com/dashboard.php?debug=1
```

This will show all API requests and responses in console.
