# WhatsApp Plain Text Fallback Implementation

## Overview
This implementation adds plain text message fallback functionality to the existing WhatsApp template system. When an alert type has no mapped template, the system will automatically send a plain text message instead of skipping the alert.

## Changes Made

### 1. InfobipClient Service (`app/Services/InfobipClient.php`)
- **Added**: `sendTextMessage($to, $message)` method
- **Purpose**: Sends plain text WhatsApp messages using Infobip API `/whatsapp/1/message/text` endpoint
- **Features**: 
  - Proper logging for debugging
  - Uses same authentication and configuration as template messages
  - Generates unique message IDs with `txt_` prefix

### 2. SendWhatsappAlert Job (`app/Jobs/SendWhatsappAlert.php`)
- **Added**: `$useTextFallback` parameter to constructor
- **Added**: `buildPlainTextMessage()` private method
- **Enhanced**: `handle()` method to support both template and plain text sending
- **Features**:
  - Builds formatted plain text messages from placeholders
  - Includes vehicle ID, alert type, time, speed (if available), and location
  - Enhanced logging to distinguish between template and plain text messages
  - Follows the requested format: "Vehicle TEST-001 triggered Overspeed at 12:34PM, speed 120km/h (limit 100)."

### 3. FmsAlertController (`app/Http/Controllers/FmsAlertController.php`)
- **Modified**: Template mapping logic to use fallback instead of skipping
- **Added**: `$useTextFallback` flag when no template is found
- **Enhanced**: Logging to indicate when fallback is used
- **Added**: Test cases for unmapped alert types in `testAlert()` method

### 4. TestWhatsappAlert Command (`app/Console/Commands/TestWhatsappAlert.php`)
- **Enhanced**: Support for testing unmapped alert types
- **Added**: Plain text fallback detection and messaging
- **Enhanced**: Better feedback showing message type (Template vs Plain Text)

## Plain Text Message Format

The plain text messages follow this format:
```
Vehicle {VEHICLE_ID} triggered {ALERT_TYPE} at {TIME}[, speed {SPEED}km/h[ (limit {LIMIT})]][ at {ADDRESS}].
```

### Examples:
- **Overspeed**: "Vehicle TEST-001 triggered Overspeed at 12:34PM, speed 120km/h (limit 100) at King Fahd Road, Riyadh."
- **Ignition**: "Vehicle TEST-002 triggered ignition_on at 1:45PM at Olaya Street, Riyadh."
- **Generic**: "Vehicle TEST-003 triggered maintenance_due at 3:20PM."

## Configuration

No additional configuration is required. The system automatically:
1. Tries to find a template mapping in `config/alerts.php`
2. If found, uses the existing template system
3. If not found, uses plain text fallback with enhanced logging

## Testing

### Command Line Testing
```bash
# Test with mapped alert type (uses template)
php artisan whatsapp:test overspeed +966560918986

# Test with unmapped alert type (uses plain text fallback)
php artisan whatsapp:test test_unmapped_alert +966XXXXXXXXX

# Test with custom vehicle ID
php artisan whatsapp:test maintenance_due +966XXXXXXXXX --vehicle_id=TRUCK-001
```

### API Testing
```bash
# Test via webhook endpoint
POST /api/test/whatsapp/test_unmapped
POST /api/test/whatsapp/maintenance_due
```

### Verification Script
```bash
php test-plain-text-fallback.php
```

## Logging

The system logs distinguish between message types:
- **Template messages**: `message_type: "template"`
- **Plain text messages**: `message_type: "plain text"`

Example log entries:
```
[INFO] Processing WhatsApp alert {"message_type":"plain text","alert_type":"test_unmapped_alert"}
[INFO] No template mapped for alert type, using plain text fallback {"original_type":"maintenance_due"}
[INFO] WhatsApp alert sent successfully {"message_type":"plain text"}
```

## Backward Compatibility

✅ **Fully backward compatible**
- Existing template functionality unchanged
- All existing alert types continue to use templates
- No breaking changes to existing code
- Existing webhooks and API calls work unchanged

## Benefits

1. **No Lost Alerts**: Previously skipped alerts are now sent as plain text
2. **Immediate Testing**: Can test live alerts without waiting for template approval
3. **Graceful Degradation**: System continues working even with unmapped alert types
4. **Clear Logging**: Easy to identify which messages used fallback
5. **Future-Proof**: Easy to switch to templates once approved

## Future Enhancements

- Add configuration option to enable/disable plain text fallback
- Add template for speed limits based on vehicle or location data
- Add support for multiple languages in plain text messages
- Add rich formatting options for plain text messages

## Files Modified

1. `app/Services/InfobipClient.php` - Added sendTextMessage method
2. `app/Jobs/SendWhatsappAlert.php` - Added fallback support and message building
3. `app/Http/Controllers/FmsAlertController.php` - Added fallback logic
4. `app/Console/Commands/TestWhatsappAlert.php` - Enhanced testing support

## Files Added

1. `test-plain-text-fallback.php` - Verification script
2. `PLAIN_TEXT_FALLBACK_IMPLEMENTATION.md` - This documentation
