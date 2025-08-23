<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsappAlert;
use App\Models\Alert;
use App\Models\Message;
use App\Services\InfobipClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestWhatsappAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:test 
                            {alert_type : The alert type (overspeed, ignition_on, ignition_off)}
                            {phone : Phone number in international format}
                            {--vehicle_id=TEST-CMD : Vehicle ID for testing}
                            {--direct : Send directly without queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WhatsApp alert sending with dummy data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alertType = $this->argument('alert_type');
        $phone = $this->argument('phone');
        $vehicleId = $this->option('vehicle_id');
        $direct = $this->option('direct');

        // Check if alert type has template mapping
        $alertConfig = config('alerts');
        $useTextFallback = false;
        $template = null;
        $language = 'en';

        if (isset($alertConfig[$alertType])) {
            $template = $alertConfig[$alertType]['template'];
            $language = str_ends_with($template, '_en') ? 'en' : 'ar';
        } else {
            // Use plain text fallback for unmapped alert types
            $useTextFallback = true;
            $template = 'plain_text_fallback';
            $this->warn("No template found for '{$alertType}' - using plain text fallback");
        }

        $this->info("Testing WhatsApp alert:");
        $this->info("- Alert Type: {$alertType}");
        $this->info("- Template: {$template}");
        $this->info("- Phone: {$phone}");
        $this->info("- Vehicle ID: {$vehicleId}");
        $this->info("- Language: {$language}");
        $this->info("- Direct Send: " . ($direct ? 'Yes' : 'No (via queue)'));

        // Generate test data
        $testData = $this->generateTestData($alertType, $vehicleId, $phone);

        // Default placeholders
        $placeholders = [
            'vehicle_id' => $vehicleId,
            'alert_type' => $alertType,
            'message' => $testData['message'],
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'location_lat' => $testData['location']['lat'] ?? '',
            'location_lng' => $testData['location']['lng'] ?? '',
            'speed' => $testData['speed'] ?? '',
            'address' => $testData['address'] ?? '',
        ];

        // Special case for your test template
        if ($template === 'infobip_test_hsm_2') {
            $placeholders = [
                $vehicleId ?? 'TestUser',    // for {{1}}
                '1234567890',                // for {{2}}
            ];
            $language = 'en'; // Force English for this template
        }

        try {
            // Create alert record
            $alert = Alert::create([
                'event_id' => 'TEST-' . uniqid(),
                'vehicle_id' => $vehicleId,
                'customer_id' => 'TEST-CUSTOMER',
                'alert_type' => $alertType,
                'occurred_at' => now(),
                'payload' => $testData,
                'idempotency_key' => 'test-' . uniqid()
            ]);

            // Create message record
            $message = Message::create([
                'alert_id' => $alert->id,
                'to_msisdn' => $phone,
                'template_code' => $template,
                'language' => $language,
                'status' => 'pending',
            ]);

            if ($direct) {
                // Send directly
                $this->info("Sending directly via InfobipClient...");
                $infobip = app(InfobipClient::class);
                
                if ($useTextFallback) {
                    // Send plain text message
                    $textMessage = $this->buildPlainTextMessage($placeholders, $alertType);
                    $response = $infobip->sendTextMessage($phone, $textMessage);
                } else {
                    // Send template message
                    $response = $infobip->sendTemplateMessage($phone, $template, array_values($placeholders), $language);
                }

                if ($response->successful()) {
                    $json = $response->json();
                    $message->status = 'sent';
                    
                    if ($useTextFallback) {
                        // Text message response doesn't have messages array
                        $message->provider_msg_id = $json['messageId'] ?? null;
                    } else {
                        // Template message response has messages array
                        $message->provider_msg_id = $json['messages'][0]['messageId'] ?? null;
                    }
                    
                    $message->save();

                    $this->info("✅ Message sent successfully!");
                    $this->info("Provider Message ID: " . $message->provider_msg_id);
                } else {
                    $message->status = 'failed';
                    $message->last_error = $response->body();
                    $message->save();

                    $this->error("❌ Failed to send message:");
                    $this->error("Status: " . $response->status());
                    $this->error("Response: " . $response->body());
                }
            } else {
                // Send via queue
                $this->info("Dispatching to queue...");
                SendWhatsappAlert::dispatch($message, $placeholders, $alertType, $useTextFallback)->onQueue('default');
                $this->info("✅ Message queued successfully!");
                $this->info("Message ID: {$message->id}");
                $this->info("Message Type: " . ($useTextFallback ? 'Plain Text' : 'Template'));
                $this->info("Check logs with: tail -f storage/logs/fms.log");
            }

            $this->newLine();
            $this->info("Alert ID: {$alert->id}");
            $this->info("Message ID: {$message->id}");
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::channel('fms')->error('Test WhatsApp command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Build plain text message from placeholders
     */
    private function buildPlainTextMessage(array $placeholders, string $alertType): string
    {
        $vehicleId = $placeholders['vehicle_id'] ?? 'Unknown';
        $alertType = $placeholders['alert_type'] ?? $alertType;
        $occurredAt = $placeholders['occurred_at'] ?? now()->format('H:i A');
        
        // Format time to be more readable
        if ($occurredAt && $occurredAt !== now()->format('H:i A')) {
            try {
                $occurredAt = date('g:i A', strtotime($occurredAt));
            } catch (\Exception $e) {
                $occurredAt = 'Unknown time';
            }
        }
        
        // Start building the message
        $message = "Vehicle {$vehicleId} triggered {$alertType} at {$occurredAt}";
        
        // Add speed information if available (for overspeed alerts)
        if (!empty($placeholders['speed']) && is_numeric($placeholders['speed'])) {
            $speed = $placeholders['speed'];
            $message .= ", speed {$speed}km/h";
            
            // Add speed limit if this is an overspeed alert
            if (stripos($alertType, 'overspeed') !== false || stripos($alertType, 'speed') !== false) {
                // You can customize this logic based on your speed limit data
                $speedLimit = 100; // Default speed limit, you can make this dynamic
                $message .= " (limit {$speedLimit})";
            }
        }
        
        // Add location/address if available
        if (!empty($placeholders['address'])) {
            $message .= " at " . $placeholders['address'];
        }
        
        $message .= ".";
        
        return $message;
    }

    private function generateTestData(string $alertType, string $vehicleId, string $phone): array
    {
        $baseData = [
            'alert_type' => $alertType,
            'vehicle_id' => $vehicleId,
            'phone_number' => $phone,
            'occurred_at' => now()->toISOString(),
            'customer_id' => 'TEST-CUSTOMER',
        ];

        switch ($alertType) {
            case 'overspeed':
                return array_merge($baseData, [
                    'message' => 'Vehicle exceeded speed limit during test',
                    'speed' => '125',
                    'address' => 'King Fahd Road, Riyadh (Test Location)',
                    'location' => ['lat' => '24.7136', 'lng' => '46.6753'],
                ]);

            case 'ignition_on':
                return array_merge($baseData, [
                    'message' => 'Vehicle ignition turned on during test',
                    'address' => 'Olaya Street, Riyadh (Test Location)',
                    'location' => ['lat' => '24.6877', 'lng' => '46.7219'],
                ]);

            case 'ignition_off':
                return array_merge($baseData, [
                    'message' => 'Vehicle ignition turned off during test',
                    'address' => 'Prince Mohammed Bin Abdulaziz Road, Riyadh (Test Location)',
                    'location' => ['lat' => '24.7744', 'lng' => '46.7383'],
                ]);

            default:
                return array_merge($baseData, [
                    'message' => "Test alert for {$alertType}",
                    'address' => 'Test Location, Riyadh',
                    'location' => ['lat' => '24.7136', 'lng' => '46.6753'],
                ]);
        }
    }
}
