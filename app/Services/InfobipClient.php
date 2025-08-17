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
        $language = $language ?? env('DEFAULT_LANGUAGE', 'ar');
        $apiKey   = env('INFOBIP_API_KEY');
        $base     = rtrim(env('INFOBIP_BASE_URL'), '/');

        // Convert associative array placeholders to indexed array for Infobip
        $templatePlaceholders = is_array($placeholders) && !empty($placeholders) 
            ? (array_keys($placeholders) !== range(0, count($placeholders) - 1) 
                ? array_values($placeholders) 
                : $placeholders)
            : [];

        $payload = [
            'from'      => env('WABA_SENDER'),
            'to'        => $to,
            'messageId' => uniqid('msg_', true),
            'content'   => [
                'templateName' => $template,
                'templateData' => [
                    'body' => ['placeholders' => $templatePlaceholders]
                ],
                'language' => $language
            ]
        ];

        Log::channel('fms')->debug('Sending WhatsApp template message', [
            'to' => $to,
            'template' => $template,
            'language' => $language,
            'placeholders' => $templatePlaceholders,
            'payload' => $payload
        ]);

        return Http::withHeaders([
                'Authorization' => "App {$apiKey}",
                'Content-Type'  => 'application/json'
            ])
            ->timeout(30)
            ->connectTimeout(5)
            ->post("{$base}/whatsapp/1/message/template", $payload);
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
        $language = $params['language'] ?? 'en';
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
}
