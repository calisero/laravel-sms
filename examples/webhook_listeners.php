<?php

/**
 * Example: Webhook Event Listeners
 *
 * This example shows how to listen for webhook events dispatched by the package.
 * Add these listeners to your EventServiceProvider or use Event::listen() in a service provider.
 *
 * Every event carries the raw payload in $event->messageData; $event->message() reads it
 * with the SDK's DeliveryWebhookMessage, whose getters are typed (null when the payload
 * lacks a required field).
 */

use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Register listeners in your EventServiceProvider or AppServiceProvider boot() method
 */

// Listen for message sent event (status "sent")
Event::listen(MessageSent::class, function (MessageSent $event) {
    Log::info('Message sent', [
        'message_id' => $event->messageData['messageId'] ?? null,
        'recipient' => $event->messageData['recipient'] ?? null,
        'status' => $event->messageData['status'] ?? null,
    ]);

    // Update database, trigger notifications, etc.
    // DB::table('sms_logs')->where('message_id', $event->messageData['messageId'])->update(['status' => 'sent']);
});

// Listen for message delivered event (status "delivered")
Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    $message = $event->message();

    Log::info('Message delivered', [
        'message_id' => $message?->getMessageId(),
        'recipient' => $message?->getRecipient(),
        'delivered_at' => $message?->getDeliveredAt(),
        'price' => $message?->getPrice(),
        'daily_remaining' => $message?->getDailyRemaining(), // null when the account has no daily limit
    ]);

    // Mark as delivered in database
    // DB::table('sms_logs')->where('message_id', $message?->getMessageId())
    //     ->update(['status' => 'delivered', 'delivered_at' => $message?->getDeliveredAt()]);
});

// Listen for message failed event (status "undelivered")
Event::listen(MessageFailed::class, function (MessageFailed $event) {
    Log::error('Message delivery failed', [
        'message_id' => $event->messageData['messageId'] ?? null,
        'recipient' => $event->messageData['recipient'] ?? null,
        'status' => $event->messageData['status'] ?? null,
    ]);

    // Handle failure - retry, notify user, etc.
    // DB::table('sms_logs')->where('message_id', $event->messageData['messageId'])
    //     ->update(['status' => 'undelivered']);
});

echo "✓ Webhook event listeners registered!\n";
