<?php

namespace Calisero\LaravelSms\Events\Concerns;

use Calisero\Sms\Dto\DeliveryWebhookMessage;

/**
 * Typed access to the delivery webhook payload of a message event.
 */
trait ReadsDeliveryPayload
{
    /**
     * The payload as the SDK reads it, with typed getters for every field (the
     * daily limit's included); null when the payload lacks a required field,
     * whose raw values stay in $messageData.
     */
    public function message(): ?DeliveryWebhookMessage
    {
        try {
            return DeliveryWebhookMessage::fromArray($this->messageData);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
