# Quick Start Guide

Get your TikTok Campaign Launcher running in 5 minutes!

## Prerequisites

- PHP 7.4+ installed
- Composer installed
- TikTok Business API credentials

## Step 1: Install Dependencies

```bash
cd tiktok-business-ads-api-php-sdk
composer install
cd ..
```

## Step 2: Verify Configuration

Check that `.env` file exists in the root directory with:

```env
AUTH_USERNAME=Sunny
AUTH_PASSWORD=D3v3lop3r@Pear2001
TIKTOK_ACCESS_TOKEN=4abc2c99e47b72673a2eb49b3b277ab5f4a840e1
TIKTOK_ADVERTISER_ID=7546384313781125137
```

## Step 3: Start the Server

```bash
php -S localhost:8000
```

## Step 4: Access the Application

Open your browser and go to:
```
http://localhost:8000
```

## Step 5: Login

- **Username**: `Sunny` (from .env)
- **Password**: `D3v3lop3r@Pear2001` (from .env)

## Step 6: Create Your First Campaign

1. **Campaign Tab**
   - Enter campaign name
   - Click "Continue to Ad Group"

2. **Ad Group Tab**
   - Enter ad group name
   - Set daily budget (min $20)
   - Select start date
   - Choose timezone
   - (Optional) Enable dayparting for specific hours
   - Set bid amount
   - Click "Continue to Ads"

3. **Ads Tab**
   - Enter ad name
   - Click placeholder to select/upload media
   - Select identity from dropdown
   - Write ad copy
   - Choose call-to-action
   - Enter destination URL
   - Click "Duplicate Last Ad" for more ads
   - Click "Review & Publish"

4. **Review & Publish**
   - Review all settings
   - Click "Publish All"

## Workflow Summary

```
Login → Create Campaign → Setup Ad Group → Create Ads → Review → Publish
```

## Common Tasks

### Upload Media
1. Go to Ads step
2. Click creative placeholder
3. Switch to "Upload New" tab
4. Drag & drop or select file
5. Wait for upload
6. Switch back to "Library" tab
7. Select uploaded media

### Duplicate Ads
1. Create first ad with all settings
2. Click "Duplicate Last Ad" button
3. Modify ad name, creative, or copy
4. Repeat as needed

### View Created Campaigns
- Use TikTok Ads Manager to view/monitor campaigns
- This tool creates campaigns in your TikTok account

## Troubleshooting

**Can't login?**
- Check username/password in `.env` file

**API errors?**
- Verify `TIKTOK_ACCESS_TOKEN` is valid
- Check `TIKTOK_ADVERTISER_ID` is correct
- Ensure API access is enabled in TikTok Business

**No identities?**
- Create identities in TikTok Ads Manager first
- They will appear in the dropdown automatically

**Upload fails?**
- Check file size and format
- Ensure stable internet connection
- Try smaller files first

## Production Deployment

For production use:

1. **Use a proper web server** (Apache/Nginx)
2. **Enable HTTPS**
3. **Change authentication credentials** in `.env`
4. **Secure your `.env` file** (chmod 600)
5. **Keep access tokens secret**

## Next Steps

- Read full [README.md](README.md) for detailed documentation
- Check [TikTok API Docs](https://business-api.tiktok.com/portal/docs) for API limits
- Create multiple campaign templates
- Set up backup and monitoring

## Support

If you encounter issues:
1. Check browser console for errors
2. Verify PHP error logs
3. Test API credentials in TikTok Ads Manager
4. Review network requests in browser DevTools

---

**Ready to scale your TikTok advertising!** 🚀
