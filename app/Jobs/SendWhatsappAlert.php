<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\InfobipClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsappAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 3;

    public function __construct(
        public Message $message, 
        public array $placeholders, 
        public string $alertType = '',
        public bool $useTextFallback = false
    ) {}

    public function handle(InfobipClient $infobip): void
    {
        $messageType = $this->useTextFallback ? 'plain text' : 'template';
        
        Log::channel('fms')->info('Processing WhatsApp alert', [
            'message_id' => $this->message->id,
            'template' => $this->message->template_code,
            'phone' => $this->message->to_msisdn,
            'alert_type' => $this->alertType,
            'language' => $this->message->language,
            'message_type' => $messageType
        ]);

        try {
            if ($this->useTextFallback) {
                // Send plain text message
                $textMessage = $this->buildPlainTextMessage();
                $res = $infobip->sendTextMessage($this->message->to_msisdn, $textMessage);
            } else {
                // Send template message (existing logic)
                $res = $infobip->sendTemplateMessage(
                    $this->message->to_msisdn,
                    $this->message->template_code,
                    $this->placeholders,
                    $this->message->language
                );
            }

            $this->message->attempts++;
            
            if ($res->successful()) {
                $json = $res->json();
                $this->message->status = 'sent';
                $this->message->provider_msg_id = $json['messages'][0]['messageId'] ?? null;
                $this->message->last_error = null;
                
                Log::channel('fms')->info('WhatsApp alert sent successfully', [
                    'message_id' => $this->message->id,
                    'provider_msg_id' => $this->message->provider_msg_id,
                    'template' => $this->message->template_code,
                    'alert_type' => $this->alertType,
                    'message_type' => $messageType
                ]);
            } else {
                $this->message->status = 'failed';
                $this->message->last_error = $res->body();
                
                Log::channel('fms')->error('Infobip send failed', [
                    'message_id' => $this->message->id, 
                    'template' => $this->message->template_code,
                    'alert_type' => $this->alertType,
                    'message_type' => $messageType,
                    'status_code' => $res->status(),
                    'response_body' => $res->body()
                ]);
                
                $this->release($this->backoff);
            }
        } catch (\Exception $e) {
            $this->message->status = 'failed';
            $this->message->last_error = $e->getMessage();
            $this->message->attempts++;
            
            Log::channel('fms')->error('Exception during WhatsApp send', [
                'message_id' => $this->message->id,
                'template' => $this->message->template_code,
                'alert_type' => $this->alertType,
                'message_type' => $messageType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->release($this->backoff);
        }
        
        $this->message->save();
    }

    /**
     * Build plain text message from placeholders
     */
    private function buildPlainTextMessage(): string
    {
        $vehicleId = $this->placeholders['vehicle_id'] ?? 'Unknown';
        $alertType = $this->placeholders['alert_type'] ?? $this->alertType;
        $occurredAt = $this->placeholders['occurred_at'] ?? now()->format('H:i A');
        
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
        if (!empty($this->placeholders['speed']) && is_numeric($this->placeholders['speed'])) {
            $speed = $this->placeholders['speed'];
            $message .= ", speed {$speed}km/h";
            
            // Add speed limit if this is an overspeed alert
            if (stripos($alertType, 'overspeed') !== false || stripos($alertType, 'speed') !== false) {
                // You can customize this logic based on your speed limit data
                $speedLimit = 100; // Default speed limit, you can make this dynamic
                $message .= " (limit {$speedLimit})";
            }
        }
        
        // Add location/address if available
        if (!empty($this->placeholders['address'])) {
            $message .= " at " . $this->placeholders['address'];
        }
        
        $message .= ".";
        
        return $message;
    }
}
