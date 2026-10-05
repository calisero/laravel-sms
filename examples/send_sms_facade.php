<?php

/**
 * Example: Send SMS using Facade
 *
 * This is the simplest way to send an SMS message.
 */

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\UnauthorizedException;

// Basic send
Calisero::sendSms([
    'to' => '+40712345678',
    'text' => 'Hello from Calisero + Laravel!',
    'from' => 'MyApp', // Only if approved by Calisero
]);

// Error handling pattern
try {
    $response = Calisero::sendSms([
        'to' => '+40712345678',
        'text' => 'Hello from Laravel!',
        'from' => 'MyApp', // Only if approved by Calisero
    ]);

    echo "✓ SMS sent successfully!\n";
    echo "Message ID: {$response->getData()->getId()}\n";
    echo "Status: {$response->getData()->getStatus()}\n";
} catch (ValidationException $e) {
    echo "✗ Validation error: {$e->getMessage()}\n";
} catch (UnauthorizedException $e) {
    echo "✗ Authentication failed: Check your API key\n";
} catch (ApiException $e) {
    echo "✗ Failed to send SMS: {$e->getMessage()} (trace ID: {$e->getTraceId()})\n";
} catch (\Exception $e) {
    echo "✗ Failed to send SMS: {$e->getMessage()}\n";
}
