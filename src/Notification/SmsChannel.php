<?php

namespace Calisero\LaravelSms\Notification;

use Calisero\LaravelSms\Contracts\SmsClient;
use Calisero\Sms\Dto\CreateMessageResponse;
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
     * Returns the API's answer, which Laravel hands to NotificationSent listeners
     * as $event->response; null when nothing was sent.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     * @return \Calisero\Sms\Dto\CreateMessageResponse|null
     */
    public function send($notifiable, Notification $notification): ?CreateMessageResponse
    {
        $message = $notification->toCalisero($notifiable);

        if (! $message) {
            return null;
        }

        $to = $this->getTo($notifiable, $notification, $message);

        if (! $to) {
            return null;
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

        if (null !== $message->shortenUrls) {
            $params['shorten_urls'] = $message->shortenUrls;
        }

        if ($message->visibleBody) {
            $params['visible_body'] = $message->visibleBody;
        }

        if (null !== $message->validity) {
            $params['validity'] = $message->validity;
        }

        if ($message->callbackUrl) {
            $params['callback_url'] = $message->callbackUrl;
        }

        return $this->client->sendSms($params);
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
