<?php

// Example: Centralized event subscriber for SMS related events.
// In a real Laravel app, place this class in app/Listeners or app/Subscribers
// and register it inside EventServiceProvider::$subscribe array.

use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\CreditCritical;
use Illuminate\Support\Facades\Log;

class SmsEventSubscriber
{
    /**
     * Handle message sent.
     */
    public function handleSent(MessageSent $event): void
    {
        Log::info('Subscriber: SMS sent', [
            'id' => $event->messageData['messageId'] ?? null,
        ]);
    }

    /**
     * Handle message delivered.
     */
    public function handleDelivered(MessageDelivered $event): void
    {
        Log::info('Subscriber: SMS delivered', [
            'id' => $event->messageData['messageId'] ?? null,
        ]);
    }

    /**
     * Handle message failed.
     */
    public function handleFailed(MessageFailed $event): void
    {
        Log::warning('Subscriber: SMS failed', [
            'id' => $event->messageData['messageId'] ?? null,
            'status' => $event->messageData['status'] ?? null,
        ]);
    }

    public function handleCreditLow(CreditLow $event): void
    {
        Log::warning('Subscriber: Credit low', [
            'remaining' => $event->remainingBalance,
        ]);
    }

    public function handleCreditCritical(CreditCritical $event): void
    {
        Log::error('Subscriber: Credit CRITICAL', [
            'remaining' => $event->remainingBalance,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function subscribe(): array
    {
        return [
            MessageSent::class => ['handleSent'],
            MessageDelivered::class => ['handleDelivered'],
            MessageFailed::class => ['handleFailed'],
            CreditLow::class => ['handleCreditLow'],
            CreditCritical::class => ['handleCreditCritical'],
        ];
    }
}

// Registration snippet (e.g. in app/Providers/EventServiceProvider.php):
// protected $subscribe = [
//     SmsEventSubscriber::class,
// ];

