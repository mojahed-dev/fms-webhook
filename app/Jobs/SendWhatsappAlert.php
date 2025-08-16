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

    public function __construct(public Message $message, public array $placeholders) {}

    public function handle(InfobipClient $infobip): void
    {
        $res = $infobip->sendTemplate(
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
        } else {
            $this->message->status = 'failed';
            $this->message->last_error = $res->body();
            Log::error('Infobip send failed', ['message_id' => $this->message->id, 'body' => $res->body()]);
            $this->release($this->backoff);
        }
        $this->message->save();
    }
}
