<?php

/**
 * Example: Send verification code with Brand
 *
 * This example shows how to send a verification code using a brand name.
 * Brand is required when no template is provided.
 * 
 * Note: Verification codes are 6 characters (alphanumeric) and case-insensitive.
 * Example codes: SBMH0f, abc123, XYZ789
 */

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\UnauthorizedException;

try {
    $response = Calisero::sendVerification([
        'to' => '+40712345678',
        'brand' => 'MyApp', // Brand name shown in the SMS
        'expires_in' => 5, // Optional: expires in 5 minutes (1-10 min)
    ]);

    echo "✓ Verification code sent successfully!\n";
    echo "Recipient: {$response->phone}\n";
    echo "Status: {$response->status}\n";
    echo "Expires at: {$response->expires_at}\n";
} catch (ValidationException $e) {
    echo "✗ Validation error: {$e->getMessage()}\n";
    // Handle validation errors (invalid phone, brand, etc.)
} catch (UnauthorizedException $e) {
    echo "✗ Authentication failed: {$e->getMessage()}\n";
    // Check your API key
} catch (\Exception $e) {
    echo "✗ Failed to send verification: {$e->getMessage()}\n";
}
