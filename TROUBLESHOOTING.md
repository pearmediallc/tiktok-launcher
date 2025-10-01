# Troubleshooting Guide

## "Complete payment to continue" Error

### Problem
When creating a campaign and moving to the Ad Group step, you see: **"Complete payment to continue"**

### Root Cause
This error comes directly from TikTok's API and means:
- No payment method is added to your TikTok Ads account
- Account verification is incomplete
- Billing issues with the advertiser account

### Solution 1: Add Payment Method (Required for Production)

1. **Login to TikTok Ads Manager**
   - Go to: https://ads.tiktok.com/
   - Login with your advertiser account

2. **Navigate to Billing**
   - Click on **Settings** (gear icon)
   - Go to **Billing & Payments**

3. **Add Payment Method**
   - Click **Add Payment Method**
   - Enter credit card details
   - Complete verification

4. **Verify Account**
   - Complete any pending account verification steps
   - Confirm billing address
   - Accept terms and conditions

5. **Retry Campaign Creation**
   - Go back to your launcher app
   - Try creating campaign again

### Solution 2: Use Sandbox Mode (Testing Only)

For testing without payment method:

1. **Get Sandbox Credentials**
   - Contact TikTok support for sandbox access
   - Or use TikTok's test advertiser account

2. **Update Environment Variables**
   - Add to your `.env` file:
   ```
   TIKTOK_ENVIRONMENT=sandbox
   ```

3. **Restart Application**
   - If local: restart PHP server
   - If Render: add environment variable in dashboard

**Note:** Sandbox mode won't create real ads, but you can test the workflow.

---

## Other Common Errors

### Error: "Invalid access token"

**Cause:** Access token expired or incorrect

**Solution:**
1. Go to TikTok for Business: https://business-api.tiktok.com/
2. Generate new access token
3. Update `TIKTOK_ACCESS_TOKEN` in environment variables
4. Restart application

### Error: "Advertiser not found"

**Cause:** Wrong advertiser ID

**Solution:**
1. Login to TikTok Ads Manager
2. Check URL for advertiser ID: `https://ads.tiktok.com/i18n/dashboard?aadvid=YOUR_ID_HERE`
3. Update `TIKTOK_ADVERTISER_ID` in environment variables

### Error: "Unauthorized" on login

**Cause:** Wrong username/password

**Solution:**
1. Check `AUTH_USERNAME` and `AUTH_PASSWORD` in `.env`
2. Update if needed
3. Clear browser cookies
4. Try logging in again

### Error: "No identities available"

**Cause:** No TikTok identities created in Ads Manager

**Solution:**
1. Go to TikTok Ads Manager
2. Navigate to **Assets** → **Identities**
3. Create at least one identity
4. Refresh the launcher dashboard

### Error: "Media upload failed"

**Cause:** File size/format issues or API limits

**Solution:**
1. **Check file size:**
   - Images: Max 5MB
   - Videos: Max 500MB
2. **Check format:**
   - Images: JPG, PNG, GIF
   - Videos: MP4, MOV, MPEG, AVI
3. **Reduce file size** if needed
4. Try uploading again

### Error: "Rate limit exceeded"

**Cause:** Too many API requests in short time

**Solution:**
1. Wait 5-10 minutes
2. Try again
3. Don't spam create/delete operations

---

## TikTok Account Requirements

Before using this tool, ensure your TikTok Ads account has:

### ✅ Required:
- [ ] Verified TikTok Ads account
- [ ] Payment method added
- [ ] At least one identity created
- [ ] Business verification completed (for some regions)
- [ ] Valid access token (not expired)
- [ ] Correct advertiser ID

### ✅ Recommended:
- [ ] Pixel installed (for conversion tracking)
- [ ] At least one data connection set up
- [ ] Test budget available
- [ ] Account not suspended/restricted

---

## Checking Your TikTok Account Status

### 1. Verify Payment Method
```
TikTok Ads Manager → Settings → Billing & Payments
Look for: "Payment Method: [Credit Card ending in XXXX]"
```

### 2. Check Account Balance
```
TikTok Ads Manager → Dashboard
Look for: Account balance or credit limit
```

### 3. Verify Access Token
Test your token with this curl command:
```bash
curl -X GET 'https://business-api.tiktok.com/open_api/v1.3/advertiser/info/' \
  -H 'Access-Token: YOUR_ACCESS_TOKEN' \
  -d 'advertiser_ids=["YOUR_ADVERTISER_ID"]'
```

Expected response: `{"code":0,"message":"OK",...}`

### 4. Check API Permissions
```
TikTok for Business → My Apps → Your App → Permissions
Ensure these are enabled:
- Ad Account Management (Read/Write)
- Campaign Management (Read/Write)
- Ad Group Management (Read/Write)
- Ad Management (Read/Write)
```

---

## Still Having Issues?

### Debug Steps:

1. **Check Browser Console**
   - Open DevTools (F12)
   - Go to Console tab
   - Look for red errors
   - Share error messages

2. **Check Network Requests**
   - Open DevTools (F12)
   - Go to Network tab
   - Filter by "api.php"
   - Click on failed request
   - Check Response tab
   - Share response body

3. **Check Server Logs** (If on Render)
   - Go to Render Dashboard
   - Click on your service
   - Go to Logs tab
   - Look for PHP errors or warnings

4. **Test Locally**
   - Clone repository locally
   - Run `php -S localhost:8000`
   - Try creating campaign
   - Check terminal for errors

### Contact Information:

- **TikTok Support:** https://ads.tiktok.com/help/
- **TikTok API Documentation:** https://business-api.tiktok.com/portal/docs
- **GitHub Issues:** https://github.com/pearmediallc/tiktok-launcher/issues

---

## Quick Checklist Before Creating Campaigns

Before using the launcher, verify:

- [ ] Logged into TikTok Ads Manager successfully
- [ ] Payment method is active and verified
- [ ] At least one identity exists
- [ ] Access token is valid (not expired)
- [ ] Advertiser ID is correct
- [ ] Account has available budget/credit
- [ ] No account restrictions or suspensions

If all items are checked, the launcher should work properly! ✅
