<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class InfobipClient
{
    public function sendTemplate(string $to, string $template, array $placeholders, string $language = null)
    {
        $language = $language ?? env('DEFAULT_LANGUAGE', 'ar');
        $apiKey   = env('INFOBIP_API_KEY');
        $base     = rtrim(env('INFOBIP_BASE_URL'), '/');

        return Http::withHeaders([
                'Authorization' => "App {$apiKey}",
                'Content-Type'  => 'application/json'
            ])
            ->timeout(2)
            ->connectTimeout(0.2)
            ->post("{$base}/whatsapp/1/message/template", [
                'from'      => env('WABA_SENDER'),
                'to'        => $to,
                'messageId' => uniqid('msg_', true),
                'content'   => [
                    'templateName' => $template,
                    'templateData' => [
                        'body' => ['placeholders' => $placeholders]
                    ],
                    'language' => $language
                ]
            ]);
    }
}
