<?php

/**
 * Example: Send SMS using Facade
 *
 * This is the simplest way to send an SMS message.
 */

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\UnauthorizedException;

// Basic send
Calisero::sendSms([
    'to' => '+1234567890',
    'text' => 'Hello from Calisero + Laravel!',
    'from' => 'MyApp',
    'idempotencyKey' => 'greeting-' . bin2hex(random_bytes(4)),
]);

// Error handling pattern
try {
    $response = Calisero::sendSms([
        'to' => '+40712345678',
        'text' => 'Hello from Laravel!',
        'from' => 'MyApp', // Only if approved by Calisero,
        'idempotencyKey' => 'greeting-' . bin2hex(random_bytes(4)),
    ]);

    echo "✓ SMS sent successfully!\n";
    echo "Message ID: {$response['message_id']}\n";
} catch (ValidationException $e) {
    echo "✗ Validation error: {$e->getMessage()}\n";
} catch (UnauthorizedException $e) {
    echo "✗ Authentication failed: Check your API key\n";
} catch (\Exception $e) {
    echo "✗ Failed to send SMS: {$e->getMessage()}\n";
}
