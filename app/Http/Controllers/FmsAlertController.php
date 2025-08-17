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

        // TEMPORARY: Simple test response to confirm endpoint works
        return response()->json(['ok' => true, 'seen' => $req->all()], 202);

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

        $payload    = $req->all();
        $type       = $payload['type']         ?? $payload['alert_type'] ?? 'Unknown';
        $vehicleId  = $payload['vehicle_id']   ?? 'NA';
        $customerId = $payload['customer_id']  ?? null;
        $occurredAt = $payload['occurred_at']  ?? $payload['timestamp'] ?? now()->toISOString();
        $msisdn     = $payload['phone']        ?? null;

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

            $message = Message::create([
                'alert_id'      => $alert->id,
                'to_msisdn'     => $msisdn,
                'template_code' => $template,
                'language'      => env('DEFAULT_LANGUAGE', 'ar'),
                'status'        => 'pending',
            ]);

            $placeholders = [
                $vehicleId,
                $type,
                $occurredAt,
                data_get($payload, 'location.lat', ''),
                data_get($payload, 'location.lng', ''),
            ];

            SendWhatsappAlert::dispatch($message, $placeholders)->onQueue('default');

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
}
