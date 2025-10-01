# TikTok Campaign Launcher

A production-ready web application for automating TikTok ad campaign creation through the TikTok Business API. This tool allows you to create campaigns, ad groups, and multiple ads with an intuitive interface.

## Features

- ✅ **Secure Authentication** - Login dashboard with credentials
- ✅ **Campaign Creation** - Create manual Lead Generation campaigns
- ✅ **Ad Group Management** - Set budget, schedule, timezone, dayparting, and bidding
- ✅ **Multiple Ads** - Create and duplicate ads with ease
- ✅ **Media Library** - Upload and manage images/videos
- ✅ **Identity Selection** - Choose custom identities for your ads
- ✅ **Call-to-Action** - Select from multiple CTA options
- ✅ **Review & Publish** - Review all settings before publishing
- ✅ **Batch Operations** - Publish all ads at once

## Requirements

- PHP 7.4 or higher
- Composer (for dependencies)
- TikTok Business API Access Token
- TikTok Advertiser ID
- Web server (Apache, Nginx, or PHP built-in server)

## Installation

### 1. Install Dependencies

```bash
cd sdk
composer install
```

### 2. Configure Environment

The `.env` file is already configured in the root directory. Make sure it contains:

```env
# Authentication Credentials
AUTH_USERNAME=Sunny
AUTH_PASSWORD=D3v3lop3r@Pear2001

# TikTok API Configuration
TIKTOK_ACCESS_TOKEN=your_access_token_here
TIKTOK_ADVERTISER_ID=your_advertiser_id_here
```

### 3. Start the Application

#### Option A: PHP Built-in Server (Development)

```bash
php -S localhost:8000
```

Then visit: http://localhost:8000

#### Option B: Apache/Nginx (Production)

1. Point your web server document root to this directory
2. Ensure mod_rewrite is enabled (for Apache)
3. Access via your domain or server IP

## File Structure

```
launcher/
├── index.php                           # Login page
├── dashboard.php                       # Main dashboard
├── api.php                            # Backend API endpoints
├── app.js                             # Frontend JavaScript
├── style.css                          # Styling
├── .env                               # Environment configuration
├── README.md                          # This file
└── sdk/   # TikTok SDK
    ├── src/
    │   └── TikTokAds/
    │       ├── Campaign/              # Campaign endpoints
    │       ├── AdGroup/               # Ad Group endpoints
    │       ├── Ad/                    # Ad endpoints
    │       ├── File/                  # File upload endpoints
    │       ├── Identity/              # Identity endpoints
    │       └── Tools/                 # Tools & resources
    └── vendor/                        # Composer dependencies
```

## Usage Guide

### Step 1: Login

1. Navigate to `index.php`
2. Enter your credentials (from `.env`)
3. Click "Login"

### Step 2: Create Campaign

1. Enter a campaign name
2. The objective is automatically set to "Lead Generation"
3. Campaign type is "Manual"
4. Click "Continue to Ad Group"

### Step 3: Create Ad Group

1. **Ad Group Name**: Enter a descriptive name
2. **Budget**: Set daily budget (minimum $20)
3. **Start Date**: Select when the campaign should start
4. **Timezone**: Choose your timezone (default: UTC -5:00 Panama)
5. **Dayparting** (Optional):
   - Enable checkbox to select specific hours
   - Click hours in the grid to enable/disable
6. **Bid Amount**: Set your bid price
7. Click "Continue to Ads"

### Step 4: Create Ads

For each ad:

1. **Ad Name**: Enter unique ad name
2. **Creative**:
   - Click placeholder to open media library
   - Upload new media or select from library
   - Click refresh icon to reload library
3. **Identity**: Select your TikTok identity
4. **Ad Copy**: Enter your ad text
5. **Call to Action**: Select CTA button (Apply Now, Sign Up, etc.)
6. **Destination URL**: Enter landing page URL
7. **URL Parameters**: Auto-add tracking parameters (checked by default)

#### Duplicate Ads

- Click "Duplicate Last Ad" to create a copy with all settings
- Modify the duplicated ad as needed
- Repeat for multiple variations

### Step 5: Review & Publish

1. Review campaign summary
2. Review ad group settings
3. Review all ads
4. Click "Publish All" to launch your campaign

## API Endpoints

The `api.php` file provides these endpoints:

- `create_campaign` - Create a new campaign
- `create_adgroup` - Create an ad group
- `create_ad` - Create an ad
- `upload_image` - Upload image to library
- `upload_video` - Upload video to library
- `get_identities` - Fetch available identities
- `get_images` - Get image library
- `get_videos` - Get video library
- `get_campaigns` - List campaigns
- `get_adgroups` - List ad groups
- `get_ads` - List ads
- `publish_ads` - Publish ads (set status to ENABLE)
- `duplicate_ad` - Duplicate an existing ad
- `duplicate_adgroup` - Duplicate an ad group

## Campaign Settings

### Default Settings

- **Objective**: Lead Generation
- **Campaign Type**: Manual
- **Optimization Goal**: Conversion
- **Event**: Lead
- **Location**: United States
- **Placement**: TikTok only
- **Budget Mode**: Daily budget
- **Billing Event**: CPC (Cost Per Click)

### Call-to-Action Options

- Apply Now
- Sign Up
- Learn More
- Download
- Shop Now
- Watch Now

### Timezone Options

- UTC -5:00 East Standard Time (Panama)
- UTC -5:00 Eastern Time (New York)
- UTC -6:00 Central Time (Chicago)
- UTC -7:00 Mountain Time (Denver)
- UTC -8:00 Pacific Time (Los Angeles)

## Troubleshooting

### "Unauthorized" Error
- Check your `.env` credentials
- Ensure you're logged in
- Session may have expired - try logging in again

### "Failed to create campaign"
- Verify TikTok access token is valid
- Check advertiser ID is correct
- Ensure API access is enabled

### Media Upload Issues
- Check file size limits (varies by TikTok)
- Ensure file format is supported (JPG, PNG, MP4, etc.)
- Verify TikTok API upload permissions

### No Identities Available
- Create identities in TikTok Ads Manager first
- Refresh the page
- Check API permissions

## Security Notes

- Never commit `.env` file to version control
- Change default credentials in production
- Use HTTPS in production
- Keep access tokens secure
- Regularly rotate API credentials

## SDK Extensions

The following classes were added to the TikTok SDK:

### File Management (`File.php`)
- Upload images and videos
- Get media library information

### Identity Management (`Identity.php`)
- Fetch available identities
- Create new identities

### Tools & Resources (`Tools.php`)
- Get regions
- Get languages
- Get timezones
- Get app list

### Ad Updates (`Ad.php`)
- Added `getSelf()` method
- Added `update()` method
- Added `statusUpdate()` method

## Production Deployment

### Apache Configuration

Create `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L]
```

### Nginx Configuration

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Environment Variables

For production, use environment variables instead of `.env` file:

```bash
export AUTH_USERNAME="your_username"
export AUTH_PASSWORD="your_password"
export TIKTOK_ACCESS_TOKEN="your_token"
export TIKTOK_ADVERTISER_ID="your_id"
```

## Support

For TikTok API documentation:
- [TikTok Business API Docs](https://business-api.tiktok.com/portal/docs)

## License

MIT License - See LICENSE file for details

## Credits

Built with:
- TikTok Business Ads API PHP SDK by Justin Stolpe
- Vanilla JavaScript (no frameworks)
- Pure CSS (no CSS frameworks)
