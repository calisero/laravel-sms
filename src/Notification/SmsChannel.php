<?php

namespace Calisero\LaravelSms\Notification;

use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(
        private SmsClient $client
    ) {
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        $message = $notification->toCalisero($notifiable);

        if (! $message) {
            return;
        }

        $to = $this->getTo($notifiable, $notification, $message);

        if (! $to) {
            return;
        }

        $params = [
            'to' => $to,
            'text' => $message->content,
        ];

        if ($message->from) {
            $params['from'] = $message->from;
        }

        if ($message->scheduleAt) {
            $params['schedule_at'] = $message->scheduleAt;
        }

        if ($message->idempotencyKey) {
            $params['idempotency_key'] = $message->idempotencyKey;
        }

        $this->client->sendSms($params);
    }

    /**
     * Get the phone number for the notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     * @param mixed $message
     * @return string|null
     */
    protected function getTo($notifiable, Notification $notification, $message): ?string
    {
        if ($message->to) {
            return $message->to;
        }

        if (method_exists($notifiable, 'routeNotificationForCalisero')) {
            return $notifiable->routeNotificationForCalisero($notification);
        }

        if (isset($notifiable->phone)) {
            return $notifiable->phone;
        }

        return null;
    }
}
