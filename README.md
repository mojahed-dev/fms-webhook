# Tawasul Cloud WhatsApp Webhook System

A Laravel-based webhook system that receives fleet management alerts from Tawasul Cloud and sends WhatsApp notifications via Infobip API.

## 🚀 Features

- **Real-time Webhook Processing**: Receives alerts from Tawasul Cloud FMS
- **WhatsApp Integration**: Sends template messages via Infobip API
- **Alert Type Mapping**: Configurable mapping of alert types to WhatsApp templates
- **Queue System**: Asynchronous message processing with retry logic
- **Idempotency**: Prevents duplicate alert processing
- **Security**: Optional IP allowlist and HMAC signature verification
- **Database Logging**: Stores alerts and message delivery status

## 📋 System Requirements

- PHP 8.2+
- Laravel 12+
- MySQL/SQLite database
- Composer
- Infobip WhatsApp Business API account

## 🛠️ Installation & Setup

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd whatsapp-webhook
composer install
```

### 2. Environment Configuration

Copy `.env.example` to `.env` and configure:

```env
# Application
APP_NAME=TawasulCloudWhatsAppWebhook
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Database (SQLite for local, MySQL for production)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Queue System
QUEUE_CONNECTION=database

# Infobip WhatsApp API
INFOBIP_BASE_URL=https://<your-subdomain>.api.infobip.com
INFOBIP_API_KEY=your-api-key-here
WABA_SENDER=+9665xxxxxxx
DEFAULT_LANGUAGE=ar

# Security (Optional)
WEBHOOK_SIGNING_SECRET=your-secret-key
ALLOWED_SOURCE_IPS=192.168.1.100,10.0.0.50
```

### 3. Database Setup

```bash
# Create SQLite database file (for local development)
touch database/database.sqlite

# Run migrations
php artisan migrate
```

### 4. Start the Application

```bash
# Start Laravel development server
php artisan serve

# In another terminal, start queue worker
php artisan queue:work --tries=3
```

## 🔧 Configuration

### Alert Type Mapping

Configure alert types in `config/alerts.php`:

```php
return [
    'Overspeed'          => ['template' => 'overspeed_alert_ar',   'priority' => 'high'],
    'Geofence Out'       => ['template' => 'geofence_exit_ar',     'priority' => 'high'],
    'SOS'                => ['template' => 'sos_alert_ar',         'priority' => 'crit'],
    'Fuel (Fill/Theft)'  => ['template' => 'fuel_alert_ar',        'priority' => 'normal'],
    'Ignition ON/OFF'    => ['template' => 'ignition_alert_ar',    'priority' => 'normal'],
];
```

## 📡 API Endpoints

### Webhook Receiver
```
POST /api/fms/alerts
Content-Type: application/json
```

**Request Payload:**
```json
{
  "vehicle_id": "2665 RHA",
  "customer_id": "CUST-123",
  "type": "Overspeed",
  "occurred_at": "2025-08-14T07:50:12Z",
  "phone": "+9665XXXXXXX",
  "location": {
    "lat": 24.7136,
    "lng": 46.6753
  }
}
```

**Response:**
```json
{
  "queued": true,
  "alert_id": 123
}
```

### Health Check
```
GET /api/healthz
```

**Response:**
```json
{
  "ok": true
}
```

## 🧪 Testing

### Test Webhook Endpoint

```bash
# Using PowerShell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/fms/alerts" -Method POST -Headers @{"Content-Type"="application/json"} -Body '{"vehicle_id":"2665 RHA","customer_id":"CUST-123","type":"Overspeed","occurred_at":"2025-08-14T07:50:12Z","phone":"+9665XXXXXXX","location":{"lat":24.7136,"lng":46.6753}}'

# Using curl (if available)
curl -X POST http://127.0.0.1:8000/api/fms/alerts \
  -H "Content-Type: application/json" \
  -d '{"vehicle_id":"2665 RHA","customer_id":"CUST-123","type":"Overspeed","occurred_at":"2025-08-14T07:50:12Z","phone":"+9665XXXXXXX","location":{"lat":24.7136,"lng":46.6753}}'
```

### Test Health Endpoint

```bash
# PowerShell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/healthz" -Method GET

# curl
curl http://127.0.0.1:8000/api/healthz
```

## 🏗️ Architecture

### Components

1. **FmsAlertController**: Handles incoming webhooks
2. **InfobipClient**: Service for WhatsApp API integration
3. **SendWhatsappAlert**: Queue job for message processing
4. **Alert Model**: Stores alert data with idempotency
5. **Message Model**: Tracks message delivery status

### Flow

1. Tawasul Cloud sends webhook to `/api/fms/alerts`
2. Controller validates payload and maps alert type
3. Creates Alert record with idempotency key
4. Creates Message record and dispatches queue job
5. Queue worker sends WhatsApp message via Infobip
6. Updates message status based on API response

### Database Schema

**alerts table:**
- `id`, `idempotency_key`, `event_id`, `vehicle_id`
- `customer_id`, `alert_type`, `occurred_at`, `payload`

**messages table:**
- `id`, `alert_id`, `to_msisdn`, `template_code`
- `language`, `status`, `provider_msg_id`, `attempts`, `last_error`

## 🔒 Security Features

- **IP Allowlist**: Restrict webhook access to specific IPs
- **HMAC Signature**: Verify webhook authenticity
- **Idempotency**: Prevent duplicate processing
- **Rate Limiting**: Built-in Laravel rate limiting

## 🚀 Production Deployment

### Environment Setup

1. Use MySQL/PostgreSQL instead of SQLite
2. Configure Redis for queue backend
3. Set up proper logging and monitoring
4. Enable HTTPS with SSL certificates
5. Configure firewall and security groups

### Queue Management

```bash
# Use Supervisor for queue worker management
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### Monitoring

- Monitor queue jobs: `php artisan queue:monitor`
- Check failed jobs: `php artisan queue:failed`
- Application logs: `storage/logs/laravel.log`

## 📞 Infobip WhatsApp Setup

1. Create Infobip account and get API credentials
2. Set up WhatsApp Business API integration
3. Create and approve WhatsApp message templates
4. Configure sender phone number (WABA_SENDER)

### Template Examples

Templates should include placeholders for:
- `{{1}}` - Vehicle ID
- `{{2}}` - Alert Type
- `{{3}}` - Occurred At
- `{{4}}` - Latitude
- `{{5}}` - Longitude

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection**: Ensure database is running and credentials are correct
2. **Queue Not Processing**: Start queue worker with `php artisan queue:work`
3. **Infobip API Errors**: Check API credentials and template names
4. **404 Errors**: Ensure API routes are registered in `bootstrap/app.php`

### Debug Commands

```bash
# Check routes
php artisan route:list

# Test queue jobs
php artisan queue:work --once

# Clear caches
php artisan config:clear
php artisan route:clear
```

## 📝 License

This project is licensed under the MIT License.

## 🤝 Support

For support and questions, please contact the development team.
