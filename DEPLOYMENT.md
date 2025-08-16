# Deployment Checklist

## 🚀 Production Deployment Guide

### Pre-Deployment Requirements

- [ ] **Server Setup**
  - [ ] PHP 8.2+ installed
  - [ ] Composer installed
  - [ ] Web server (Apache/Nginx) configured
  - [ ] MySQL/PostgreSQL database server
  - [ ] Redis server (for queues)
  - [ ] SSL certificate configured

- [ ] **Infobip Account Setup**
  - [ ] Infobip account created
  - [ ] WhatsApp Business API configured
  - [ ] Message templates created and approved
  - [ ] API credentials obtained
  - [ ] Sender phone number verified

### Environment Configuration

1. **Database Configuration**
```env
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=whatsapp_webhook_prod
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password
```

2. **Queue Configuration**
```env
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
```

3. **Infobip Configuration**
```env
INFOBIP_BASE_URL=https://your-subdomain.api.infobip.com
INFOBIP_API_KEY=your-production-api-key
WABA_SENDER=+9665xxxxxxx
DEFAULT_LANGUAGE=ar
```

4. **Security Configuration**
```env
APP_ENV=production
APP_DEBUG=false
WEBHOOK_SIGNING_SECRET=your-strong-secret-key
ALLOWED_SOURCE_IPS=tawasul-server-ip1,tawasul-server-ip2
```

### Deployment Steps

1. **Clone Repository**
```bash
git clone <repository-url> /var/www/whatsapp-webhook
cd /var/www/whatsapp-webhook
```

2. **Install Dependencies**
```bash
composer install --no-dev --optimize-autoloader
```

3. **Environment Setup**
```bash
cp .env.example .env
# Edit .env with production values
php artisan key:generate
```

4. **Database Migration**
```bash
php artisan migrate --force
```

5. **Optimize Application**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. **Set Permissions**
```bash
chown -R www-data:www-data /var/www/whatsapp-webhook
chmod -R 755 /var/www/whatsapp-webhook
chmod -R 775 /var/www/whatsapp-webhook/storage
chmod -R 775 /var/www/whatsapp-webhook/bootstrap/cache
```

### Queue Worker Setup (Supervisor)

Create `/etc/supervisor/conf.d/whatsapp-webhook-worker.conf`:

```ini
[program:whatsapp-webhook-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/whatsapp-webhook/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/whatsapp-webhook/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start whatsapp-webhook-worker:*
```

### Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/whatsapp-webhook/public;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Monitoring & Logging

1. **Application Logs**
   - Location: `/var/www/whatsapp-webhook/storage/logs/`
   - Rotate logs regularly
   - Monitor for errors and warnings

2. **Queue Monitoring**
```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Restart failed jobs
php artisan queue:retry all
```

3. **Health Check Monitoring**
   - Set up monitoring for `https://your-domain.com/api/healthz`
   - Alert if endpoint returns non-200 status

### Security Checklist

- [ ] **SSL/TLS Configuration**
  - [ ] Valid SSL certificate installed
  - [ ] HTTP redirects to HTTPS
  - [ ] Strong cipher suites configured

- [ ] **Application Security**
  - [ ] `APP_DEBUG=false` in production
  - [ ] Strong `APP_KEY` generated
  - [ ] Webhook signing secret configured
  - [ ] IP allowlist configured for webhook endpoint

- [ ] **Server Security**
  - [ ] Firewall configured (only necessary ports open)
  - [ ] Regular security updates applied
  - [ ] Database access restricted
  - [ ] File permissions properly set

### Testing Production Deployment

1. **Health Check**
```bash
curl https://your-domain.com/api/healthz
```

2. **Webhook Test**
```bash
curl -X POST https://your-domain.com/api/fms/alerts \
  -H "Content-Type: application/json" \
  -H "X-Tawasul-Signature: sha256=your-signature" \
  -d '{"vehicle_id":"TEST-001","type":"Overspeed","phone":"+966500000000","occurred_at":"2025-08-16T10:00:00Z"}'
```

3. **Queue Processing**
```bash
# Check if workers are running
sudo supervisorctl status whatsapp-webhook-worker:*

# Monitor queue in real-time
php artisan queue:monitor
```

### Tawasul Cloud Configuration

In Tawasul Cloud FMS:
1. Navigate to Alert Configuration
2. Set webhook URL: `https://your-domain.com/api/fms/alerts`
3. Configure HMAC signature if using webhook signing
4. Test webhook delivery

### Backup Strategy

1. **Database Backups**
```bash
# Daily backup script
mysqldump -u username -p whatsapp_webhook_prod > backup_$(date +%Y%m%d).sql
```

2. **Application Backups**
```bash
# Backup application files (excluding vendor and node_modules)
tar -czf app_backup_$(date +%Y%m%d).tar.gz --exclude=vendor --exclude=node_modules /var/www/whatsapp-webhook
```

### Maintenance

- [ ] **Regular Updates**
  - [ ] Laravel framework updates
  - [ ] PHP security updates
  - [ ] Server OS updates

- [ ] **Log Rotation**
  - [ ] Configure logrotate for Laravel logs
  - [ ] Monitor disk space usage

- [ ] **Performance Monitoring**
  - [ ] Monitor response times
  - [ ] Monitor queue processing times
  - [ ] Monitor database performance

### Troubleshooting

Common production issues and solutions:

1. **Queue Not Processing**
   - Check supervisor status
   - Verify Redis connection
   - Check worker logs

2. **Database Connection Issues**
   - Verify database credentials
   - Check database server status
   - Review connection limits

3. **Webhook 404 Errors**
   - Verify web server configuration
   - Check Laravel route caching
   - Confirm API routes are registered

4. **Infobip API Errors**
   - Verify API credentials
   - Check template names and approval status
   - Review rate limits and quotas

### Support Contacts

- **Technical Support**: [Your team contact]
- **Infobip Support**: [Infobip support details]
- **Tawasul Support**: [Tawasul support details]
