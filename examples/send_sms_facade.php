<?php

// Example: Sending a simple SMS using the Facade.
// Place a variant of this in a controller, job, or service class.

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\RateLimitedException;

// Basic send
Calisero::sendSms([
    'to' => '+1234567890',
    'text' => 'Hello from Calisero + Laravel!',
    'from' => 'MyApp',
    'idempotencyKey' => 'greeting-' . bin2hex(random_bytes(4)),
]);

// Error handling pattern
try {
    Calisero::sendSms([
        'to' => '+1234567890',
        'text' => 'With error handling',
        'from' => 'MyApp',
        'idempotencyKey' => 'safe-' . uniqid('', true),
    ]);
} catch (ValidationException $e) {
    logger()->warning('SMS validation failed', [
        'errors' => $e->getValidationErrors(),
        'details' => $e->getErrorDetails() ?? null,
    ]);
} catch (RateLimitedException $e) {
    logger()->warning('Rate limited when sending SMS', [
        'retry_after' => $e->getCode(),
        'message' => $e->getMessage(),
    ]);
} catch (UnauthorizedException $e) {
    logger()->error('Unauthorized Calisero SMS request', [
        'message' => $e->getMessage(),
    ]);
} catch (\Throwable $e) {
    logger()->error('Unexpected SMS send failure', ['message' => $e->getMessage()]);
}
