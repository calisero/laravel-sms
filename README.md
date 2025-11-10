# Laravel SMS Package for Calisero

[![Latest Version on Packagist](https://img.shields.io/packagist/v/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)
[![tests](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat-square)](https://phpstan.org)
[![License](https://img.shields.io/packagist/l/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)
[![Tests](https://img.shields.io/github/actions/workflow/status/calisero/laravel-sms/ci.yml?branch=main&label=tests&style=flat-square)](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)

A first-class Laravel 12 package that wraps the [Calisero PHP SDK](https://github.com/calisero/calisero-php) and provides idiomatic Laravel features for sending SMS messages through the Calisero API.

## Features

- 🚀 **Laravel 12** ready with full support for the latest features
- 📱 **Easy SMS sending** via Facade, Notification channels, or direct client usage
- 🔐 **Two-Factor Authentication** with verification codes API
- 🔒 **Webhook handling** with token-based security
- ✅ **Validation rules** for phone numbers (E.164) and sender IDs
- 🎯 **Queue support** for reliable message delivery
- 🧪 **Artisan commands** for testing and development
- 📊 **Comprehensive logging** and error handling
- 🏗️ **PSR-4 compliant** with full test coverage

## Installation

You can install the package via Composer:

```bash
composer require calisero/laravel-sms
```

### Publish the configuration file

```bash
php artisan vendor:publish --provider="Calisero\\LaravelSms\\ServiceProvider" --tag="calisero-config"
```

### Configure your environment

Add your Calisero API credentials to your `.env` file:

```env
# Required: API Configuration
CALISERO_API_KEY=your-api-key-here
CALISERO_BASE_URI=https://rest.calisero.ro/api/v1

# Optional: Account ID for balance queries
CALISERO_ACCOUNT_ID=your-account-id

# Optional: Connection Settings
CALISERO_TIMEOUT=10.0
CALISERO_CONNECT_TIMEOUT=3.0
CALISERO_RETRIES=5
CALISERO_RETRY_BACKOFF_MS=200

# Optional: Webhook Configuration
CALISERO_WEBHOOK_ENABLED=true
CALISERO_WEBHOOK_PATH=calisero/webhook
CALISERO_WEBHOOK_TOKEN=your-shared-secret

# Optional: Credit Monitoring
CALISERO_CREDIT_LOW=500
CALISERO_CREDIT_CRITICAL=100

# Optional: Logging
CALISERO_LOG_CHANNEL=default
```

## Usage

### Quick Start

#### Sending SMS via Facade

```php
use Calisero\LaravelSms\Facades\Calisero;

$response = Calisero::sendSms([
    'to' => '+40712345678',
    'text' => 'Hello from Laravel!',
    // 'from' => 'MyBrand' // Include ONLY if approved by Calisero
]);
```

#### Verification Codes (2FA)

Send and verify one-time codes for two-factor authentication:

```php
use Calisero\LaravelSms\Facades\Calisero;

// Send a verification code (with brand)
$response = Calisero::sendVerification([
    'to' => '+40712345678',
    'brand' => 'MyApp', // Required if no template
    'expires_in' => 5, // Optional: 1-10 minutes, default 5
]);

// OR send with custom template
$response = Calisero::sendVerification([
    'to' => '+40712345678',
    'template' => 'Your verification code is {code}. Valid for 5 minutes.',
    'expires_in' => 5,
]);

// The response includes expiration time
echo "Code expires at: " . $response->expires_at;

// Check/verify the code entered by user
$result = Calisero::checkVerification([
    'to' => '+40712345678',
    'code' => '123456', // Code entered by user (6 characters)
]);

if ('verified' === $result->status) {
    // Code is valid, proceed with authentication
    echo "Verification successful!";
} else {
    // Code is invalid or expired
    echo "Verification failed!";
}
```

> **Note**: Either `brand` OR `template` is required when sending verification codes. The template must contain `{code}` placeholder. Codes are 6 characters and sent via SMS.

### Using Notifications

Create a notification class:

```php
use Calisero\LaravelSms\Notification\SmsMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['calisero'];
    }

    public function toCalisero($notifiable): SmsMessage
    {
        return SmsMessage::create('Welcome to our app!')
            // ->from('MyBrand') // Uncomment only after approval
            ;
    }
}
```

Send the notification:

```php
use App\Models\User;
use App\Notifications\WelcomeNotification;

$user = User::find(1);
$user->notify(new WelcomeNotification());
```

For the notification to work, your User model should implement the `routeNotificationForCalisero` method:

```php
public function routeNotificationForCalisero($notification): string
{
    return $this->phone; // Return the user's phone number
}
```

### Using the Direct Client

For more control, inject the client directly:

```php
use Calisero\LaravelSms\Contracts\SmsClient;

class SmsService 
{
    public function __construct(private SmsClient $client) {}

    public function sendWelcomeSms(string $phone): void
    {
        $this->client->sendSms([
            'to' => $phone,
            'text' => 'Welcome to our service!',
            'from' => 'MyApp',
            'idempotencyKey' => 'welcome-' . uniqid(),
        ]);
    }
}
```

### Validation Rules

Use the included validation rules in your form requests:

```php
use Calisero\LaravelSms\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SendSmsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', Rule::phoneE164()],
            'sender_id' => ['required', Rule::senderId()],
            'message' => ['required', 'string', 'max:1600'],
        ];
    }
}
```

### Webhook Handling

Enable webhooks by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

#### Securing the Webhook (Query Token)
If you also set `CALISERO_WEBHOOK_TOKEN=your-shared-secret`, the package will:
- Automatically append `?token=your-shared-secret` to the injected `callback_url` sent to Calisero (only when you did not supply a custom `callback_url`).
- Register a middleware that rejects any incoming webhook request not containing the correct `token` query parameter.

Requirements when token security is enabled:
- Each webhook request from Calisero must include the query parameter `token` with the exact configured value.
- If you manually override `callback_url`, you are responsible for including the `?token=...` segment yourself.
- If your explicit URL already contains a `token=` parameter, the library will not modify it.

Environment example:
```env
CALISERO_WEBHOOK_ENABLED=true
CALISERO_WEBHOOK_PATH=calisero/webhook
CALISERO_WEBHOOK_TOKEN=super-secret-value
```

Example of explicit override (token already present, no modification by the library):
```php
Calisero::sendSms([
    'to' => '+1234567890',
    'text' => 'Custom secured callback',
    'callback_url' => 'https://example.com/custom-hook?token=' . urlencode(config('calisero.webhook.token')),
]);
```

Rotation tip: rotate the token by
1. Adding a temporary second endpoint (optional) or a maintenance window.
2. Updating `CALISERO_WEBHOOK_TOKEN`.
3. Redeploying and updating the callback URL in Calisero (or sending a new message to propagate the injected URL).

If you leave `CALISERO_WEBHOOK_TOKEN` empty, no token middleware is attached and the endpoint is publicly accessible (POST only). Consider other controls (IP allow-list, WAF) if you opt out of the token.

Listen for the events:
```php
Event::listen(MessageSent::class, fn (MessageSent $e) => ...);
Event::listen(MessageDelivered::class, fn (MessageDelivered $e) => ...);
Event::listen(MessageFailed::class, fn (MessageFailed $e) => ...);
```

Statuses currently emitted (lifecycle):
- `sent` – the message was accepted and dispatched to the network
- `delivered` – the handset/network confirmed delivery
- `failed` – delivery permanently failed

Webhook payload example (flat structure):
```json
{
  "price": 0.0378,
  "sender": "CALISERO",
  "sentAt": "2025-09-19T11:59:44.000000Z",
  "status": "sent",
  "messageId": "019961d8-3338-700c-be17-10d061f03a5c",
  "recipient": "+40742***350",
  "scheduleAt": "2025-09-19T11:59:42.000000Z",
  "deliveredAt": null,
  "remainingBalance": 999.43
}
```
When the same message is later delivered you will receive another webhook with:
```json
{
  "price": 0.0378,
  "sender": "CALISERO",
  "sentAt": "2025-09-19T11:59:44.000000Z",
  "status": "delivered",
  "messageId": "019961d8-3338-700c-be17-10d061f03a5c",
  "recipient": "+40742***350",
  "scheduleAt": "2025-09-19T11:59:42.000000Z",
  "deliveredAt": "2025-09-19T12:00:24.000000Z",
  "remainingBalance": 999.43
}
```
A failed attempt would have `"status": "failed"` and usually a `deliveredAt` of `null`.

#### Automatic callback_url Injection
If `CALISERO_WEBHOOK_ENABLED=true`, every `sendSms()` call **without** an explicit `callback_url` (or `callbackUrl`) automatically includes one pointing to the named route `calisero.webhook` (if registered) or a URL built from `app.url` + the configured path.  
To override, supply your own `callback_url` parameter.  
To disable injection, set `CALISERO_WEBHOOK_ENABLED=false` or omit the env variable.

Edge cases:
- If `app.url` is not set and the route helper fails, a root-relative path like `/calisero/webhook` is used.
- Passing either `callback_url` or `callbackUrl` prevents injection.

Example (override):
```php
Calisero::sendSms([
    'to' => '+1234567890',
    'text' => 'Custom callback',
    'callback_url' => 'https://example.com/custom-hook',
]);
```

### Artisan Commands

The package provides several Artisan commands for testing and development:

#### Test SMS Sending

```bash
php artisan calisero:sms:test +40712345678 --from=YourApp --text="Test message"
```

#### Verification Commands

Send a verification code with brand:

```bash
php artisan calisero:verification:send +40712345678 --brand=MyApp
```

Send a verification code with custom template:

```bash
php artisan calisero:verification:send +40712345678 --template="Your code is {code}" --expires-in=5
```

Check a verification code:

```bash
php artisan calisero:verification:check +40712345678 123456
```

#### Webhook Verification

Verify webhook signatures offline:

```bash
php artisan calisero:webhook:verify "sha256=..." --payload='{"status":"delivered"}'
```

## Environment Variables Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `CALISERO_API_KEY` | **Yes** | - | Your Calisero API key from the dashboard |
| `CALISERO_BASE_URI` | No | `https://rest.calisero.ro/api/v1` | Calisero API base URL |
| `CALISERO_ACCOUNT_ID` | No | - | Your account ID for balance queries |
| `CALISERO_TIMEOUT` | No | `10.0` | Request timeout in seconds |
| `CALISERO_CONNECT_TIMEOUT` | No | `3.0` | Connection timeout in seconds |
| `CALISERO_RETRIES` | No | `5` | Number of retry attempts |
| `CALISERO_RETRY_BACKOFF_MS` | No | `200` | Backoff delay between retries (ms) |
| `CALISERO_WEBHOOK_ENABLED` | No | `false` | Enable webhook handling |
| `CALISERO_WEBHOOK_PATH` | No | `calisero/webhook` | Webhook endpoint path |
| `CALISERO_WEBHOOK_TOKEN` | No | - | Shared secret for webhook authentication |
| `CALISERO_CREDIT_LOW` | No | - | Credit threshold for low balance alerts |
| `CALISERO_CREDIT_CRITICAL` | No | - | Credit threshold for critical balance alerts |
| `CALISERO_LOG_CHANNEL` | No | `default` | Laravel log channel to use |

## Advanced Configuration

The configuration file (`config/calisero.php`) allows you to customize:

- API connection settings (timeouts, retries, backoff)
- Webhook path and middleware
- Logging channel preferences

## Sender ID (Alphanumeric) Requirements

Custom alphanumeric sender IDs (the `from` field) must be **pre‑approved by Calisero** before they can be used in production traffic. If you send a message with an unapproved sender:

- The API may reject the request (validation / 422).
- Or the gateway may substitute a default system sender / numeric originator.
- Delivery performance and branding can be impacted if you skip approval.

Approval Guidelines:
- Length: 3–11 characters (enforced by the `SenderId` validation rule).
- Allowed characters: letters, digits, spaces, hyphens (`-`), dots (`.`).
- No fully numeric sender IDs unless explicitly provisioned (use phone numbers instead).
- Avoid trademarks you do not own.

How to Request Approval:
1. Log in to your Calisero dashboard (https) and navigate to Sender IDs
2. Submit each desired sender (case‑sensitive) with a brief business justification.
3. Wait for confirmation before deploying to production.

Best Practices:
- Keep a configurable default sender (e.g. via env: `CALISERO_DEFAULT_SENDER=`) and only override per message when approved.
- In multi‑tenant apps, map tenants to approved sender pools—never accept raw user input as a sender.
- Log or alert when the API response indicates a sender rejection so you can remediate quickly.

Example (with fallback logic):
```php
$sender = config('services.calisero.default_sender');
if ($tenantSender && in_array($tenantSender, $approvedPool, true)) {
    $sender = $tenantSender;
}
Calisero::sendSms([
    'to' => '+1234567890',
    'text' => 'Hello!',
    'from' => $sender, // guaranteed approved
]);
```

> NOTE: If you do not have an approved sender yet, omit `from` to let Calisero assign a default.

## Credit Monitoring & Balance Alerts

Configure optional balance thresholds to emit events when your Calisero account credit becomes low or critical:

Environment variables:
```env
CALISERO_CREDIT_LOW=500        # Emit CreditLow when remainingBalance <= 500
CALISERO_CREDIT_CRITICAL=100   # Emit CreditCritical when remainingBalance <= 100
```

Events:
- `Calisero\\LaravelSms\\Events\\CreditLow` (remainingBalance float)
- `Calisero\\LaravelSms\\Events\\CreditCritical` (remainingBalance float)

Example listener registration:
```php
use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\CreditCritical;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

Event::listen(CreditLow::class, fn (CreditLow $e) => Log::warning('Calisero credit low', ['remaining' => $e->remainingBalance]));
Event::listen(CreditCritical::class, fn (CreditCritical $e) => Log::error('Calisero credit CRITICAL', ['remaining' => $e->remainingBalance]));
```

If a critical threshold is met, only `CreditCritical` is fired (not `CreditLow`). Leave variables unset (or null) to disable.

## Examples

A curated set of runnable usage examples lives in the [`examples/`](examples) directory:

| Scenario | File |
|----------|------|
| Send an SMS via Facade | `examples/send_sms_facade.php` |
| Send notification | `examples/notification_example.php` |
| Register webhook & delivery listeners | `examples/webhook_listeners.php` |
| Credit monitoring listeners | `examples/credit_monitoring_listeners.php` |
| Event subscriber pattern | `examples/event_subscriber.php` |
| Config customization snippet | `examples/custom_config_snippet.php` |

Quick peek (webhook event handling):
```php
Event::listen(MessageSent::class, fn($e) => logger()->info('Message sent', $e->messageData));
Event::listen(MessageDelivered::class, fn($e) => logger()->info('Message delivered', $e->messageData));
Event::listen(MessageFailed::class, fn($e) => logger()->warning('Message failed', $e->messageData));
```

See the [Examples README](examples/README.md) for setup & detailed walkthroughs.

## Error Handling

The package provides comprehensive error handling:

```php
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\RateLimitedException;

try {
    Calisero::sendSms([
        'to' => '+1234567890',
        'text' => 'Hello!',
    ]);
} catch (UnauthorizedException $e) {
    // Handle authentication errors
} catch (ValidationException $e) {
    // Handle validation errors
} catch (RateLimitedException $e) {
    // Handle rate limiting - respect Retry-After header if provided
} catch (\Throwable $e) {
    // Handle other errors
}
```

## Testing

Basic test run:

```bash
composer test
```

## Quality Assurance (CI / Local)

The project ships with an automated GitHub Actions workflow (`.github/workflows/ci.yml`) that runs:

- Code Style (PHP CS Fixer) on PHP 8.2, 8.3, 8.4
- Static Analysis (PHPStan) on PHP 8.2, 8.3, 8.4
- Test Matrix on PHP 8.2, 8.3, 8.4

Local commands:

```bash
# Run coding standards (no changes)
composer cs:check

# Auto-fix code style
composer cs:fix

# Static analysis
composer stan

# Full test suite
composer test

# Full QA pipeline (validate composer.json, cs:check, stan, test)
composer qa
```

Notes:
- `PHP_CS_FIXER_IGNORE_ENV` is set in scripts to allow running php-cs-fixer on PHP 8.4 until official support lands.
- PHPUnit configuration updated for modern schema; tests currently pass with zero deprecations.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Calisero Team](https://github.com/calisero)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Links

- [Calisero API Documentation](https://docs.calisero.ro)
- [Calisero PHP SDK](https://github.com/calisero/calisero-php)
- [Laravel Documentation](https://laravel.com/docs)

Calisero Laravel library allows you to send SMSs from your Laravel application using Calisero API
