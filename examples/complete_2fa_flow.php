<?php

/**
 * Example: Complete 2FA flow with verification
 *
 * This example shows a complete two-factor authentication implementation
 * using the verification API.
 * 
 * Note: Verification codes are 6 characters (alphanumeric) and case-insensitive.
 * Users can enter codes in any case: SBMH0f, sbmh0f, SbMh0F - all are valid.
 */

use Calisero\LaravelSms\Facades\Calisero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * Step 1: Send verification code to user's phone
 */
function sendVerificationCode(Request $request)
{
    $request->validate([
        'phone' => 'required|string',
    ]);

    try {
        // Send verification code with brand
        $response = Calisero::sendVerification([
            'to' => $request->phone,
            'brand' => 'MyApp',
            'expires_in' => 10, // 10 minutes expiration
        ]);

        // Store phone in session for verification step
        session([
            'verification_phone' => $request->phone,
            'verification_sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent',
            'expires_at' => $response->expires_at,
        ]);
    } catch (\Calisero\Sms\Exceptions\ValidationException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Invalid phone number format',
        ], 422);
    } catch (\Calisero\Sms\Exceptions\RateLimitedException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $e->getRetryAfter(),
        ], 429);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Failed to send verification code',
        ], 500);
    }
}

/**
 * Step 2: Verify the code entered by user
 */
function verifyCode(Request $request)
{
    $request->validate([
        'phone' => 'required|string',
        'code' => 'required|string|size:6', // 6 characters, case-insensitive
    ]);

    try {
        $result = Calisero::checkVerification([
            'to' => $request->phone,
            'code' => $request->code,
        ]);

        if ('verified' === $result->status) {
            // Verification successful - authenticate user
            $user = User::where('phone', $request->phone)->first();

            if ($user) {
                Auth::login($user);

                // Clear verification session data
                session()->forget(['verification_phone', 'verification_sent_at']);

                return response()->json([
                    'success' => true,
                    'message' => 'Verification successful',
                    'user' => $user,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                ], 404);
            }
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired verification code',
            ], 422);
        }
    } catch (\Calisero\Sms\Exceptions\NotFoundException $e) {
        return response()->json([
            'success' => false,
            'error' => 'No verification request found. Please request a new code.',
        ], 404);
    } catch (\Calisero\Sms\Exceptions\RateLimitedException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Too many verification attempts. Please try again later.',
            'retry_after' => $e->getRetryAfter(),
        ], 429);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Verification failed',
        ], 500);
    }
}
