-- One-time SQL backfill script for MariaDB
-- Populates NULL event_id, vehicle_id, customer_id, occurred_at from JSON payload
-- Run this script before schema changes

-- Update NULL event_id from payload
UPDATE alerts 
SET event_id = JSON_EXTRACT(payload, '$.event_id')
WHERE event_id IS NULL 
  AND JSON_EXTRACT(payload, '$.event_id') IS NOT NULL
  AND JSON_EXTRACT(payload, '$.event_id') != 'null';

-- Alternative event_id extraction from alert_id
UPDATE alerts 
SET event_id = JSON_EXTRACT(payload, '$.alert_id')
WHERE event_id IS NULL 
  AND JSON_EXTRACT(payload, '$.alert_id') IS NOT NULL
  AND JSON_EXTRACT(payload, '$.alert_id') != 'null';

-- Update NULL vehicle_id from payload (multiple possible paths)
UPDATE alerts 
SET vehicle_id = COALESCE(
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.vehicle_id')), 'null'),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body.vehicle.id')), 'null'),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.device.device_id')), 'null'),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.device.name')), 'null'),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.device.plate_number')), 'null')
)
WHERE (vehicle_id IS NULL OR vehicle_id = 'NA')
  AND payload IS NOT NULL;

-- Update NULL customer_id from payload
UPDATE alerts 
SET customer_id = COALESCE(
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.customer_id')), 'null'),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body.customer.id')), 'null')
)
WHERE customer_id IS NULL 
  AND payload IS NOT NULL;

-- Update NULL occurred_at from payload (multiple possible timestamp fields)
UPDATE alerts 
SET occurred_at = STR_TO_DATE(
    SUBSTRING(
        COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.occurred_at')), 'null'),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.timestamp')), 'null'),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.time')), 'null')
        ), 1, 19
    ), '%Y-%m-%dT%H:%i:%s'
)
WHERE occurred_at IS NULL 
  AND payload IS NOT NULL
  AND (
    JSON_EXTRACT(payload, '$.occurred_at') IS NOT NULL OR
    JSON_EXTRACT(payload, '$.timestamp') IS NOT NULL OR
    JSON_EXTRACT(payload, '$.time') IS NOT NULL
  );

-- Show summary of backfill results
SELECT 
    'Backfill Summary' as operation,
    COUNT(*) as total_alerts,
    SUM(CASE WHEN event_id IS NOT NULL THEN 1 ELSE 0 END) as alerts_with_event_id,
    SUM(CASE WHEN vehicle_id IS NOT NULL AND vehicle_id != 'NA' THEN 1 ELSE 0 END) as alerts_with_vehicle_id,
    SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as alerts_with_customer_id,
    SUM(CASE WHEN occurred_at IS NOT NULL THEN 1 ELSE 0 END) as alerts_with_occurred_at
FROM alerts;

-- Show remaining NULL counts
SELECT 
    'Remaining NULLs' as status,
    SUM(CASE WHEN event_id IS NULL THEN 1 ELSE 0 END) as null_event_id,
    SUM(CASE WHEN vehicle_id IS NULL OR vehicle_id = 'NA' THEN 1 ELSE 0 END) as null_vehicle_id,
    SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END) as null_customer_id,
    SUM(CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END) as null_occurred_at
FROM alerts;
