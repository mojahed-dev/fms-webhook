<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipClient
{
    public function sendTemplate(string $to, string $template, array $placeholders, string $language = null)
    {
        return $this->sendTemplateMessage($to, $template, $placeholders, $language);
    }

    public function sendTemplateMessage(string $to, string $template, array $placeholders, string $language = null)
    {
        $from = env('WABA_SENDER');
        $language = $language ?? env('DEFAULT_LANGUAGE', 'en');

        // Validate required fields
        if (is_null($from) || is_null($to) || empty($template)) {
            Log::error('InfobipClient validation failed', [
                'from' => $from,
                'to' => $to,
                'template' => $template,
                'error' => 'Required fields must not be null: from, to, and template.'
            ]);
            throw new \InvalidArgumentException('Required fields must not be null: from, to, and template.');
        }

        // Correct payload structure for Infobip WhatsApp Template API
        $payload = [
            'messages' => [
                [
                    'from' => $from,
                    'to' => $to,
                    'messageId' => uniqid('msg_', true),
                    'content' => [
                        'templateName' => $template,
                        'templateData' => [
                            'body' => [
                                'placeholders' => array_values($placeholders)
                            ]
                        ],
                        'language' => $language
                    ]
                ]
            ]
        ];

        // Debug log before sending
        Log::info('INFOBIP REQUEST DEBUG', [
            'url' => env('INFOBIP_BASE_URL') . '/whatsapp/1/message/template',
            'headers' => [
                'Authorization' => 'App ' . substr(env('INFOBIP_API_KEY'), 0, 10) . '...',
                'Content-Type' => 'application/json'
            ],
            'json_payload' => json_encode($payload, JSON_PRETTY_PRINT)
        ]);

        // Send request
        $response = Http::withHeaders([
            'Authorization' => 'App ' . env('INFOBIP_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('INFOBIP_BASE_URL') . '/whatsapp/1/message/template', $payload);

        return $response;
    }

    /**
     * Send WhatsApp template message with dynamic parameters
     * 
     * @param string $phone Phone number in international format
     * @param string $template Infobip template name
     * @param array $params Dynamic parameters for the template
     * @return \Illuminate\Http\Client\Response
     */
    public function sendTemplateWithParams(string $phone, string $template, array $params = [])
    {
        // $language = $params['language'] ?? 'en';
        $language = 'en';
        $placeholders = [];

        // Extract common parameters for WhatsApp templates
        if (isset($params['vehicle_id'])) {
            $placeholders[] = $params['vehicle_id'];
        }
        if (isset($params['alert_type'])) {
            $placeholders[] = $params['alert_type'];
        }
        if (isset($params['message'])) {
            $placeholders[] = $params['message'];
        }
        if (isset($params['occurred_at'])) {
            $placeholders[] = date('Y-m-d H:i:s', strtotime($params['occurred_at']));
        }
        if (isset($params['speed'])) {
            $placeholders[] = $params['speed'];
        }
        if (isset($params['address'])) {
            $placeholders[] = $params['address'];
        }

        return $this->sendTemplateMessage($phone, $template, $placeholders, $language);
    }

    /**
     * Send plain text WhatsApp message using Infobip API
     * 
     * @param string $to Phone number in international format
     * @param string $message Plain text message content
     * @return \Illuminate\Http\Client\Response
     */
    public function sendTextMessage(string $to, string $message)
    {
        $apiKey = env('INFOBIP_API_KEY');
        $base = rtrim(env('INFOBIP_BASE_URL'), '/');

        $payload = [
            'from' => env('WABA_SENDER'),
            'to' => $to,
            'messageId' => uniqid('txt_', true),
            'content' => [
                'text' => $message
            ]
        ];

        Log::channel('fms')->debug('Sending WhatsApp text message', [
            'to' => $to,
            'message' => $message,
            'payload' => $payload
        ]);

        return Http::withHeaders([
            'Authorization' => "App {$apiKey}",
            'Content-Type' => 'application/json'
        ])
            ->timeout(30)
            ->connectTimeout(5)
            ->post("{$base}/whatsapp/1/message/text", $payload);
    }
}
