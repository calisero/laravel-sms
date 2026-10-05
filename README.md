# Laravel SMS Package for Calisero

[![Latest Version on Packagist](https://img.shields.io/packagist/v/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)
[![tests](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat-square)](https://phpstan.org)
[![License](https://img.shields.io/packagist/l/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)
[![Tests](https://img.shields.io/github/actions/workflow/status/calisero/laravel-sms/ci.yml?branch=main&label=tests&style=flat-square)](https://github.com/calisero/laravel-sms/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/calisero/laravel-sms.svg?style=flat-square)](https://packagist.org/packages/calisero/laravel-sms)

A first-class Laravel package that wraps the [Calisero PHP SDK](https://github.com/calisero/calisero-php) and provides idiomatic Laravel features for sending SMS messages through the Calisero API. Supports **Laravel 12 and Laravel 13**.

## Features

- 🚀 **Laravel 12 & 13** ready with full support for the latest features
- 📱 **Easy SMS sending** via Facade, Notification channels, or direct client usage
- 🔗 **URL shortening** with click statistics for every link of a message
- 🔐 **Two-Factor Authentication** with verification codes API
- 🔒 **Webhook handling** with token-based security and typed delivery events
- 📈 **Daily sending limit & credit monitoring** through events and the account API
- ✅ **Validation rules** for phone numbers (E.164) and sender IDs
- 🎯 **Queue support** for reliable message delivery
- 🧪 **Artisan commands** for testing and development
- 🧯 **Typed error handling**, with the trace ID of every failed request
- 🏗️ **PSR-4 compliant** with full test coverage

> Internal package logging was removed. Add your own logging in event listeners/subscribers.

## Requirements

| Package version | Laravel | PHP | Calisero PHP SDK | Calisero API |
| --- | --- | --- | --- | --- |
| `^1.3` | 12.x, 13.x | 8.2 – 8.5 (Laravel 13 requires PHP 8.3+) | `^2.3` | 1.0.14 |
| `1.2.x` | 12.x, 13.x | 8.2 – 8.5 (Laravel 13 requires PHP 8.3+) | `^2.0` | 1.0.12 |
| `1.0.x` – `1.1.x` | 12.x | 8.2 – 8.4 | `^2.0` | 1.0.12 |

Composer picks the right Laravel release for your PHP version automatically: on PHP 8.2 you get
Laravel 12, and on PHP 8.3, 8.4 or 8.5 you can run either Laravel 12 or 13.

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

# Optional: Account ID for the account, balance and daily limit lookups
CALISERO_ACCOUNT_ID=your-account-id

# Optional: Connection Settings (seconds)
CALISERO_TIMEOUT=10.0
CALISERO_CONNECT_TIMEOUT=3.0

# Optional: Webhook Configuration
CALISERO_WEBHOOK_ENABLED=true
CALISERO_WEBHOOK_PATH=calisero/webhook
CALISERO_WEBHOOK_TOKEN=your-shared-secret

# Optional: Credit Monitoring
CALISERO_CREDIT_LOW=500
CALISERO_CREDIT_CRITICAL=100

# Optional: Daily Sending Limit Monitoring
CALISERO_DAILY_LIMIT_LOW=100
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

$message = $response->getData();
echo $message->getId();     // keep it to match the delivery webhooks
echo $message->getStatus(); // scheduled, sent...
```

#### Sending Options

Every optional parameter accepts snake_case or camelCase (`shorten_urls` or `shortenUrls`):

```php
use Calisero\LaravelSms\Facades\Calisero;

$response = Calisero::sendSms([
    'to' => '+40712345678',
    'text' => 'Your order shipped: https://shop.example.com/orders/123',
    'from' => 'MyBrand',                     // an approved sender ID
    'shorten_urls' => true,                  // replace the links with short ones
    'schedule_at' => now()->addHour(),       // a date-time, or a 'Y-m-d H:i:s' string in Romania time
    'validity' => 24,                        // hours
    'visible_body' => 'Your order shipped',  // shown in the dashboard and the API instead of the text
    'callback_url' => 'https://example.com/hooks/sms', // instead of the package webhook
]);
```

- **`schedule_at`**: the API reads it as `Y-m-d H:i:s` in Romania time (`Europe/Bucharest`) and
  refuses ISO 8601 strings such as `2026-10-05T10:00:00Z`. Pass a `DateTimeInterface` (a Carbon
  instance, `now()->addHour()`…) and the package converts it for you; a string is sent as is.
- **`shorten_urls`**: Calisero replaces the `http://` and `https://` links of the text with short ones
  before sending. The short links, and how many times each was opened, come back on the message:

```php
foreach ($response->getData()->getShortenedUrls() as $link) {
    echo $link->getOriginalLink() . ' -> ' . $link->getShortenedLink();
}

// Later, with click statistics
$message = Calisero::getMessageStatus($messageId)->getData();
foreach ($message->getShortenedUrls() as $link) {
    echo $link->getShortenedLink() . ': ' . $link->getClickCount() . ' clicks, last ' . ($link->getLastClick() ?? 'never');
}
```

- **What the answer reports**: `$response->getResponseMeta()` gives the request's trace ID and, while
  the account has a daily sending limit, how many messages it can still send today:

```php
$meta = $response->getResponseMeta();
$meta->getTraceId();        // quote it to Calisero support
$meta->getDailyLimit();     // null when the account has no daily limit
$meta->getDailyRemaining(); // what is left today, after this message
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
echo "Code expires at: " . $response->getData()->getExpiresAt();

// Check/verify the code entered by user
$result = Calisero::checkVerification([
    'to' => '+40712345678',
    'code' => '123456', // Code entered by user (6 characters)
]);

if ('verified' === $result->getData()->getStatus()) {
    // Code is valid, proceed with authentication
    echo "Verification successful!";
} else {
    // Code is invalid or expired
    echo "Verification failed!";
}
```

> **Note**: Either `brand` OR `template` is required when sending verification codes. The template must contain `{code}` placeholder. Codes are 6 characters and sent via SMS.
> A verification code's SMS counts towards the account's [daily sending limit](#daily-sending-limit) like any other message.

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

`SmsMessage` covers every sending option:

```php
SmsMessage::create('Track your order: https://shop.example.com/orders/123')
    ->from('MyBrand')                       // an approved sender ID
    ->to('+40712345678')                    // instead of the notifiable's number
    ->shortenUrls()                         // replace the links with short ones
    ->scheduleAt(now()->addHour())          // converted to Romania time
    ->validity(24)                          // hours
    ->visibleBody('Track your order')       // shown in the dashboard instead
    ->callbackUrl('https://example.com/hooks/sms');
```

The channel returns the API's answer, so a `NotificationSent` listener can keep the message ID:

```php
use Illuminate\Notifications\Events\NotificationSent;

Event::listen(function (NotificationSent $event) {
    if ('calisero' === $event->channel && null !== $event->response) {
        $messageId = $event->response->getData()->getId();
    }
});
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

    public function sendWelcomeSms(string $phone): string
    {
        return $this->client->sendSms([
            'to' => $phone,
            'text' => 'Welcome to our service!',
            'from' => 'MyApp',
        ])->getData()->getId();
    }
}
```

The client also reads messages and the account (`CALISERO_ACCOUNT_ID` is needed for the account):

```php
$client->getMessageStatus($messageId); // GetMessageResponse
$client->listMessages(page: 2);        // PaginatedMessages
$client->deleteMessage($messageId);    // only while it is still scheduled
$client->getAccount();                 // Account: credit, status, daily limit
$client->getBalance();                 // float, the account's credit
```

> The Calisero API takes no idempotency key, so `SmsMessage::idempotencyKey()` and an
> `idempotency_key` parameter have no effect. The API refuses (422) the same message to the same
> recipient sent again within a few seconds.

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
- `sent` → `MessageSent` – the message was accepted and dispatched to the network
- `delivered` → `MessageDelivered` – the handset/network confirmed delivery
- `undelivered` → `MessageFailed` – delivery permanently failed

Each event carries the raw payload in `$event->messageData`, and `$event->message()` reads it with
the SDK's `DeliveryWebhookMessage`, whose getters are typed (`null` when a required field is missing):

```php
Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    $message = $event->message();

    $message?->getMessageId();      // string
    $message?->getDeliveredAt();    // ?string
    $message?->getPrice();          // float
    $message?->getDailyRemaining(); // ?int, null when the account has no daily limit
});
```

Calisero retries a callback only when the connection fails or your endpoint does not answer
within 2 seconds (at most 5 attempts); a non-2xx answer is not retried. Keep the endpoint fast:
queue the work in your listeners (`ShouldQueue`).

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
  "remainingBalance": 999.43,
  "dailyLimit": 1000,
  "dailyRemaining": 588,
  "sentToday": 412
}
```
`dailyLimit` and `dailyRemaining` are `null` while the account has no daily sending limit.
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
  "remainingBalance": 999.43,
  "dailyLimit": 1000,
  "dailyRemaining": 588,
  "sentToday": 412
}
```
A failed delivery has `"status": "undelivered"` and a `deliveredAt` of `null`.

#### Automatic callback_url Injection
If `CALISERO_WEBHOOK_ENABLED=true` (or `1`, `on`, `yes`), every `sendSms()` call **without** an explicit `callback_url` (or `callbackUrl`) automatically includes one pointing to the named route `calisero.webhook` (if registered) or a URL built from `app.url` + the configured path.  
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

Options: `--from`, `--text`, `--visible-body`, `--validity` (hours), `--schedule-at`
(`Y-m-d H:i:s`, Romania time), `--callback-url` and `--shorten-urls`. The result shows what is left
of the daily sending limit and the trace ID; a refusal shows the API's message and the trace ID.

#### SMS Status

```bash
php artisan calisero:sms:status 019961d8-3338-700c-be17-10d061f03a5c
```

Also lists the shortened links of the message with their click counts.

#### Account

```bash
php artisan calisero:account
```

Shows the account of `CALISERO_ACCOUNT_ID`: credit, status, sandbox, and the daily sending limit
with what is left of it today.

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

## Environment Variables Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `CALISERO_API_KEY` | **Yes** | - | Your Calisero API key from the dashboard |
| `CALISERO_BASE_URI` | No | `https://rest.calisero.ro/api/v1` | Calisero API base URL |
| `CALISERO_ACCOUNT_ID` | No | - | Your account ID, for `getAccount()`, `getBalance()` and `calisero:account` |
| `CALISERO_TIMEOUT` | No | `10.0` | Request timeout in seconds (rounded up to whole seconds) |
| `CALISERO_CONNECT_TIMEOUT` | No | `3.0` | Connection timeout in seconds (rounded up to whole seconds) |
| `CALISERO_WEBHOOK_ENABLED` | No | `false` | Enable webhook handling (`true`, `1`, `on` or `yes`) |
| `CALISERO_WEBHOOK_PATH` | No | `calisero/webhook` | Webhook endpoint path |
| `CALISERO_WEBHOOK_TOKEN` | No | - | Shared secret for webhook authentication |
| `CALISERO_CREDIT_LOW` | No | - | Credit threshold for low balance alerts |
| `CALISERO_CREDIT_CRITICAL` | No | - | Credit threshold for critical balance alerts |
| `CALISERO_DAILY_LIMIT_LOW` | No | - | Messages left today at or below which `DailyLimitLow` is dispatched |

## Advanced Configuration

The configuration file (`config/calisero.php`) allows you to customize:

- API connection settings (base URI, timeouts)
- Webhook path, middleware and token
- Credit and daily sending limit thresholds

Requests are not retried automatically: the API takes no idempotency key, so retrying a send that
timed out could deliver the message twice. Retry from your own job when it is safe to.

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
- `Calisero\LaravelSms\Events\CreditLow` (remainingBalance float)
- `Calisero\LaravelSms\Events\CreditCritical` (remainingBalance float)

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

## Daily Sending Limit

Every Calisero account has its own daily sending limit. Each real message counts once, whatever its
number of parts, verification codes included; test messages (a sandbox account or API key) never
count. The day ends at midnight, Romania time (`Europe/Bucharest`).

**Where to read it**

```php
// The account (needs CALISERO_ACCOUNT_ID)
$account = Calisero::getAccount();
$account->getDailyLimit();     // ?int, null when no limit applies
$account->getDailyRemaining(); // ?int
$account->getSentToday();      // int

// After each message or verification code you create
$response->getResponseMeta()->getDailyRemaining();
```

From the command line: `php artisan calisero:account`.

**When it is reached**, the API answers `429` and the SDK throws
`Calisero\Sms\Exceptions\DailyLimitExceededException`: nothing was sent and nothing billed. It extends
`RateLimitedException`, so catch it first to tell it from the request rate limit (240 requests a
minute):

```php
use Calisero\Sms\Exceptions\DailyLimitExceededException;
use Calisero\Sms\Exceptions\RateLimitedException;

try {
    Calisero::sendSms(['to' => '+40712345678', 'text' => 'Hello!']);
} catch (DailyLimitExceededException $e) {
    $e->getDailyLimit();  // e.g. 1000
    $e->getResetsAt();    // e.g. 2026-10-06T00:00:00+03:00
    $e->getRetryAfter();  // seconds until midnight, Romania time
    // A queued job: $this->release($e->getRetryAfter() ?? 3600);
} catch (RateLimitedException $e) {
    $e->getRetryAfter();  // seconds; the request rate limit frees up quickly
}
```

**Before it is reached**, set a threshold and listen for `DailyLimitLow`, dispatched by the delivery
webhook (which must be enabled) whenever the messages left today are at or below it:

```env
CALISERO_DAILY_LIMIT_LOW=100   # Emit DailyLimitLow when dailyRemaining <= 100
```

```php
use Calisero\LaravelSms\Events\DailyLimitLow;

Event::listen(DailyLimitLow::class, fn (DailyLimitLow $e) => Log::warning('Calisero daily limit almost reached', [
    'limit' => $e->dailyLimit,
    'remaining' => $e->dailyRemaining,
    'sent_today' => $e->sentToday,
]));
```

To raise the limit, contact Calisero.

## Examples

A curated set of runnable usage examples lives in the [`examples/`](examples) directory:

| Scenario | File |
|----------|------|
| Send an SMS via Facade | `examples/send_sms_facade.php` |
| Send notification | `examples/notification_example.php` |
| Send with shortened URLs, read the daily limit left | `examples/send_sms_with_shortened_urls.php` |
| Register webhook & delivery listeners | `examples/webhook_listeners.php` |
| Credit monitoring listeners | `examples/credit_monitoring_listeners.php` |
| Daily sending limit (account, refusal, `DailyLimitLow`) | `examples/daily_limit.php` |
| Event subscriber pattern | `examples/event_subscriber.php` |
| A one-off client with a longer timeout | `examples/custom_config_snippet.php` |

Quick peek (webhook event handling):
```php
Event::listen(MessageSent::class, fn($e) => logger()->info('Message sent', $e->messageData));
Event::listen(MessageDelivered::class, fn($e) => logger()->info('Message delivered', $e->messageData));
Event::listen(MessageFailed::class, fn($e) => logger()->warning('Message failed', $e->messageData));
```

See the [Examples README](examples/README.md) for setup & detailed walkthroughs.

## Error Handling

The SDK throws a typed exception for every error status, carrying the API's own message:

```php
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\DailyLimitExceededException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ValidationException;

try {
    Calisero::sendSms([
        'to' => '+40712345678',
        'text' => 'Hello!',
    ]);
} catch (UnauthorizedException $e) {
    // 401: the API key is missing or invalid
} catch (ValidationException $e) {
    // 422: $e->getValidationErrors() lists the fields at fault
} catch (DailyLimitExceededException $e) {
    // 429: the daily sending limit is reached until $e->getResetsAt()
} catch (RateLimitedException $e) {
    // 429: the request rate limit, retry after $e->getRetryAfter() seconds
} catch (ApiException $e) {
    // 403, 404, 5xx, no answer...: $e->getStatusCode()
    logger()->error('Calisero error: ' . $e->getMessage(), ['trace_id' => $e->getTraceId()]);
}
```

| Exception | Status |
| --- | --- |
| `UnauthorizedException` | 401 |
| `ForbiddenException` | 403 |
| `NotFoundException` | 404 |
| `ValidationException` | 422 |
| `DailyLimitExceededException` (extends `RateLimitedException`) | 429, `code: daily_limit_exceeded` |
| `RateLimitedException` | 429 |
| `ServerException` | 500, 502, 503, 504 |
| `ApiException` (the parent of all of the above) | any other error |
| `TransportException` | no answer (connection failed, timeout) |

Every `ApiException` has `getTraceId()`: quote it to Calisero support, or look the request up in
the dashboard under Developers → Debug. The package's own `\InvalidArgumentException` and
`\RuntimeException` report a missing parameter or configuration before any request is made.

## Testing

Basic test run:

```bash
composer test
```

## Quality Assurance (CI / Local)

The project ships with an automated GitHub Actions workflow (`.github/workflows/ci.yml`) that runs:

- Code Style (PHP CS Fixer) on PHP 8.2, 8.3, 8.4, 8.5
- Static Analysis (PHPStan) on PHP 8.2, 8.3, 8.4, 8.5 against Laravel 12 and 13
- Test Matrix on PHP 8.2, 8.3, 8.4, 8.5 against Laravel 12 and 13
  (Laravel 13 is skipped on PHP 8.2, which it does not support)

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

To test against a specific Laravel version locally:

```bash
# Laravel 13
composer update --with="illuminate/support:^13.0" --with="illuminate/notifications:^13.0" \
  --with="illuminate/validation:^13.0" --with="illuminate/routing:^13.0" --with="illuminate/console:^13.0"

# Laravel 12
composer update --with="illuminate/support:^12.0" --with="illuminate/notifications:^12.0" \
  --with="illuminate/validation:^12.0" --with="illuminate/routing:^12.0" --with="illuminate/console:^12.0"
```

Notes:
- Running php-cs-fixer on a PHP version newer than the project minimum is allowed via
  `setUnsupportedPhpVersionAllowed()` in `.php-cs-fixer.php` (the older `PHP_CS_FIXER_IGNORE_ENV`
  environment variable is deprecated and no longer used).
- PHPUnit configuration uses the modern schema; tests pass with zero deprecations on both Laravel 12 and 13.

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
