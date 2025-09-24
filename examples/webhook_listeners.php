<?php

// Example: Registering webhook lifecycle listeners.
// Put this in a service provider boot() or an events service provider.

use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(MessageSent::class, function (MessageSent $e) {
    Log::info('SMS sent (provider accepted)', [
        'id' => $e->messageData['messageId'] ?? null,
        'to' => $e->messageData['recipient'] ?? null,
    ]);
});

Event::listen(MessageDelivered::class, function (MessageDelivered $e) {
    Log::info('SMS delivered', [
        'id' => $e->messageData['messageId'] ?? null,
        'to' => $e->messageData['recipient'] ?? null,
        'deliveredAt' => $e->messageData['deliveredAt'] ?? null,
    ]);
});

Event::listen(MessageFailed::class, function (MessageFailed $e) {
    Log::warning('SMS delivery failed', [
        'id' => $e->messageData['messageId'] ?? null,
        'to' => $e->messageData['recipient'] ?? null,
        'status' => $e->messageData['status'] ?? null,
    ]);
});

