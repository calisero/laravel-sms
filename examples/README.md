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
   CALISERO_WEBHOOK_PATH=calisero/webhook        # optional override
   CALISERO_WEBHOOK_ENABLED=true                 # enable webhook route & callback injection
   CALISERO_CREDIT_LOW=500                       # optional
   CALISERO_CREDIT_CRITICAL=100                  # optional
   ```

## File Overview
| File | Purpose |
|------|---------|
| `send_sms_facade.php` | Minimal Facade based send (synchronous) |
| `notification_example.php` | Using a Notification + custom `toCalisero` method |
| `verification_with_brand.php` | **NEW**: Send verification code using brand name |
| `verification_with_template.php` | **NEW**: Send verification code with custom template |
| `verification_check_with_errors.php` | **NEW**: Check verification codes with comprehensive error handling |
| `complete_2fa_flow.php` | **NEW**: Complete two-factor authentication implementation |
| `webhook_listeners.php` | Register runtime webhook event listeners |
| `credit_monitoring_listeners.php` | Listen for credit threshold events |
| `event_subscriber.php` | Centralized subscriber wiring multiple events |
| `custom_config_snippet.php` | Illustrates programmatic config overrides |

---
## 1. Sending an SMS (Facade)
See: `send_sms_facade.php`
- Demonstrates idempotency key usage
- Shows basic error handling skeleton

## 2. Notification Channel
See: `notification_example.php`
- Implements `routeNotificationForCalisero` on a Notifiable model
- Uses `SmsMessage` fluent builder

## 3. **NEW**: Verification API (2FA)
The package now includes full support for Calisero's Verification API for two-factor authentication:

### 3.1 Send Verification with Brand
See: `verification_with_brand.php`
- Codes are **auto-generated** by Calisero (6 alphanumeric characters, case-insensitive)
- Uses brand name for default SMS template
- Configurable expiration (1-10 minutes)

### 3.2 Send Verification with Template
See: `verification_with_template.php`
- Custom message template with `{code}` placeholder
- Template max length: 600 characters
- Must contain `{code}` placeholder

### 3.3 Check Verification Codes
See: `verification_check_with_errors.php`
- Comprehensive error handling for all scenarios
- Handles: invalid code, expired code, too many attempts, 404
- Status: `'verified'` or `'unverified'`

### 3.4 Complete 2FA Flow
See: `complete_2fa_flow.php`
- Full implementation from send to verify
- Session management
- Authentication integration
- Production-ready error handling

**API Parameters:**
| Parameter | Required | Type | Constraints |
|-----------|----------|------|-------------|
| `to` (or `phone`) | Yes | string | E.164 format |
| `brand` | Conditional | string | Max 120 chars, required if no template |
| `template` | Conditional | string | Max 600 chars, must contain `{code}`, required if no brand |
| `expires_in` | No | integer | 1-10 minutes (default: 5) |

**Response Properties:**
- `phone`: recipient phone number
- `status`: `'verified'` or `'unverified'`
- `expires_at`: ISO 8601 timestamp
- `verified_at`: ISO 8601 timestamp (null if unverified)
- `attempts`: number of verification attempts
- `expired`: boolean

**Common Exceptions:**
- `ValidationException` (422): Invalid parameters
- `NotFoundException` (404): No verification request exists
- `RateLimitedException` (429): Too many attempts
- `UnauthorizedException` (401): Invalid API key

> **Note**: Verification codes are **case-insensitive**. Both `SBMH0f` and `sbmh0f` are treated as the same code.

## 4. Webhook Events
See: `webhook_listeners.php`
- Listens for lifecycle events: `MessageSent`, `MessageDelivered`, `MessageFailed`
- Demonstrates minimal logging handlers

## 5. Credit Monitoring
See: `credit_monitoring_listeners.php`
- Reacts to `CreditLow` and `CreditCritical`
- Good place to trigger Slack/email alerts

## 6. Event Subscriber Pattern
See: `event_subscriber.php`
- Groups all related SMS event handling in one place, auto-registered via service provider

## 7. Dynamic Config Override
See: `custom_config_snippet.php`
- Adjusts timeout / retries at runtime (e.g., in a job) without republishing config

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
## Production Notes
- Always set an `idempotencyKey` for send operations inside loops/batches.
- Use queued notifications for better throughput.
- Monitor credit with threshold events + alerting (Slack, email, etc.).
- Handle retries at the job / message dispatch level if network transient failures occur.
- **NEW**: For 2FA, use the Verification API instead of manually generating OTP codes
- **NEW**: Verification codes are managed by Calisero for security and compliance
- **NEW**: Implement rate limiting on verification endpoints (Laravel throttle middleware)

## Need More?
Open an issue or discussion in the main repository with the scenario you'd like documented.

Happy building! 🚀


This directory contains practical, runnable examples demonstrating various features of the Laravel SMS package.

## Available Examples

### Verification (2FA) Examples

| File | Description |
|------|-------------|
| `verification_with_brand.php` | Send verification code using a brand name |
| `verification_with_template.php` | Send verification code with custom message template |
| `verification_check_with_errors.php` | Check verification codes with comprehensive error handling |
| `complete_2fa_flow.php` | Complete two-factor authentication implementation |

### SMS Sending Examples

| File | Description |
|------|-------------|
| `send_sms_facade.php` | Send SMS using the Facade |
| `notification_example.php` | Send SMS via Laravel Notifications |

### Webhook Examples

| File | Description |
|------|-------------|
| `webhook_listeners.php` | Register and handle webhook events |
| `credit_monitoring_listeners.php` | Monitor account credit balance |
| `event_subscriber.php` | Event subscriber pattern for webhooks |

### Configuration Examples

| File | Description |
|------|-------------|
| `custom_config_snippet.php` | Customize package configuration |

## Verification API Requirements

### Brand vs Template

When sending verification codes, you must provide **either** a `brand` OR a `template`:

**Using Brand:**
```php
Calisero::sendVerification([
    'to' => '+40712345678',
    'brand' => 'MyApp', // Brand name shown in SMS
    'expires_in' => 5, // Optional: 1-10 minutes
]);
```

**Using Template:**
```php
Calisero::sendVerification([
    'to' => '+40712345678',
    'template' => 'Your {code} is ready. Valid for 5 min.',
    // Template MUST contain {code} placeholder
    'expires_in' => 5, // Optional: 1-10 minutes
]);
```

### Parameters

| Parameter | Required | Type | Description | Constraints |
|-----------|----------|------|-------------|-------------|
| `to` | Yes | string | Phone number in E.164 format | Must be valid E.164 |
| `brand` | Conditional | string | Brand name for default template | Required if no template, max 120 chars |
| `template` | Conditional | string | Custom message template | Required if no brand, max 600 chars, must contain `{code}` |
| `expires_in` | No | integer | Code expiration in minutes | 1-10 minutes, default: 5 |

### Error Handling

The verification API can throw the following exceptions:

| Exception | When | HTTP Status | How to Handle |
|-----------|------|-------------|---------------|
| `ValidationException` | Invalid parameters (missing {code}, wrong expires_in, etc.) | 422 | Fix the request parameters |
| `NotFoundException` | No verification request exists | 404 | User needs to request new code |
| `RateLimitedException` | Too many attempts | 429 | Respect retry-after, show cooldown |
| `UnauthorizedException` | Invalid API key | 401 | Check credentials |

### Code Verification Responses

**Valid Code:**
```php
'verified' === $result->status
null !== $result->verified_at
'+40712345678' === $result->phone
```

**Invalid/Expired Code:**
```php
'unverified' === $result->status
true === $result->expired // if code expired
$result->attempts >= max // if too many attempts
```

> **Note**: Verification codes are **case-insensitive**. Both `SBMH0f` and `sbmh0f` are treated as the same code.

## Running the Examples

These examples are meant to be integrated into your Laravel application. To use them:

1. **Copy the relevant code** into your controller or service class
2. **Adjust namespaces and imports** as needed
3. **Configure environment variables** in your `.env` file:
   ```env
   CALISERO_API_KEY=your-api-key
   CALISERO_WEBHOOK_TOKEN=your-secret
   CALISERO_WEBHOOK_PATH=calisero/webhook        # optional override
   CALISERO_WEBHOOK_ENABLED=true                 # enable webhook route & callback injection
   CALISERO_CREDIT_LOW=500                       # optional
   CALISERO_CREDIT_CRITICAL=100                  # optional
   ```

4. **Test in your local environment** before deploying to production

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

### Security Best Practices

1. **Rate limit verification endpoints** (use Laravel's throttle middleware)
2. **Clear old sessions** after successful verification
3. **Log verification attempts** for security auditing
4. **Don't expose sensitive error details** to users
5. **Use HTTPS** for all verification endpoints
6. **Implement CSRF protection** on forms

## Support

For more information:
- [Main README](../README.md)
- [Calisero API Documentation](https://docs.calisero.ro)
- [GitHub Issues](https://github.com/calisero/laravel-sms/issues)
