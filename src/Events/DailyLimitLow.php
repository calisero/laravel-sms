<?php

namespace Calisero\LaravelSms\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched for a delivery webhook reporting that the account can send no more
 * than calisero.daily_limit.low_threshold messages today. The limit resets at
 * midnight, Romania time; past it, sending throws DailyLimitExceededException.
 */
class DailyLimitLow
{
    use Dispatchable;
    use SerializesModels;
    use InteractsWithSockets;

    public function __construct(
        public int $dailyLimit,
        public int $dailyRemaining,
        public ?int $sentToday = null
    ) {
    }
}
