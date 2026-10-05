<?php

/**
 * Example: Send an SMS with shortened URLs
 *
 * Calisero replaces the http:// and https:// links of the text with short ones before
 * sending, and counts how many times each is opened. The answer also tells how many
 * messages the account can still send today.
 */

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\DailyLimitExceededException;

try {
    $response = Calisero::sendSms([
        'to' => '+40712345678',
        'text' => 'Your order shipped. Track it: https://shop.example.com/orders/123/tracking',
        'shorten_urls' => true,
        'schedule_at' => now()->addMinutes(30), // a date-time is converted to Romania time
    ]);

    $message = $response->getData();
    echo "✓ SMS created: {$message->getId()}\n";

    foreach ($message->getShortenedUrls() as $link) {
        echo "  {$link->getOriginalLink()}\n  -> {$link->getShortenedLink()}\n";
    }

    // What the answer's headers say (null when the account has no daily limit)
    $meta = $response->getResponseMeta();
    echo 'Messages left today: ' . ($meta->getDailyRemaining() ?? 'no limit') . "\n";
    echo "Trace ID: {$meta->getTraceId()}\n";
} catch (DailyLimitExceededException $e) {
    echo "✗ Daily sending limit reached; it resets at {$e->getResetsAt()}\n";
} catch (ApiException $e) {
    echo "✗ Failed to send SMS: {$e->getMessage()} (trace ID: {$e->getTraceId()})\n";
}

/**
 * Later: the click statistics of the links of a message.
 */
function showClicks(string $messageId): void
{
    $message = Calisero::getMessageStatus($messageId)->getData();

    foreach ($message->getShortenedUrls() as $link) {
        echo "{$link->getShortenedLink()}: {$link->getClickCount()} clicks, last " . ($link->getLastClick() ?? 'never') . "\n";
    }
}

// In a notification:
//
// public function toCalisero($notifiable): SmsMessage
// {
//     return SmsMessage::create('Track your order: https://shop.example.com/orders/123/tracking')
//         ->shortenUrls();
// }
