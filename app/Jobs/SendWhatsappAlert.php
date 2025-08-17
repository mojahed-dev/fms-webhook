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
        public string $alertType = ''
    ) {}

    public function handle(InfobipClient $infobip): void
    {
        Log::channel('fms')->info('Processing WhatsApp alert', [
            'message_id' => $this->message->id,
            'template' => $this->message->template_code,
            'phone' => $this->message->to_msisdn,
            'alert_type' => $this->alertType,
            'language' => $this->message->language
        ]);

        try {
            $res = $infobip->sendTemplateMessage(
                $this->message->to_msisdn,
                $this->message->template_code,
                $this->placeholders,
                $this->message->language
            );

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
                    'alert_type' => $this->alertType
                ]);
            } else {
                $this->message->status = 'failed';
                $this->message->last_error = $res->body();
                
                Log::channel('fms')->error('Infobip send failed', [
                    'message_id' => $this->message->id, 
                    'template' => $this->message->template_code,
                    'alert_type' => $this->alertType,
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->release($this->backoff);
        }
        
        $this->message->save();
    }
}
