# Deployment Guide

## Deploy to Render

### Option 1: Using render.yaml (Recommended)

1. **Push code to GitHub** (already done)
   ```
   https://github.com/pearmediallc/tiktok-launcher
   ```

2. **Connect to Render**
   - Go to [Render Dashboard](https://dashboard.render.com/)
   - Click "New +" → "Web Service"
   - Connect your GitHub repository
   - Select `tiktok-launcher` repository

3. **Configuration** (Auto-detected from render.yaml)
   - **Name**: tiktok-launcher
   - **Environment**: PHP
   - **Build Command**: `cd sdk && composer install`
   - **Start Command**: `php -S 0.0.0.0:$PORT`

4. **Add Environment Variables**
   ```
   AUTH_USERNAME=Sunny
   AUTH_PASSWORD=D3v3lop3r@Pear2001
   TIKTOK_ACCESS_TOKEN=4abc2c99e47b72673a2eb49b3b277ab5f4a840e1
   TIKTOK_ADVERTISER_ID=7546384313781125137
   ```

5. **Deploy**
   - Click "Create Web Service"
   - Wait for deployment to complete
   - Access your app at: `https://your-app-name.onrender.com`

### Option 2: Using Dockerfile

If you prefer Docker:

1. In Render dashboard, select "Docker" as environment
2. **Dockerfile Path**: `./Dockerfile`
3. Add the same environment variables
4. Deploy

### Option 3: Manual Configuration

If render.yaml is not detected:

**Build Command:**
```bash
cd sdk && composer install --no-dev --optimize-autoloader
```

**Start Command:**
```bash
php -S 0.0.0.0:$PORT
```

---

## Deploy to Other Platforms

### Heroku

1. **Create Procfile**
   ```
   web: php -S 0.0.0.0:$PORT
   ```

2. **Deploy**
   ```bash
   heroku create tiktok-launcher
   heroku config:set AUTH_USERNAME=Sunny
   heroku config:set AUTH_PASSWORD=D3v3lop3r@Pear2001
   heroku config:set TIKTOK_ACCESS_TOKEN=your_token
   heroku config:set TIKTOK_ADVERTISER_ID=your_id
   git push heroku main
   ```

### Railway

1. **Connect GitHub repo**
2. **Add Environment Variables** in Railway dashboard
3. **Deploy Command**: Auto-detected
4. Railway will automatically build and deploy

### DigitalOcean App Platform

1. **Connect GitHub repository**
2. **Detected Language**: PHP
3. **Build Command**: `cd sdk && composer install`
4. **Run Command**: `php -S 0.0.0.0:8080`
5. Add environment variables
6. Deploy

### Traditional VPS (Ubuntu/Debian)

```bash
# Install dependencies
sudo apt update
sudo apt install php8.1 php8.1-curl php8.1-mbstring composer apache2

# Clone repository
git clone https://github.com/pearmediallc/tiktok-launcher.git
cd tiktok-launcher

# Install SDK dependencies
cd sdk && composer install && cd ..

# Configure Apache
sudo cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/tiktok-launcher.conf

# Edit the config file
sudo nano /etc/apache2/sites-available/tiktok-launcher.conf

# Add DocumentRoot to your app directory:
# DocumentRoot /path/to/tiktok-launcher

# Enable site and restart Apache
sudo a2ensite tiktok-launcher
sudo systemctl restart apache2
```

---

## Environment Variables Required

| Variable | Description | Example |
|----------|-------------|---------|
| `AUTH_USERNAME` | Dashboard login username | `Sunny` |
| `AUTH_PASSWORD` | Dashboard login password | `D3v3lop3r@Pear2001` |
| `TIKTOK_ACCESS_TOKEN` | TikTok Business API access token | `4abc2c99...` |
| `TIKTOK_ADVERTISER_ID` | TikTok advertiser account ID | `7546384313781125137` |

---

## Post-Deployment Checklist

- [ ] Application accessible via URL
- [ ] Login works with credentials
- [ ] TikTok API connection successful
- [ ] Can create test campaign
- [ ] Media upload works
- [ ] Environment variables are set
- [ ] HTTPS is enabled (Render provides this automatically)

---

## Troubleshooting

### "Composer not found"
- Ensure build command includes: `cd sdk && composer install`

### "Cannot connect to TikTok API"
- Verify `TIKTOK_ACCESS_TOKEN` is correct
- Check `TIKTOK_ADVERTISER_ID` is correct
- Ensure token hasn't expired

### "Unauthorized" on login
- Verify `AUTH_USERNAME` and `AUTH_PASSWORD` environment variables
- Check `.env` file is not being used (use environment variables instead)

### "Port already in use"
- Render automatically provides `$PORT` variable
- Use `php -S 0.0.0.0:$PORT` not hardcoded port

### Session issues
- Enable session support in PHP
- For production, consider using Redis for sessions

---

## Quick Deploy to Render

**One-click summary:**

1. Go to: https://dashboard.render.com/
2. New Web Service
3. Connect: `github.com/pearmediallc/tiktok-launcher`
4. Build: `cd sdk && composer install`
5. Start: `php -S 0.0.0.0:$PORT`
6. Add 4 environment variables (see above)
7. Deploy! 🚀

Your app will be live at: `https://tiktok-launcher.onrender.com`
