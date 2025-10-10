# PHP Upload Configuration for Video Files

If you're getting "File exceeds upload_max_filesize" errors when uploading videos, you need to increase the PHP upload limits.

## Solution 1: Using .htaccess (Recommended for Apache)

A `.htaccess` file has been created in this directory with the following settings:

```apache
php_value upload_max_filesize 500M
php_value post_max_size 510M
php_value memory_limit 512M
php_value max_execution_time 300
php_value max_input_time 300
```

**This should work automatically on most Apache servers.**

## Solution 2: Using php.ini (For servers that don't support .htaccess)

A `php.ini` file has been created in this directory with:

```ini
upload_max_filesize = 500M
post_max_size = 510M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
```

**This file should be automatically loaded by PHP.**

## Solution 3: Server-level php.ini (If above methods don't work)

If you have access to your server's php.ini file (usually in `/etc/php/8.x/apache2/php.ini` or similar), edit it and change:

```ini
upload_max_filesize = 500M
post_max_size = 510M
memory_limit = 512M
max_execution_time = 300
```

Then restart your web server:
```bash
sudo systemctl restart apache2
# or
sudo systemctl restart nginx
```

## Solution 4: Contact Your Hosting Provider

If you're on shared hosting and can't modify these settings, contact your hosting provider and ask them to:

1. Increase `upload_max_filesize` to **500M**
2. Increase `post_max_size` to **510M**
3. Increase `memory_limit` to **512M**

## Verify Configuration

To check if the changes worked, the error message will show the current limit. The API logs will also display:
- Current upload_max_filesize
- Current post_max_size
- Current memory_limit

## Why These Limits?

- **upload_max_filesize**: Maximum size of uploaded files (500MB for TikTok videos)
- **post_max_size**: Must be larger than upload_max_filesize (510MB)
- **memory_limit**: PHP memory needed to process uploads (512MB)
- **max_execution_time**: Time allowed for upload processing (300 seconds = 5 minutes)
