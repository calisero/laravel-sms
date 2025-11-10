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
- 🏗️ **PSR-4 compliant** with full test coverage

> Internal package logging was removed. Add your own logging in event listeners/subscribers.

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
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

Enable webhook handling by setting `CALISERO_WEBHOOK_ENABLED=true`. The package will register a POST endpoint at `/calisero/webhook` (or your configured `CALISERO_WEBHOOK_PATH`).

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
To disable injection, set `CALISERO_WEBHOOK_ENABLED=false
