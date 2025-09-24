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
   CALISERO_WEBHOOK_SECRET=your-webhook-secret   # if using webhooks
   CALISERO_WEBHOOK_PATH=calisero/webhook        # optional override
   CALISERO_CREDIT_LOW=500                       # optional
   CALISERO_CREDIT_CRITICAL=100                  # optional
   ```

## File Overview
| File | Purpose |
|------|---------|
| `send_sms_facade.php` | Minimal Facade based send (synchronous) |
| `notification_example.php` | Using a Notification + custom `toCalisero` method |
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

## 3. Webhook Events
See: `webhook_listeners.php`
- Listens for lifecycle events: `MessageSent`, `MessageDelivered`, `MessageFailed`
- Demonstrates minimal logging handlers

## 4. Credit Monitoring
See: `credit_monitoring_listeners.php`
- Reacts to `CreditLow` and `CreditCritical`
- Good place to trigger Slack/email alerts

## 5. Event Subscriber Pattern
See: `event_subscriber.php`
- Groups all related SMS event handling in one place, auto-registered via service provider

## 6. Dynamic Config Override
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

## Need More?
Open an issue or discussion in the main repository with the scenario you’d like documented.

Happy building! 🚀

