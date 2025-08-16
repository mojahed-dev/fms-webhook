<?php
/**
 * Simple test script to verify Infobip WhatsApp API integration
 * Run with: php test-infobip.php
 */

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Services\InfobipClient;

echo "🧪 Testing Infobip WhatsApp Integration\n";
echo "=====================================\n\n";

// Check environment variables
$baseUrl = $_ENV['INFOBIP_BASE_URL'] ?? null;
$apiKey = $_ENV['INFOBIP_API_KEY'] ?? null;
$sender = $_ENV['WABA_SENDER'] ?? null;

echo "📋 Configuration Check:\n";
echo "Base URL: " . ($baseUrl ?: '❌ Not set') . "\n";
echo "API Key: " . ($apiKey ? '✅ Set (' . substr($apiKey, 0, 8) . '...)' : '❌ Not set') . "\n";
echo "Sender: " . ($sender ?: '❌ Not set') . "\n\n";

if (!$baseUrl || !$apiKey || !$sender) {
    echo "❌ Missing required configuration. Please update your .env file:\n";
    echo "INFOBIP_BASE_URL=https://your-subdomain.api.infobip.com\n";
    echo "INFOBIP_API_KEY=your-api-key-here\n";
    echo "WABA_SENDER=+9665xxxxxxx\n\n";
    exit(1);
}

// Test phone number (replace with actual test number)
$testPhone = '+966500000000'; // Replace with your test number
echo "📱 Test Configuration:\n";
echo "Test Phone: $testPhone\n";
echo "Template: overspeed_alert_ar\n";
echo "Language: ar\n\n";

echo "⚠️  WARNING: This will attempt to send a real WhatsApp message!\n";
echo "Make sure the test phone number is correct and you have permission to send to it.\n\n";

echo "Press Enter to continue or Ctrl+C to cancel...";
fgets(STDIN);

try {
    $client = new InfobipClient();
    
    echo "🚀 Sending test message...\n";
    
    $placeholders = [
        'TEST-VEHICLE-123',  // Vehicle ID
        'Overspeed Test',    // Alert Type
        '2025-08-16T10:00:00Z', // Occurred At
        '24.7136',           // Latitude
        '46.6753'            // Longitude
    ];
    
    $response = $client->sendTemplate(
        $testPhone,
        'overspeed_alert_ar',
        $placeholders,
        'ar'
    );
    
    echo "📤 Response Status: " . $response->status() . "\n";
    
    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Message sent successfully!\n";
        echo "Message ID: " . ($data['messages'][0]['messageId'] ?? 'N/A') . "\n";
        echo "Status: " . ($data['messages'][0]['status']['name'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Failed to send message\n";
        echo "Error: " . $response->body() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
}

echo "\n🏁 Test completed.\n";
