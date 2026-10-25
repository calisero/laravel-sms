---
name: calisero-sms
description: "Use this skill whenever a Laravel application sends SMS or one-time codes through Calisero with calisero/laravel-sms: the Calisero facade (sendSms, sendVerification, checkVerification, getMessageStatus, listMessages, getAccount), the calisero notification channel and SmsMessage, OTP / 2FA / phone number verification, delivery reports through the package webhook and its MessageSent, MessageDelivered and MessageFailed events, credit and daily sending limit events, Calisero errors (401, 403, 404, 422, 429, trace ID), sender IDs, message length and cost, scheduling, and testing SMS code with mocks or sandbox API keys. Also use it when the user mentions Calisero, config/calisero.php or CALISERO_* variables. Do not use it for other SMS providers (Twilio, Vonage, AWS SNS) or for email."
license: MIT
metadata:
  author: calisero
---

# Calisero SMS in Laravel

`calisero/laravel-sms` wraps the Calisero API (`https://rest.calisero.ro/api/v1`, a Romanian transactional SMS API)
for Laravel 12 and 13. Use it rather than raw HTTP calls. The SDK underneath is `calisero/calisero-php`: DTOs live in
`Calisero\Sms\Dto`, exceptions in `Calisero\Sms\Exceptions`.

## 1. Configure

```bash
composer require calisero/laravel-sms
php artisan vendor:publish --provider="Calisero\LaravelSms\ServiceProvider" --tag="calisero-config"
```

```env
CALISERO_API_KEY=            # required; a sandbox key in local, staging and CI
CALISERO_ACCOUNT_ID=         # for getAccount(), getBalance(), calisero:account
CALISERO_WEBHOOK_ENABLED=false
CALISERO_WEBHOOK_TOKEN=      # a long random secret once the webhook is enabled
```

Optional: `CALISERO_BASE_URI` (keep it `https://`), `CALISERO_TIMEOUT` (10 s), `CALISERO_CONNECT_TIMEOUT` (3 s), `CALISERO_WEBHOOK_PATH`
(`calisero/webhook`), `CALISERO_CREDIT_LOW`, `CALISERO_CREDIT_CRITICAL`, `CALISERO_DAILY_LIMIT_LOW`. The config keys
mirror them under `calisero.*` (`calisero.api_key`, `calisero.webhook.enabled`, `calisero.daily_limit.low_threshold`...).
There is no default sender key: if the application needs one, add it to its own config and pass it as `from`.

Never hard-code the API key, never commit it, and read configuration with `config()`, not `env()`.

## 2. Send an SMS

```php
use Calisero\LaravelSms\Facades\Calisero;

$response = Calisero::sendSms([
    'to' => $user->phone,                  // E.164: +40712345678
    'text' => 'Your order #1001 has shipped.',
    'from' => config('services.sms.sender'), // optional: an approved sender only
]);

$sms = $response->getData();
$order->update(['sms_id' => $sms->getId(), 'sms_status' => $sms->getStatus()]);
Log::info('SMS sent', ['id' => $sms->getId(), 'parts' => $sms->getParts(), 'trace_id' => $response->getResponseMeta()->getTraceId()]);
```

| Key (snake_case or camelCase) | Meaning |
| --- | --- |
| `to`, `text` | Required: recipient in E.164, text up to 1600 characters |
| `from` | An approved sender ID; omit for the default sender |
| `schedule_at` | A Carbon/`DateTimeInterface` (converted to Romania time) or a `Y-m-d H:i:s` string already in Romania time |
| `validity` | Hours |
| `visible_body` | Text shown in the dashboard and API instead of the real one (e.g. a masked code) |
| `shorten_urls` | Shorten the links of the text; `getShortenedUrls()` lists them with click counts |
| `callback_url` | Your own delivery URL instead of the package webhook |

Unknown keys are silently ignored: check the spelling. In a class, inject `Calisero\LaravelSms\Contracts\SmsClient`
instead of using the facade; the methods are the same.

### Through a notification

```php
use Calisero\LaravelSms\Notification\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 1; // a retried send may deliver the SMS twice

    public function via(object $notifiable): array
    {
        return ['calisero'];
    }

    public function toCalisero(object $notifiable): SmsMessage
    {
        return SmsMessage::create('Your order has shipped.'); // ->from('MyShop')->scheduleAt(now()->addHour())
    }
}
```

The recipient comes from `SmsMessage::to()`, else `routeNotificationForCalisero($notification)` on the notifiable,
else its `phone` attribute. A `NotificationSent` listener gets the API's answer as `$event->response` when
`$event->channel === 'calisero'`.

## 3. OTP / 2FA

Calisero generates a 6-character code (letters and digits, case-insensitive), sends it from the shared "Verify"
sender and validates it. It is valid 5 minutes by default (`expires_in`: 1 to 10), single use, with a limited number of
attempts. The application never sees or stores the code.

```php
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\ValidationException;
use Illuminate\Support\Facades\RateLimiter;

// Send: throttle it, every code is a billed SMS
$key = 'otp:'.$phone;
if (RateLimiter::tooManyAttempts($key, 3)) {
    abort(429);
}
RateLimiter::hit($key, 600);

Calisero::sendVerification([
    'to' => $phone,
    'brand' => 'MyShop',                              // or 'template' => 'Your MyShop code is {code}'
    'expires_in' => 5,
]);

// Check
try {
    $verified = Calisero::checkVerification(['to' => $phone, 'code' => $request->input('code')])
        ->getData()->getStatus() === 'verified';
} catch (ValidationException $e) {
    // wrong or expired code, or too many attempts: $e->getMessage() says which
    $verified = false;
} catch (NotFoundException $e) {
    // no verification for this number: send a new code
    $verified = false;
}
```

`brand` or `template` is required; a template must contain `{code}`, and neither may contain links. Each code counts
towards the daily sending limit.

## 4. Message status

```php
$message = Calisero::getMessageStatus($id)->getData();
$message->getStatus();        // scheduled, sent, delivered, undelivered...
$message->getDeliveredAt();   // null until delivered

Calisero::listMessages(2);    // a page of 50
Calisero::deleteMessage($id); // only while still scheduled, else ForbiddenException (403)
```

`delivered` is the only success. A message that stays `sent` was not confirmed by the carrier, which does not mean it
failed. Prefer the webhook to polling; poll only as a safety net for messages without a final status after a few
hours.

## 5. Delivery reports

1. Set `CALISERO_WEBHOOK_ENABLED=true` and `CALISERO_WEBHOOK_TOKEN`. The package registers `POST /calisero/webhook`
   (route `calisero.webhook`, `api` middleware, token checked from `?token=`) and adds its URL with the token as the
   `callback_url` of every message that sets none.
2. The URL must be public http(s): on `localhost` or a private address the API refuses the message. Keep the webhook
   disabled in local development, or expose the app through a tunnel and set `APP_URL` to it (queue workers and
   commands build the URL from `APP_URL`).
3. Listen with queued listeners: the events are dispatched inside the callback request and Calisero waits 2 seconds.

```php
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordSmsDelivery implements ShouldQueue
{
    public function handle(MessageDelivered|MessageFailed $event): void
    {
        $update = $event->message(); // null when the payload lacks a required field; raw data in $event->messageData
        if ($update === null) {
            return;
        }

        // Idempotent: the same update can arrive twice, and out of order
        Order::where('sms_id', $update->getMessageId())
            ->where('sms_status', '!=', 'delivered')
            ->update(['sms_status' => $update->getStatus(), 'sms_delivered_at' => $update->getDeliveredAt()]);
    }
}
```

| Callback status | Event |
| --- | --- |
| `sent` | `MessageSent` |
| `delivered` | `MessageDelivered` |
| `undelivered` | `MessageFailed` |

`CreditLow` / `CreditCritical` (`$event->remainingBalance`) and `DailyLimitLow` (`$event->dailyLimit`,
`$event->dailyRemaining`, `$event->sentToday`) fire when their thresholds are set. Calisero retries a callback only
after a timeout or a failed connection (at most 5 attempts), never after a non-2xx answer: an exception in a
synchronous listener loses the update.

## 6. Errors and retries

| Exception (`Calisero\Sms\Exceptions`) | Status | What to do |
| --- | --- | --- |
| `UnauthorizedException` | 401 | Wrong or missing API key: fix the configuration |
| `ForbiddenException` | 403 | The key may not do this (e.g. delete a message already sent) |
| `NotFoundException` | 404 | Unknown message or verification ID |
| `ValidationException` | 422 | `getValidationErrors()` lists the fields: invalid number, unapproved sender, wrong OTP code... |
| `DailyLimitExceededException` | 429, `daily_limit_exceeded` | Nothing sent; the limit resets at midnight, Romania time (`getResetsAt()`, `getRetryAfter()`). Catch it before `RateLimitedException` |
| `RateLimitedException` | 429 | More than 240 requests a minute: retry after `getRetryAfter()` seconds |
| `ServerException` | 5xx | Calisero failed: the message may or may not exist |
| `TransportException` | none | Timeout or connection failure: the message may have been sent. Failing on every request with a TLS handshake error: the server's cURL cannot do TLS 1.2, which the API requires |

Every one extends `ApiException`: log `$e->getTraceId()` and look it up in the dashboard under Developers, Debug.

The API takes no idempotency key, so a send retried after a timeout or a 5xx can deliver and bill the SMS twice.
Retry only the refusals that mean nothing was sent:

```php
use Calisero\Sms\Exceptions\DailyLimitExceededException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ServerException;
use Calisero\Sms\Exceptions\TransportException;

// In a queued job (InteractsWithQueue) with enough $tries for the releases: the errors whose outcome is
// unknown are caught, so the queue never retries them
public function handle(): void
{
    try {
        Calisero::sendSms(['to' => $this->phone, 'text' => $this->text]);
    } catch (DailyLimitExceededException $e) {
        $this->release($e->getRetryAfter() ?? 3600);
    } catch (RateLimitedException $e) {
        $this->release($e->getRetryAfter() ?? 5);
    } catch (TransportException|ServerException $e) {
        Log::warning('SMS outcome unknown, not retried', ['trace_id' => $e->getTraceId()]);
        // check Calisero::listMessages() before sending again
    }
}
```

## 7. Testing

```php
use Calisero\LaravelSms\Contracts\SmsClient;
use Calisero\Sms\Dto\CreateMessageResponse;

$this->mock(SmsClient::class)
    ->shouldReceive('sendSms')
    ->once()
    ->withArgs(fn (array $params) => $params['to'] === '+40712345678')
    ->andReturn(CreateMessageResponse::fromArray(['data' => [
        'id' => '9e2574e8-3615-4090-9b5a-0fc812079da8', 'recipient' => '+40712345678', 'body' => 'Hello',
        'parts' => 1, 'created_at' => '2026-01-01T00:00:00.000000Z', 'status' => 'scheduled',
    ]]));
```

The facade and the `calisero` channel resolve the contract, so both use the mock. `Notification::fake()` asserts
notifications without sending; `Event::fake()` plus a `postJson('/calisero/webhook?token=...', $payload)` tests the
webhook (enabled in the test environment). Against the real API, use a sandbox API key: same validation, no real SMS,
no billing, never counted in the daily limit; the message is `delivered` at once and the callbacks still arrive.
`php artisan calisero:sms:test +40712345678` checks a configuration by hand.

## 8. Checklist

- [ ] Numbers in E.164, validated with `Calisero\LaravelSms\Validation\Rule::phoneE164()`.
- [ ] Text length checked: 160 GSM-7 characters a part, 70 with Romanian diacritics or emoji, each part billed;
  writing without diacritics keeps 160-character parts when cost matters (an option, not a rule).
- [ ] `from` only an approved sender (at most 11 alphanumeric characters, case-sensitive), or omitted.
- [ ] `schedule_at` passed as a Carbon instance.
- [ ] Delivery listeners queued and idempotent on message ID + status; webhook disabled locally.
- [ ] No automatic retries of sends; `DailyLimitExceededException` handled apart from `RateLimitedException`.
- [ ] Trace IDs logged; API key only in the environment; sandbox key outside production.
