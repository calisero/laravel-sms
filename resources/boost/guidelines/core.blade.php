# Calisero SMS (calisero/laravel-sms)

- This application sends SMS through Calisero, a Romanian transactional SMS API, with `calisero/laravel-sms`. Use the package; do not call `https://rest.calisero.ro/api/v1` with the HTTP client or write a client of your own.
- IMPORTANT: Activate `calisero-sms` when sending SMS or OTP codes, handling delivery reports, or testing code that does.
- Configuration lives in `config/calisero.php`, filled from `CALISERO_API_KEY` (required), `CALISERO_ACCOUNT_ID`, `CALISERO_WEBHOOK_ENABLED`, `CALISERO_WEBHOOK_TOKEN` and the other `CALISERO_*` variables. Read `config('calisero.api_key')`, never `env()` outside config files, and never hard-code or commit the API key.
- Send with the facade `Calisero\LaravelSms\Facades\Calisero` (`Calisero::sendSms(['to' => $phone, 'text' => $text])`), the injectable contract `Calisero\LaravelSms\Contracts\SmsClient`, or a notification whose `via()` returns `['calisero']` and whose `toCalisero()` returns `Calisero\LaravelSms\Notification\SmsMessage`.
- OTP: `Calisero::sendVerification(['to' => $phone, 'brand' => 'MyApp'])`, then `Calisero::checkVerification(['to' => $phone, 'code' => $code])`. Calisero generates, sends and checks the code: never generate or store it. A wrong, expired or exhausted code throws `Calisero\Sms\Exceptions\ValidationException`.
- Delivery reports: with `CALISERO_WEBHOOK_ENABLED=true` the package registers `POST /calisero/webhook` and dispatches `MessageSent`, `MessageDelivered` and `MessageFailed` (namespace `Calisero\LaravelSms\Events`). Listeners must implement `ShouldQueue`: Calisero waits only 2 seconds for the answer.
- Artisan commands: `calisero:sms:test`, `calisero:sms:status`, `calisero:account`, `calisero:verification:send`, `calisero:verification:check`.

## Calisero Pitfalls

- Phone numbers must be E.164 (`+40712345678`, not `0712345678`); validate input with `Calisero\LaravelSms\Validation\Rule::phoneE164()`.
- One SMS part holds 160 GSM-7 characters, or 70 when the text has Romanian diacritics or emoji; each part is billed as one SMS, and a text takes at most 1600 characters. Writing without diacritics keeps 160-character parts when cost matters: an option, not a rule.
- `from` must be a sender approved for the account, written exactly as approved, or the API answers 422; leave it out for the default sender. It is alphanumeric, at most 11 characters, and cannot receive replies. Never take it from user input.
- Pass `schedule_at` as a Carbon instance; a string must already be `Y-m-d H:i:s` in Romania time.
- The callback URL must be public http(s); on `localhost` or a private address the API refuses the message, so keep the webhook disabled locally. Calisero retries a callback only after a timeout or a failed connection, never after a non-2xx answer; updates can repeat or arrive out of order, so process them idempotently on message ID + status.
- A 429 is either the request rate limit (240 requests a minute: `RateLimitedException`, retry after `getRetryAfter()`) or the account's daily sending limit (`DailyLimitExceededException`, catch it first: nothing was sent, and retrying before midnight, Romania time, will not help).
- The API takes no idempotency key: never retry a send blindly after a timeout or `TransportException`, it may have been sent and billed; check `Calisero::listMessages()` first, and keep queue retries off SMS sends.
- Log the trace ID: `$e->getTraceId()` on an `ApiException`, `$response->getResponseMeta()->getTraceId()` on a success. The Calisero dashboard (Developers, Debug) shows everything about that request.
- In tests, mock `Calisero\LaravelSms\Contracts\SmsClient` (`$this->mock(...)`) or use `Notification::fake()`; against the real API use a sandbox API key, which sends and bills nothing.
