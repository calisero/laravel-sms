<?php

/**
 * Example: Check verification code with comprehensive error handling
 *
 * This example demonstrates proper error handling for all verification scenarios:
 * - Valid code
 * - Invalid code
 * - Expired code
 * - Too many attempts
 * - Verification not found (404)
 * 
 * Note: Verification codes are case-insensitive. Both 'SBMH0f' and 'sbmh0f' are valid.
 */

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\RateLimitedException;

try {
    $result = Calisero::checkVerification([
        'to' => '+40712345678',
        'code' => '123456',
    ]);

    if ('verified' === $result->status) {
        echo "✓ Verification successful!\n";
        echo "Phone number verified: {$result->phone}\n";
        echo "Status: {$result->status}\n";
        echo "Verified at: {$result->verified_at}\n";

        // Proceed with authentication/registration
        // Auth::login($user);
    } else {
        echo "✗ Verification failed\n";
        echo "Status: {$result->status}\n";
        echo "Reason: Invalid or expired code\n";
    }
} catch (ValidationException $e) {
    // Invalid code format or validation error
    echo "✗ Invalid code format: {$e->getMessage()}\n";
    // Example: code must be 6 digits
} catch (NotFoundException $e) {
    // No verification found for this phone number
    echo "✗ No verification request found: {$e->getMessage()}\n";
    echo "Please request a new verification code.\n";
} catch (RateLimitedException $e) {
    // Too many verification attempts
    echo "✗ Too many attempts: {$e->getMessage()}\n";
    echo "Please wait before trying again.\n";

    // Respect Retry-After header if available
    if ($retryAfter = $e->getRetryAfter()) {
        echo "Retry after: {$retryAfter} seconds\n";
    }
} catch (\Exception $e) {
    echo "✗ Verification check failed: {$e->getMessage()}\n";
}
