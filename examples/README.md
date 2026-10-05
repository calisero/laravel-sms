# Calisero Laravel SMS Examples

This directory contains focused, copy‑paste friendly examples for common scenarios when integrating the `calisero/laravel-sms` package into a Laravel application.

> These files are illustrative. They assume you are inside a normal Laravel app (not just this package repo). For quick experimentation, place snippets into `routes/console.php`, a tinker session, or a dedicated command.

## Prerequisites
1. Install the package:
   ```bash
   composer require calisero/laravel-sms
   ```
2. Publish (optional) config:
   ```bash
   php artisan vendor:publish --provider="Calisero\\LaravelSms\\ServiceProvider" --tag=calisero-config
   ```
3. Set required ENV variables in `.env`:
   ```env
   CALISERO_API_KEY=your-api-key
   CALISERO_ACCOUNT_ID=your-account-id          # optional: account, balance, daily limit
   CALISERO_WEBHOOK_PATH=calisero/webhook        # optional override
   CALISERO_WEBHOOK_ENABLED=true                 # enable webhook route & callback injection
   CALISERO_WEBHOOK_TOKEN=your-secret            # optional: require ?token= on callbacks
   CALISERO_CREDIT_LOW=500                       # optional
   CALISERO_CREDIT_CRITICAL=100                  # optional
   CALISERO_DAILY_LIMIT_LOW=100                  # optional
   ```

## File Overview
| File | Purpose |
|------|---------|
| `send_sms_facade.php` | Minimal Facade based send (synchronous) with error handling |
| `send_sms_with_shortened_urls.php` | Shortened URLs and their clicks, scheduling, the daily limit left |
| `notification_example.php` | Using a Notification + custom `toCalisero` method |
| `verification_with_brand.php` | Send verification code using brand name |
| `verification_with_template.php` | Send verification code with custom template |
| `verification_check_with_errors.php` | Check verification codes with comprehensive error handling |
| `complete_2fa_flow.php` | Complete two-factor authentication implementation |
| `webhook_listeners.php` | Register runtime webhook event listeners |
| `credit_monitoring_listeners.php` | Listen for credit threshold events |
| `daily_limit.php` | Read the daily sending limit, handle its refusal, listen for `DailyLimitLow` |
| `event_subscriber.php` | Centralized subscriber wiring multiple events |
| `custom_config_snippet.php` | A one-off client with a longer timeout |

---
## 1. Sending an SMS (Facade)
See: `send_sms_facade.php`
- Reads the message ID and status from the answer
- Shows basic error handling skeleton, with the trace ID of a failed request

## 2. Shortened URLs
See: `send_sms_with_shortened_urls.php`
- `'shorten_urls' => true` (or `SmsMessage::shortenUrls()`) replaces the links of the text with short ones
- `getShortenedUrls()` lists them, with their click counts once the message is read again
- `schedule_at` accepts a date-time, converted to Romania time; a string must be `Y-m-d H:i:s`

## 3. Notification Channel
See: `notification_example.php`
- Implements `routeNotificationForCalisero` on a Notifiable model
- Uses `SmsMessage` fluent builder

## 4. Verification API (2FA)

### 4.1 Send Verification with Brand
See: `verification_with_brand.php`
- Codes are **auto-generated** by Calisero (6 alphanumeric characters, case-insensitive)
- Uses brand name for default SMS template
- Configurable expiration (1-10 minutes)

### 4.2 Send Verification with Template
See: `verification_with_template.php`
- Custom message template with `{code}` placeholder
- Template max length: 600 characters

### 4.3 Check Verification Codes
See: `verification_check_with_errors.php`
- Comprehensive error handling for all scenarios
- Handles: invalid code, expired code, too many attempts, 404
- Status: `'verified'` or `'unverified'`

### 4.4 Complete 2FA Flow
See: `complete_2fa_flow.php`
- Full implementation from send to verify
- Session management
- Authentication integration
- Handles the daily sending limit apart from the request rate limit

**Parameters:**

| Parameter | Required | Type | Constraints |
|-----------|----------|------|-------------|
| `to` (or `phone`) | Yes | string | E.164 format |
| `brand` | Conditional | string | Max 120 chars, required if no template |
| `template` | Conditional | string | Max 600 chars, must contain `{code}`, required if no brand |
| `expires_in` | No | integer | 1-10 minutes (default: 5) |

**Response:** `sendVerification()` returns a `CreateVerificationResponse` and `checkVerification()` a
`GetVerificationResponse`; `getData()` gives the `Verification`:

```php
$verification = $result->getData();
$verification->getPhone();      // recipient phone number
$verification->getStatus();     // 'verified' or 'unverified'
$verification->getExpiresAt();  // ISO 8601 timestamp
$verification->getVerifiedAt(); // ISO 8601 timestamp, null if unverified
$verification->getAttempts();   // number of verification attempts
$verification->isExpired();     // bool
```

**Common Exceptions:**

| Exception | When | HTTP Status | How to Handle |
|-----------|------|-------------|---------------|
| `ValidationException` | Invalid parameters (missing {code}, wrong expires_in, etc.) | 422 | Fix the request parameters |
| `NotFoundException` | No verification request exists | 404 | User needs to request new code |
| `DailyLimitExceededException` | The account's daily sending limit is reached | 429 | Try again after `getResetsAt()` |
| `RateLimitedException` | Too many requests | 429 | Respect `getRetryAfter()`, show cooldown |
| `UnauthorizedException` | Invalid API key | 401 | Check credentials |

> **Note**: Verification codes are **case-insensitive**. Both `SBMH0f` and `sbmh0f` are treated as the same code.

## 5. Webhook Events
See: `webhook_listeners.php`
- Listens for lifecycle events: `MessageSent` (`sent`), `MessageDelivered` (`delivered`), `MessageFailed` (`undelivered`)
- Reads the payload raw (`$event->messageData`) or typed (`$event->message()`)

## 6. Credit Monitoring
See: `credit_monitoring_listeners.php`
- Reacts to `CreditLow` and `CreditCritical`
- Good place to trigger Slack/email alerts

## 7. Daily Sending Limit
See: `daily_limit.php`
- Reads the account's limit, what is left today and what was sent
- Tells `DailyLimitExceededException` from the request rate limit
- Reacts to `DailyLimitLow` before the limit is reached

## 8. Event Subscriber Pattern
See: `event_subscriber.php`
- Groups all related SMS event handling in one place, auto-registered via service provider

## 9. One-off Client
See: `custom_config_snippet.php`
- Builds a separate client with a longer timeout: the facade's client is built once, so changing the config later does not affect it

---
## Running Examples
These files are *not* automatically autoloaded. For experimentation you can:

1. Copy the relevant code into `routes/console.php` and run `php artisan tinker` or an artisan command.
2. Or temporarily place inside a custom command handle() method.
3. Or create a one-off script in your Laravel root that includes `vendor/autoload.php` and bootstraps the app:
   ```php
   require __DIR__.'/vendor/autoload.php';
   $app = require __DIR__.'/bootstrap/app.php';
   $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
   // Paste example code below
   ```

---
## Common Patterns

### Resend Logic with Cooldown

```php
public function resendVerification(Request $request)
{
    $lastSent = session('verification_sent_at');

    if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
        return response()->json([
            'error' => 'Please wait before requesting another code',
            'retry_in' => 60 - now()->diffInSeconds($lastSent),
        ], 429);
    }

    // Send new verification...
}
```

### Attempt Tracking

```php
// Track failed attempts in session
$attempts = session('verification_attempts', 0);

if ($attempts >= 5) {
    return response()->json(['error' => 'Too many failed attempts'], 429);
}

// After failed verification:
session()->increment('verification_attempts');
```

---
## Production Notes
- Use queued notifications for better throughput.
- The API takes no idempotency key, and the package does not retry: retry from your own job when a
  failure means nothing was sent (a `DailyLimitExceededException` or `RateLimitedException`, for
  instance). The API refuses (422) the same message to the same recipient sent again within a few seconds.
- Monitor credit and the daily sending limit with threshold events + alerting (Slack, email, etc.).
- Log `getTraceId()` with every failed request: Calisero support can look it up.
- For 2FA, use the Verification API instead of manually generating OTP codes.
- Rate limit verification endpoints (Laravel's throttle middleware), clear the session after a
  successful verification, and keep error details from users.

## Support

For more information:
- [Main README](../README.md)
- [Calisero API Documentation](https://docs.calisero.ro)
- [GitHub Issues](https://github.com/calisero/laravel-sms/issues)
