<?php

/**
 * Example: The daily sending limit
 *
 * Every Calisero account has its own daily sending limit. Each real message counts once,
 * verification codes included; test messages never count. The day ends at midnight,
 * Romania time. Once the limit is reached, the API refuses messages until then and the
 * SDK throws DailyLimitExceededException: nothing is sent and nothing is billed.
 */

use Calisero\LaravelSms\Events\DailyLimitLow;
use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\DailyLimitExceededException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * 1. Read the limit (needs CALISERO_ACCOUNT_ID); `php artisan calisero:account` shows it too.
 */
$account = Calisero::getAccount();

if (null === $account->getDailyLimit()) {
    echo "No daily sending limit applies\n";
} else {
    echo "Sent today: {$account->getSentToday()} of {$account->getDailyLimit()}, "
        . "{$account->getDailyRemaining()} left until midnight, Romania time\n";
}

/**
 * 2. Tell a refusal of the daily limit from the request rate limit. Catch
 *    DailyLimitExceededException first: it extends RateLimitedException.
 */
try {
    Calisero::sendSms(['to' => '+40712345678', 'text' => 'Hello!']);
} catch (DailyLimitExceededException $e) {
    Log::warning('Calisero daily limit reached', [
        'limit' => $e->getDailyLimit(),
        'resets_at' => $e->getResetsAt(),   // e.g. 2026-10-06T00:00:00+03:00
        'trace_id' => $e->getTraceId(),
    ]);

    // In a queued job, try again once the limit resets:
    // $this->release($e->getRetryAfter() ?? 3600);
} catch (RateLimitedException $e) {
    // The request rate limit (240 requests a minute): retry in a few seconds
    // $this->release($e->getRetryAfter() ?? 5);
}

/**
 * 3. Be warned before the limit is reached: with CALISERO_DAILY_LIMIT_LOW=100 and the
 *    webhook enabled, DailyLimitLow is dispatched by every delivery callback reporting
 *    100 messages left or fewer.
 */
Event::listen(DailyLimitLow::class, function (DailyLimitLow $event) {
    Log::warning('Calisero daily limit almost reached', [
        'limit' => $event->dailyLimit,
        'remaining' => $event->dailyRemaining,
        'sent_today' => $event->sentToday,
    ]);
    // Notify the team, or ask Calisero to raise the limit
});
