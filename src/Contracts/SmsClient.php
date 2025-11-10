<?php

namespace Calisero\LaravelSms\Contracts;

use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\GetMessageResponse;
use Calisero\Sms\Dto\PaginatedMessages;

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

    /**
     * List messages with pagination.
     */
    public function listMessages(int $page = 1): PaginatedMessages;

    /**
     * Delete a message by ID (only if not yet sent).
     */
    public function deleteMessage(string $messageId): void;

    /**
     * Send a verification code to a phone number.
     *
     * @param array<string, mixed> $params
     */
    public function sendVerification(array $params): mixed;

    /**
     * Check/verify a verification code.
     *
     * @param array<string, mixed> $params
     */
    public function checkVerification(array $params): mixed;
}
