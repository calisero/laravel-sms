<?php

// Example: Credit monitoring listeners.
// Register these in a service provider or dedicated EventsServiceProvider.

use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\CreditCritical;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(CreditLow::class, function (CreditLow $e) {
    Log::warning('Calisero credit low threshold reached', [
        'remaining' => $e->remainingBalance,
    ]);
    // You might dispatch a notification job or Slack alert here.
});

Event::listen(CreditCritical::class, function (CreditCritical $e) {
    Log::error('Calisero credit CRITICAL threshold reached', [
        'remaining' => $e->remainingBalance,
    ]);
    // Trigger escalated alert (PagerDuty, SMS to ops, etc.)
});

