# WhatsApp Alerts Implementation Guide

## Overview
This implementation adds WhatsApp alert functionality to the FMS webhook system using Infobip's WhatsApp Business API with approved English templates.

## Features Implemented

### 1. English WhatsApp Templates Configuration
- **File**: `config/alerts.php`
- **Templates Added**:
  - `overspeed_alert_en` for overspeed alerts
  - `ignition_on_alert_en` for ignition on alerts
  - `ignition_off_alert_en` for ignition off alerts
  - Additional templates for geofence, SOS, and fuel alerts

### 2. Enhanced Payload Processing
- **File**: `app/Http/Controllers/FmsAlertController.php`
- **Improvements**:
  - Extracts `alert_type`, `message`, and `phone_number` from multiple payload locations
  - Supports nested payload structures (`body.user.phone_number`, etc.)
  - Automatically detects language based on template suffix (`_en` = English)
  - Enhanced placeholder data extraction (speed, location, address)

### 3. Queue Job Enhancement
- **File**: `app/Jobs/SendWhatsappAlert.php`
- **Features**:
  - Enhanced logging for success/failure tracking
  - Better error handling with detailed logging
  - Support for alert type context in logs
  - Retry mechanism with backoff

### 4. Infobip Client Enhancement
- **File**: `app/Services/InfobipClient.php`
- **New Methods**:
  - `sendTemplateMessage()` - Main template sending method
  - `sendTemplateWithParams()` - Helper method with parameter extraction
  - Improved payload formatting for Infobip API
  - Better timeout handling (30s timeout, 5s connection timeout)
  - Debug logging for API calls

### 5. Testing Infrastructure

#### API Test Routes
- **GET** `/api/test/whatsapp` - List available templates and test endpoints
- **POST** `/api/test/whatsapp/{alertType}` - Test specific alert type
  - Supported types: `overspeed`, `ignition_on`, `ignition_off`
  - Optional `phone` parameter (defaults to `+966500000000`)

#### Artisan Command
- **Command**: `php artisan whatsapp:test {alert_type} {phone} [options]`
- **Options**:
  - `--vehicle_id=TEST-CMD` - Custom vehicle ID
  - `--direct` - Send directly without queue
- **Examples**:
  ```bash
  php artisan whatsapp:test overspeed +966501234567
  php artisan whatsapp:test ignition_on +966501234567 --direct
  php artisan whatsapp:test ignition_off +966501234567 --vehicle_id=TRUCK-001
  ```

## Configuration Requirements

### Environment Variables
Ensure these are set in your `.env` file:
```env
INFOBIP_API_KEY=your_infobip_api_key
INFOBIP_BASE_URL=https://api.infobip.com
WABA_SENDER=your_whatsapp_sender_number
DEFAULT_LANGUAGE=ar
```

### Template Approval
**IMPORTANT**: All English templates must be approved by WhatsApp before use:
- `overspeed_alert_en`
- `ignition_on_alert_en` 
- `ignition_off_alert_en`
- `geofence_exit_en`
- `sos_alert_en`
- `fuel_alert_en`

## Usage Examples

### 1. Testing via API
```bash
# List available test endpoints
curl http://localhost:8000/api/test/whatsapp

# Test overspeed alert
curl -X POST http://localhost:8000/api/test/whatsapp/overspeed \
  -H "Content-Type: application/json" \
  -d '{"phone": "+966560918986"}'

# Test ignition on alert
curl -X POST http://localhost:8000/api/test/whatsapp/ignition_on \
  -H "Content-Type: application/json" \
  -d '{"phone": "+966560918986", "vehicle_id": "TRUCK-001"}'
```

### 2. Testing via Artisan Command
```bash
# Test with queue (recommended for production)
php artisan whatsapp:test overspeed +966560918986

# Test direct send (for immediate testing)
php artisan whatsapp:test ignition_on +966560918986 --direct

# Test with custom vehicle ID
php artisan whatsapp:test ignition_off +966560918986 --vehicle_id=TRUCK-001
```

### 3. Real FMS Webhook Payload
```json
{
  "alert_type": "overspeed",
  "message": "Vehicle exceeded speed limit",
  "vehicle_id": "TRUCK-001",
  "phone_number": "+966560918986",
  "speed": "120",
  "address": "King Fahd Road, Riyadh",
  "location": {
    "lat": "24.7136",
    "lng": "46.6753"
  },
  "occurred_at": "2025-01-17T08:30:00Z",
  "customer_id": "CUST-001"
}
```

## Logging and Monitoring

### Log Locations
- **Main Log**: `storage/logs/fms.log`
- **Laravel Log**: `storage/logs/laravel.log`

### Log Monitoring Commands
```bash
# Monitor FMS logs in real-time
tail -f storage/logs/fms.log

# Monitor all logs
tail -f storage/logs/laravel.log

# Search for WhatsApp related logs
findstr "WhatsApp" storage/logs/fms.log
```

### Key Log Events
- Incoming webhook payloads
- Template selection and mapping
- Queue job processing
- Infobip API calls and responses
- Success/failure notifications

## Troubleshooting

### Common Issues

1. **Template Not Found**
   - Check `config/alerts.php` for correct mapping
   - Verify alert_type normalization

2. **Missing Phone Number**
   - Ensure payload contains phone number in supported locations
   - Check extraction logic in `FmsAlertController`

3. **Infobip API Errors**
   - Verify API credentials in `.env`
   - Check template approval status
   - Review API response in logs

4. **Queue Not Processing**
   - Start queue worker: `php artisan queue:work`
   - Check queue configuration in `config/queue.php`

### Debug Steps
1. Check logs: `tail -f storage/logs/fms.log`
2. Test with direct send: `--direct` flag
3. Verify template mapping: `GET /api/test/whatsapp`
4. Test API connectivity: Use test routes

## Database Schema

### Alerts Table
- Stores incoming FMS alerts
- Includes idempotency key for duplicate prevention

### Messages Table
- Tracks WhatsApp message sending status
- Links to alerts via `alert_id`
- Stores provider message IDs and error details

## Security Considerations

- IP allowlist support via `ALLOWED_SOURCE_IPS`
- HMAC signature verification via `X-Tawasul-Signature`
- Secure API key handling
- Input validation and sanitization

## Performance Notes

- Queue-based processing for scalability
- Idempotency key prevents duplicate processing
- Configurable retry mechanism with backoff
- Efficient template mapping with fallback logic

## Next Steps

1. **Template Approval**: Submit English templates to WhatsApp for approval
2. **Production Testing**: Test with real FMS payloads
3. **Monitoring Setup**: Implement alerting for failed messages
4. **Documentation**: Update API documentation with new endpoints
5. **Load Testing**: Test with high volume of alerts

## Support

For issues or questions:
1. Check logs first: `storage/logs/fms.log`
2. Use test commands for debugging
3. Review this documentation
4. Check Infobip API documentation for template requirements
