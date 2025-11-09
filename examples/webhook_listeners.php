<?php

/**
 * Example: Webhook Event Listeners
 *
 * This example shows how to listen for webhook events dispatched by the package.
 * Add these listeners to your EventServiceProvider or use Event::listen() in a service provider.
 */

use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Register listeners in your EventServiceProvider or AppServiceProvider boot() method
 */

// Listen for message sent event
Event::listen(MessageSent::class, function (MessageSent $event) {
    Log::info('Message sent', [
        'message_id' => $event->messageData['messageId'] ?? null,
        'recipient' => $event->messageData['recipient'] ?? null,
        'status' => $event->messageData['status'] ?? null,
    ]);

    // Update database, trigger notifications, etc.
    // DB::table('sms_logs')->where('message_id', $event->messageData['messageId'])->update(['status' => 'sent']);
});

// Listen for message delivered event
Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    Log::info('Message delivered', [
        'message_id' => $event->messageData['messageId'] ?? null,
        'recipient' => $event->messageData['recipient'] ?? null,
        'delivered_at' => $event->messageData['deliveredAt'] ?? null,
    ]);

    // Mark as delivered in database
    // DB::table('sms_logs')->where('message_id', $event->messageData['messageId'])
    //     ->update(['status' => 'delivered', 'delivered_at' => $event->messageData['deliveredAt']]);
});

// Listen for message failed event
Event::listen(MessageFailed::class, function (MessageFailed $event) {
    Log::error('Message delivery failed', [
        'message_id' => $event->messageData['messageId'] ?? null,
        'recipient' => $event->messageData['recipient'] ?? null,
        'status' => $event->messageData['status'] ?? null,
    ]);

    // Handle failure - retry, notify user, etc.
    // DB::table('sms_logs')->where('message_id', $event->messageData['messageId'])
    //     ->update(['status' => 'failed']);
});

echo "✓ Webhook event listeners registered!\n";

