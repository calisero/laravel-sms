# Laravel SMS Package for Calisero

[![Latest Version on Packagist](https://img.shields.io/packagist/v/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)
[![CI](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)

A first-class Laravel 12 package that wraps the [Calisero PHP SDK](https://github.com/calisero/calisero-php) and provides idiomatic Laravel features for sending SMS messages through the Calisero API.

## Features

- 🚀 **Laravel 12** ready with full support for the latest features
- 📱 **Easy SMS sending** via Facade, Notification channels, or direct client usage
- 🔐 **Webhook handling** with automatic signature verification
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
CALISERO_API_KEY=your-api-key-here
CALISERO_BASE_URI=https://rest.calisero.ro/api/v1
CALISERO_TIMEOUT=10.0
CALISERO_CONNECT_TIMEOUT=3.0
CALISERO_RETRIES=5
CALISERO_RETRY_BACKOFF_MS=200

# Optional: Webhook configuration
CALISERO_WEBHOOK_SECRET=your-webhook-secret
CALISERO_WEBHOOK_PATH=calisero/webhook
```

## Usage

### Using the Facade

The simplest way to send an SMS:

```php
use Calisero\LaravelSms\Facades\Calisero;

Calisero::sendSms([
    'to' => '+1234567890',
    'text' => 'Hello from Laravel!',
    // 'from' => 'MyBrand' // Include ONLY if approved by Calisero
]);
```

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

To handle delivery status webhooks, first configure your webhook secret in the environment, then listen for the events:

```php
use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Illuminate\Support\Facades\Event;

Event::listen(MessageSent::class, function (MessageSent $event) {
    // Message accepted / sent by provider (not yet delivered)
    $data = $event->messageData;
});

Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    // Handle successful final delivery
    $data = $event->messageData;
});

Event::listen(MessageFailed::class, function (MessageFailed $event) {
    // Handle delivery failure
    $data = $event->messageData;
});
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

The webhook endpoint will be automatically registered at `/calisero/webhook` (or your configured path) when a webhook secret is set.

### Artisan Commands

#### Send a test SMS

```bash
php artisan calisero:sms:test +1234567890 --from="MyApp" --text="Test message"
```

#### Verify webhook signatures (for development)

```bash
php artisan calisero:webhook:verify signature-here --payload='{"test": "data"}'
```

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
1. Log in to your Calisero dashboard (https://calisero.ro) and navigate to Sender IDs
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
