<?php

/**
 * Example: Send verification code with Custom Template
 *
 * This example shows how to send a verification code using a custom template.
 * The template must contain {code} placeholder where the verification code will be inserted.
 * 
 * Note: Verification codes are 6 characters (alphanumeric) and case-insensitive.
 * The {code} placeholder will be replaced with codes like: SBMH0f, abc123, XYZ789
 */

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ValidationException;

try {
    $response = Calisero::sendVerification([
        'to' => '+40712345678',
        'template' => 'Your verification code is {code}. Valid for 5 minutes. Do not share this code.',
        'expires_in' => 5, // Optional: expires in 5 minutes (1-10 min)
    ]);

    echo "✓ Verification code sent with custom template!\n";
    echo "Recipient: {$response->phone}\n";
    echo "Status: {$response->status}\n";
    echo "Expires at: {$response->expires_at}\n";
} catch (ValidationException $e) {
    echo "✗ Validation error: {$e->getMessage()}\n";
    // Common validation errors:
    // - Missing {code} placeholder in template
    // - Invalid phone number format
    // - expires_in out of range (must be 1-10 minutes)
} catch (\Exception $e) {
    echo "✗ Failed to send verification: {$e->getMessage()}\n";
}
