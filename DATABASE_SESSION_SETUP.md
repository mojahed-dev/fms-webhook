# Optional: Database Session Setup

If you want to use database-backed sessions instead of file sessions in the future, follow these steps:

## 1. Generate Session Table Migration

```bash
php artisan session:table
```

This creates a migration file for the `sessions` table.

## 2. Run the Migration

```bash
php artisan migrate
```

This creates the `sessions` table in your MySQL database.

## 3. Update Environment Configuration

Update your `.env` file to use database sessions:

```env
SESSION_DRIVER=database
SESSION_CONNECTION=mysql
```

## 4. Clear Configuration Cache

```bash
php artisan config:clear
```

## 5. Test the Configuration

Restart your Laravel server and test the endpoints:

```bash
php artisan serve
```

Then test with:
```bash
# Health check
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/healthz" -Method GET

# Webhook test
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/fms/alerts" -Method POST -Headers @{"Content-Type"="application/json"} -Body '{"vehicle_id":"TEST-001","type":"Overspeed","phone":"+966500000000","occurred_at":"2025-08-16T12:30:00Z"}'
```

## Benefits of Database Sessions

- **Scalability**: Better for load-balanced environments
- **Persistence**: Sessions survive server restarts
- **Monitoring**: Can query session data directly from database
- **Cleanup**: Automatic session cleanup via Laravel's session sweeping

## Reverting to File Sessions

To go back to file sessions:

1. Update `.env`:
```env
SESSION_DRIVER=file
```

2. Clear config cache:
```bash
php artisan config:clear
```

## Session Table Schema

The generated sessions table will have:
- `id` (string, primary key)
- `user_id` (nullable, for authenticated users)
- `ip_address` (string, nullable)
- `user_agent` (text, nullable)
- `payload` (longtext, session data)
- `last_activity` (integer, timestamp)
