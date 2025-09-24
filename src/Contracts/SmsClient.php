<?php

namespace Calisero\LaravelSms\Contracts;

use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\GetMessageResponse;

interface SmsClient
{
    /**
     * Send an SMS message.
     *
     * @param array<string, mixed> $params
     */
    public function sendSms(array $params): CreateMessageResponse;

    /**
     * Get account balance.
     */
    public function getBalance(): float;

    /**
     * Get message status.
     */
    public function getMessageStatus(string $messageId): GetMessageResponse;
}
