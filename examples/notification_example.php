<?php

// Example: Sending an SMS via Laravel Notifications.
// Assumes this code runs inside a Laravel app context.

use Illuminate\Notifications\Notification;
use Calisero\LaravelSms\Notification\SmsMessage;
use Calisero\LaravelSms\Facades\Calisero;

// A notifiable model would normally be an Eloquent model implementing routeNotificationForCalisero
class DemoUser
{
    public function __construct(public string $phone)
    {
    }

    public function routeNotificationForCalisero(): string
    {
        return $this->phone;
    }

    public function notify(Notification $notification): void
    {
        // Very simplified dispatcher for demonstration (Laravel normally does this)
        $via = $notification->via($this);
        if (in_array('calisero', $via, true)) {
            $message = $notification->toCalisero($this);
            Calisero::sendSms([
                'to' => $this->phone,
                'text' => $message->text,
                'from' => $message->from,
                'idempotencyKey' => 'notif-' . uniqid(),
            ]);
        }
    }
}

class OnboardingNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['calisero'];
    }

    public function toCalisero($notifiable): SmsMessage
    {
        return SmsMessage::create('Welcome aboard!')
            ->from('MyApp');
    }
}

$user = new DemoUser('+1234567890');
$user->notify(new OnboardingNotification());

