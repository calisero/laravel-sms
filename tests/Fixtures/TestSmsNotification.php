<?php

namespace Calisero\LaravelSms\Tests\Fixtures;

use Calisero\LaravelSms\Notification\SmsMessage;
use Illuminate\Notifications\Notification;

class TestSmsNotification extends Notification
{
    public function __construct(
        private string $message = 'Test SMS notification'
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['calisero'];
    }

    /**
     * Build the SMS message.
     */
    public function toCalisero($notifiable): SmsMessage
    {
        return SmsMessage::create($this->message)
            ->from('TEST');
    }
}
