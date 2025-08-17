<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappAlert;
use App\Models\Alert;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FmsAlertController extends Controller
{
    public function handle(Request $req)
    {
        // Log incoming FMS webhook payload
        Log::channel('fms')->info('Incoming FMS Webhook', [
            'headers' => request()->headers->all(),
            'body'    => request()->all(),
        ]);

        // Optional IP allow-list (skip for local)
        if ($ips = env('ALLOWED_SOURCE_IPS')) {
            $allowed = array_map('trim', explode(',', $ips));
            if (!in_array($req->ip(), $allowed)) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        // Optional HMAC check (skip for local)
        if ($sig = $req->header('X-Tawasul-Signature')) {
            $calc = 'sha256=' . hash_hmac('sha256', $req->getContent(), env('WEBHOOK_SIGNING_SECRET'));
            if (!hash_equals($calc, $sig)) {
                return response()->json(['error' => 'bad signature'], 401);
            }
        }

        $payload = $req->all();
        
        // Extract alert_type, message, and phone_number from payload
        $type = $payload['alert_type'] ?? $payload['type'] ?? 'Unknown';
        $message = $payload['message'] ?? $payload['description'] ?? '';
        $vehicleId = $payload['vehicle_id'] ?? data_get($payload, 'body.vehicle.id') ?? 'NA';
        $customerId = $payload['customer_id'] ?? data_get($payload, 'body.customer.id') ?? null;
        $occurredAt = $payload['occurred_at'] ?? $payload['timestamp'] ?? now()->toISOString();
        
        // Extract phone number from multiple possible locations
        $msisdn = $payload['phone_number'] 
                ?? data_get($payload, 'body.user.phone_number')
                ?? $payload['phone'] 
                ?? data_get($payload, 'body.customer.phone')
                ?? data_get($payload, 'user.phone_number')
                ?? null;

        // Normalize alert type for better matching
        $normalizedType = strtolower(str_replace([' ', '-'], '_', $type));

        $map = config('alerts');
        $template = null;
        
        // Try original type first, then normalized type
        if (isset($map[$type])) {
            $template = $map[$type]['template'];
        } elseif (isset($map[$normalizedType])) {
            $template = $map[$normalizedType]['template'];
        } else {
            Log::warning('No template mapped for alert type', [
                'original_type' => $type,
                'normalized_type' => $normalizedType
            ]);
            return response()->json(['skipped' => true], 200);
        }

        $idempotency = hash('sha256', "{$vehicleId}|{$type}|{$occurredAt}");

        if (!$msisdn) {
            Log::error('Missing phone (to_msisdn) in payload');
            return response()->json(['error' => 'missing phone'], 422);
        }

        // Database operations with proper error handling
        try {
            $alert = Alert::firstOrCreate(
                ['idempotency_key' => $idempotency],
                [
                    'event_id'    => $payload['event_id'] ?? null,
                    'vehicle_id'  => $vehicleId,
                    'customer_id' => $customerId,
                    'alert_type'  => $type,
                    'occurred_at' => Str::of($occurredAt)->substr(0, 19),
                    'payload'     => $payload,
                ]
            );
            
            if (!$alert->wasRecentlyCreated) {
                return response()->json(['duplicate' => true], 200);
            }

            // Determine language based on template (English for new templates)
            $language = str_ends_with($template, '_en') ? 'en' : env('DEFAULT_LANGUAGE', 'ar');

            $messageRecord = Message::create([
                'alert_id'      => $alert->id,
                'to_msisdn'     => $msisdn,
                'template_code' => $template,
                'language'      => $language,
                'status'        => 'pending',
            ]);

            // Enhanced placeholders with extracted message and additional data
            $placeholders = [
                'vehicle_id' => $vehicleId,
                'alert_type' => $type,
                'message' => $message,
                'occurred_at' => $occurredAt,
                'location_lat' => data_get($payload, 'location.lat', ''),
                'location_lng' => data_get($payload, 'location.lng', ''),
                'speed' => data_get($payload, 'speed', ''),
                'address' => data_get($payload, 'address', ''),
            ];

            SendWhatsappAlert::dispatch($messageRecord, $placeholders, $type)->onQueue('default');

            return response()->json(['queued' => true, 'alert_id' => $alert->id], 202);
            
        } catch (\Exception $e) {
            Log::error('Database operation failed', [
                'error' => $e->getMessage(),
                'vehicle_id' => $vehicleId,
                'alert_type' => $type,
                'template' => $template,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Database operation failed',
                'message' => 'Unable to process webhook due to database error'
            ], 500);
        }
    }

    public function healthz() { return response()->json(['ok' => true]); }

    /**
     * Test WhatsApp alert sending with dummy payloads
     */
    public function testAlert(Request $request, string $alertType)
    {
        $phone = $request->input('phone', '+966500000000'); // Default test phone
        
        // Generate dummy payload based on alert type
        $dummyPayloads = [
            'overspeed' => [
                'alert_type' => 'overspeed',
                'message' => 'Vehicle exceeded speed limit',
                'vehicle_id' => 'TEST-001',
                'phone_number' => $phone,
                'speed' => '120',
                'address' => 'King Fahd Road, Riyadh',
                'location' => ['lat' => '24.7136', 'lng' => '46.6753'],
                'occurred_at' => now()->toISOString(),
                'customer_id' => 'CUST-001'
            ],
            'ignition_on' => [
                'alert_type' => 'ignition_on',
                'message' => 'Vehicle ignition turned on',
                'vehicle_id' => 'TEST-002',
                'phone_number' => $phone,
                'address' => 'Olaya Street, Riyadh',
                'location' => ['lat' => '24.6877', 'lng' => '46.7219'],
                'occurred_at' => now()->toISOString(),
                'customer_id' => 'CUST-002'
            ],
            'ignition_off' => [
                'alert_type' => 'ignition_off',
                'message' => 'Vehicle ignition turned off',
                'vehicle_id' => 'TEST-003',
                'phone_number' => $phone,
                'address' => 'Prince Mohammed Bin Abdulaziz Road, Riyadh',
                'location' => ['lat' => '24.7744', 'lng' => '46.7383'],
                'occurred_at' => now()->toISOString(),
                'customer_id' => 'CUST-003'
            ]
        ];

        if (!isset($dummyPayloads[$alertType])) {
            return response()->json([
                'error' => 'Invalid alert type',
                'available_types' => array_keys($dummyPayloads)
            ], 400);
        }

        // Override with any provided request data
        $payload = array_merge($dummyPayloads[$alertType], $request->all());
        
        Log::channel('fms')->info('Test WhatsApp Alert Triggered', [
            'alert_type' => $alertType,
            'phone' => $phone,
            'payload' => $payload
        ]);

        // Create a new request with the dummy payload and process it
        $testRequest = new Request($payload);
        return $this->handle($testRequest);
    }

    /**
     * List available test alert types and their templates
     */
    public function listTestAlerts()
    {
        $alertConfig = config('alerts');
        $englishTemplates = [];
        
        foreach ($alertConfig as $alertType => $config) {
            if (str_ends_with($config['template'], '_en')) {
                $englishTemplates[$alertType] = $config;
            }
        }

        return response()->json([
            'message' => 'Available English WhatsApp alert templates for testing',
            'templates' => $englishTemplates,
            'test_endpoints' => [
                'overspeed' => '/api/test/whatsapp/overspeed',
                'ignition_on' => '/api/test/whatsapp/ignition_on',
                'ignition_off' => '/api/test/whatsapp/ignition_off'
            ],
            'usage' => 'POST to /api/test/whatsapp/{alertType} with optional phone parameter'
        ]);
    }
}
