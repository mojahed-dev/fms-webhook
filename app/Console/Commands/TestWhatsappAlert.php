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

        // Validate alert type
        $alertConfig = config('alerts');
        if (!isset($alertConfig[$alertType])) {
            $this->error("Invalid alert type: {$alertType}");
            $this->info('Available types: ' . implode(', ', array_keys($alertConfig)));
            return 1;
        }

        $template = $alertConfig[$alertType]['template'];
        $language = str_ends_with($template, '_en') ? 'en' : 'ar';

        $this->info("Testing WhatsApp alert:");
        $this->info("- Alert Type: {$alertType}");
        $this->info("- Template: {$template}");
        $this->info("- Phone: {$phone}");
        $this->info("- Vehicle ID: {$vehicleId}");
        $this->info("- Language: {$language}");
        $this->info("- Direct Send: " . ($direct ? 'Yes' : 'No (via queue)'));

        // Generate test data
        $testData = $this->generateTestData($alertType, $vehicleId, $phone);
        
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

            if ($direct) {
                // Send directly
                $this->info("Sending directly via InfobipClient...");
                $infobip = app(InfobipClient::class);
                $response = $infobip->sendTemplateMessage($phone, $template, array_values($placeholders), $language);
                
                if ($response->successful()) {
                    $json = $response->json();
                    $message->status = 'sent';
                    $message->provider_msg_id = $json['messages'][0]['messageId'] ?? null;
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
                SendWhatsappAlert::dispatch($message, $placeholders, $alertType)->onQueue('default');
                $this->info("✅ Message queued successfully!");
                $this->info("Message ID: {$message->id}");
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
