# AGENTS.md: calisero/laravel-sms

Guidance for AI coding agents. Calisero is a Romanian transactional SMS API (base URL
`https://rest.calisero.ro/api/v1`, `Authorization: Bearer <API key>`). `calisero/laravel-sms` is its official
Laravel package (namespace `Calisero\LaravelSms`), built on
[`calisero/calisero-php`](https://github.com/calisero/calisero-php): Laravel 12 and 13, PHP 8.2 to 8.5.

- **Using this library**: you are adding Calisero to a Laravel application.
- **Working on this library**: you are changing this repository.

Every name below exists in `src/`, `config/` or `routes/`; `README.md` documents each feature in full. Applications
that use Laravel Boost get a condensed version of the first part from `resources/boost/` (see the end of this file).

## Using this library

### Install and configure

```bash
composer require calisero/laravel-sms
php artisan vendor:publish --provider="Calisero\LaravelSms\ServiceProvider" --tag="calisero-config"   # optional
```

The service provider and the `Calisero` facade alias are auto-discovered. The package reads `config/calisero.php`,
filled from these environment variables:

| Variable | Config key | |
| --- | --- | --- |
| `CALISERO_API_KEY` | `calisero.api_key` | Required. Never hard-code it or commit it; one key per environment |
| `CALISERO_ACCOUNT_ID` | `calisero.account_id` | For `getAccount()`, `getBalance()` and `calisero:account` |
| `CALISERO_BASE_URI` | `calisero.base_uri` | `https://rest.calisero.ro/api/v1` by default |
| `CALISERO_TIMEOUT`, `CALISERO_CONNECT_TIMEOUT` | `calisero.timeout`, `calisero.connect_timeout` | Seconds, 10 and 3 by default |
| `CALISERO_WEBHOOK_ENABLED` | `calisero.webhook.enabled` | Registers the webhook route and injects its URL as `callback_url` |
| `CALISERO_WEBHOOK_PATH` | `calisero.webhook.path` | `calisero/webhook` by default |
| `CALISERO_WEBHOOK_TOKEN` | `calisero.webhook.token` | Secret the callbacks must carry as `?token=` |
| `CALISERO_CREDIT_LOW`, `CALISERO_CREDIT_CRITICAL` | `calisero.credit.*_threshold` | Balance thresholds for `CreditLow` / `CreditCritical` |
| `CALISERO_DAILY_LIMIT_LOW` | `calisero.daily_limit.low_threshold` | Messages left today at which `DailyLimitLow` fires |

Read them with `config('calisero.…')` in application code, never `env()`. There is no default sender key: pass
`from` per message. The client is a singleton built on first use; a missing API key throws a `\RuntimeException` then.

### Send an SMS

With the facade, or by injecting `Calisero\LaravelSms\Contracts\SmsClient`:

```php
use Calisero\LaravelSms\Facades\Calisero;

$response = Calisero::sendSms([
    'to' => '+40712345678',                         // E.164
    'text' => 'Your order #1001 has shipped.',      // at most 1600 characters
    // 'from' => 'MyShop',                          // only a sender approved for the account
    // 'schedule_at' => now()->addHour(),           // a Carbon/DateTime is converted to Romania time
    // 'shorten_urls' => true, 'visible_body' => '...', 'validity' => 24, 'callback_url' => '...'
]);

$message = $response->getData();
$message->getId();                                  // keep it: it matches the delivery events
$message->getStatus();
$response->getResponseMeta()->getTraceId();
$response->getResponseMeta()->getDailyRemaining();  // null without a daily limit
```

Optional keys accept snake_case or camelCase; unknown keys are ignored, so check their spelling.

With a notification, use the `calisero` channel:

```php
use Calisero\LaravelSms\Notification\SmsMessage;

public function via(object $notifiable): array
{
    return ['calisero'];
}

public function toCalisero(object $notifiable): SmsMessage
{
    return SmsMessage::create('Your order #1001 has shipped.');
    // ->from('MyShop')->to('+40712345678')->scheduleAt(now()->addHour())->shortenUrls()
    // ->validity(24)->visibleBody('...')->callbackUrl('...')
}
```

The recipient is `SmsMessage::to()`, else the notifiable's `routeNotificationForCalisero($notification)`, else its
`phone` attribute. The channel returns the API's answer, available as `$event->response` in a `NotificationSent`
listener. `SmsMessage::idempotencyKey()` is deprecated and has no effect.

### OTP: send and validate

Calisero generates the code (6 characters, letters and digits, case-insensitive), sends it and checks it: never
generate or store it yourself.

```php
use Calisero\Sms\Exceptions\ValidationException;

Calisero::sendVerification(['to' => $phone, 'brand' => 'MyShop']);
// or 'template' => 'Your MyShop code is {code}'; 'expires_in' => 1 to 10 minutes (5 by default)

try {
    $verification = Calisero::checkVerification(['to' => $phone, 'code' => $input])->getData();
    // $verification->getStatus() === 'verified'
} catch (ValidationException $e) {
    // wrong or expired code, or too many attempts (422): $e->getMessage() says which
}
```

### Read a message's status

```php
Calisero::getMessageStatus($messageId)->getData()->getStatus(); // scheduled, sent, delivered, undelivered...
Calisero::listMessages(2);                                     // PaginatedMessages
Calisero::deleteMessage($messageId);                           // only while still scheduled
Calisero::getAccount();                                        // credit, status, daily limit (needs the account ID)
```

A message that stays `sent` was not confirmed by the carrier, which does not mean it failed. Opt-outs are not on the
facade: use `Calisero\LaravelSms\ClientFactory::make()->optOuts()` (the SDK's `OptOutService`).

### Delivery webhook

With `CALISERO_WEBHOOK_ENABLED=true` the package registers `POST /calisero/webhook` (route `calisero.webhook`, `api`
middleware, plus the token check when `CALISERO_WEBHOOK_TOKEN` is set) and sends its URL, with `?token=`, as the
`callback_url` of every message that sets none. Each callback becomes an event:

| Status | Event |
| --- | --- |
| `sent` | `Calisero\LaravelSms\Events\MessageSent` |
| `delivered` | `Calisero\LaravelSms\Events\MessageDelivered` |
| `undelivered` | `Calisero\LaravelSms\Events\MessageFailed` |

Each carries the raw payload in `$event->messageData` and the typed SDK object in `$event->message()`
(`getMessageId()`, `getStatus()`, `getDeliveredAt()`, `getPrice()`, `getDailyRemaining()`; `null` for a malformed
payload). `CreditLow`, `CreditCritical` and `DailyLimitLow` fire when their thresholds are set. The events are
dispatched inside the callback request, so make the listeners queued:

```php
use Calisero\LaravelSms\Events\MessageDelivered;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordSmsDelivery implements ShouldQueue
{
    public function handle(MessageDelivered $event): void
    {
        $update = $event->message();
        // update your record, idempotently on getMessageId() + getStatus()
    }
}
```

### Errors and the trace ID

The SDK throws `Calisero\Sms\Exceptions\ApiException` subclasses (`getStatusCode()`, `getTraceId()`):

| Exception | When |
| --- | --- |
| `UnauthorizedException` | 401: missing or invalid API key |
| `ForbiddenException` | 403: not allowed, e.g. deleting a message that is no longer scheduled |
| `NotFoundException` | 404: unknown ID |
| `ValidationException` | 422: `getValidationErrors()` maps each field to its messages |
| `DailyLimitExceededException` | 429 with `daily_limit_exceeded` (`getResetsAt()`, `getRetryAfter()`); extends `RateLimitedException`, so catch it first |
| `RateLimitedException` | 429: request rate limit, wait `getRetryAfter()` seconds |
| `ServerException` | 500, 502, 503, 504 |
| `TransportException` | no answer: connection failed or timed out |

The package itself throws `\InvalidArgumentException` (missing `to`/`text`/`code`) and `\RuntimeException` (missing
API key or account ID) before any request. Log `$e->getTraceId()` with every failure and
`getResponseMeta()->getTraceId()` with every send. Every answer carries it as `X-Trace-Id` (an error body also as
`trace_id`); the dashboard's Developers → Debug page shows everything that happened to that request.

### Tests

- Bind a mock to the contract; the facade and the `calisero` channel then use it:

  ```php
  use Calisero\LaravelSms\Contracts\SmsClient;
  use Calisero\Sms\Dto\CreateMessageResponse;

  $this->mock(SmsClient::class)
      ->shouldReceive('sendSms')
      ->once()
      ->andReturn(CreateMessageResponse::fromArray(['data' => [
          'id' => '9e2574e8-3615-4090-9b5a-0fc812079da8', 'recipient' => '+40712345678', 'body' => 'Hello',
          'parts' => 1, 'created_at' => '2026-01-01T00:00:00.000000Z', 'status' => 'scheduled',
      ]]));
  ```

  `Notification::fake()` and `Event::fake()` work as usual; post a payload to the webhook route to test listeners.
- Staging and CI against the real API: use a sandbox API key. It goes through the same validation but sends nothing,
  bills nothing and never counts towards the daily limit; the message is `delivered` at once, callbacks still arrive,
  and a test OTP code shows in the dashboard. `php artisan calisero:sms:test`, `calisero:sms:status`,
  `calisero:account`, `calisero:verification:send` and `calisero:verification:check` try the configuration.

### Rules that matter

- **Phone numbers** in E.164: `+40712345678`, not `0712345678`. Validate input with
  `Calisero\LaravelSms\Validation\Rule::phoneE164()` (and `Rule::senderId()` for a sender).
- **Length and cost**: one part holds 160 GSM-7 characters, or 70 when the text has Romanian diacritics (ă, â, î, ș,
  ț) or emoji (UCS-2). A longer text is split into parts, each billed as one SMS (`getParts()`); the text takes at
  most 1600 characters. When cost matters, a text without diacritics keeps 160-character parts: an option, not a
  rule.
- **Sender ID**: alphanumeric, at most 11 characters, approved for the account and written exactly as approved;
  otherwise 422 and nothing is sent. Leave `from` out for the default sender. It cannot receive replies. Never take it
  from user input. OTP codes go out from the shared "Verify" sender and need no sender of your own.
- **Scheduling**: the API reads `schedule_at` as `Y-m-d H:i:s`, Romania time (Europe/Bucharest). Pass a Carbon
  instance and the package converts it; a string is sent as is and must already be in that format and time zone.
- **Webhooks**: answer any 2xx within 2 seconds, do the work afterwards (queued listeners). Only a timeout or a failed
  connection is retried (at most 5 attempts); a non-2xx answer is not, so a synchronous listener that throws loses the
  update. The same update can arrive twice and out of order: key your processing on `messageId` + `status`, and never
  move a message back from `delivered`. The injected callback URL comes from the route (the request's host, or
  `APP_URL` in queue workers and commands) and must be public http(s): on `localhost` or a private address, the API
  refuses the message. Leave the webhook disabled in local development or use a tunnel.
- **429 has two meanings**: the request rate limit (240 requests a minute; retry after `getRetryAfter()`) and the
  daily sending limit (`DailyLimitExceededException`: nothing sent, retrying the same day will not help; it resets at
  midnight, Romania time; in a job, `$this->release($e->getRetryAfter() ?? 3600)`).
- **No idempotency key**: the package never retries, and you should not retry a send blindly after a timeout or a
  `TransportException`: the SMS may have been sent and billed. Check `listMessages()` first. Do not let the queue
  retry a send on its own (`$tries`, `--tries`, `retryUntil()`): retry only after a refusal that means nothing was
  sent, a `RateLimitedException` or a `DailyLimitExceededException`.
- **OTP**: `brand` or a `template` holding `{code}`, neither with links. Each code is a billed SMS that counts towards
  the daily limit: throttle resends in your application (`RateLimiter`).

## Working on this library

Read `CONTRIBUTING.md` before changing code.

- **Layout**: `src/ServiceProvider.php` (config, commands, channel, routes), `src/SmsClient.php` (the client behind the
  facade and the contract), `src/ClientFactory.php` and `src/SdkClient.php` (SDK wiring), `src/Notification/`,
  `src/Http/` (webhook controller, token middleware, User-Agent), `src/Events/`, `src/Console/Commands/`,
  `src/Validation/`, `config/calisero.php`, `routes/webhooks.php`, `resources/lang/`. Runnable snippets in `examples/`.
- **OpenAPI**: `resources/openApi/api-v1.json` is the copy of the API's contract; field names, types and status codes
  come from it.
- **Checks**:

  ```bash
  composer install
  composer qa         # composer validate, cs:check (PHP-CS-Fixer), stan (PHPStan level 6 on src and tests), test
  composer cs:fix     # fix the code style
  ```

  CI runs PHP 8.2 to 8.5 against Laravel 12 and 13; switch locally with
  `composer update --with="illuminate/support:^13.0" ...` (the full command is in `CONTRIBUTING.md`).
- **Conventions**: stay thin (the HTTP client and DTOs belong to `calisero/calisero-php`) and in step with
  `calisero/calisero-symfony`. Test methods are named `test_<intent>`; doubles live in `tests/Doubles/`, notifiables
  and notifications in `tests/Fixtures/`, helper traits in `tests/Concerns/`; phone numbers come from
  `tests/Support/TestPhones.php` (`+999`, never a live country code). No test reaches the network.
- **Laravel Boost**: `resources/boost/guidelines/core.blade.php` (rendered with Blade by `boost:install`: write
  `@{{ }}` and `@@` for literal braces and at-signs) and `resources/boost/skills/calisero-sms/SKILL.md` (plain
  Markdown with `name` and `description` frontmatter). Update them with any change to the public API, the config keys,
  the events or the commands.
- **Package contents**: there is no `.gitattributes` export-ignore, so everything in the repository, `AGENTS.md` and
  `resources/boost/` included, ships in the Composer archive.
- **Changelog and version**: note changes under `[Unreleased]` in `CHANGELOG.md`; a release sets
  `SmsClient::VERSION`, which a test matches against the latest changelog version.
