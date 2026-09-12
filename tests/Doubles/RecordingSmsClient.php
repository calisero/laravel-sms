<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Doubles;

use Calisero\LaravelSms\Contracts\SmsClient;
use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\GetMessageResponse;
use Calisero\Sms\Dto\Message;
use Calisero\Sms\Dto\PaginatedMessages;

/**
 * Implements the package contract and records the parameters it receives.
 *
 * Used to assert what a collaborator (such as the notification channel) asks the
 * client to send, without going through the wrapper or the SDK.
 */
class RecordingSmsClient implements SmsClient
{
    /** @var array<string, mixed>|null */
    public ?array $lastParams = null;

    /**
     * @param array<string, mixed> $params
     */
    public function sendSms(array $params): CreateMessageResponse
    {
        $this->lastParams = $params;

        return new CreateMessageResponse(new Message(
            id: 'recorded-id',
            recipient: (string) ($params['to'] ?? ''),
            body: (string) ($params['text'] ?? ''),
            parts: 1,
            createdAt: '2026-01-01T00:00:00.000000Z',
            scheduledAt: isset($params['schedule_at']) ? (string) $params['schedule_at'] : null,
            sentAt: null,
            deliveredAt: null,
            callbackUrl: null,
            status: 'queued',
            sender: isset($params['from']) ? (string) $params['from'] : null,
        ));
    }

    public function getBalance(): float
    {
        throw new \BadMethodCallException('Not expected in these tests.');
    }

    public function getMessageStatus(string $messageId): GetMessageResponse
    {
        throw new \BadMethodCallException('Not expected in these tests.');
    }

    public function listMessages(int $page = 1): PaginatedMessages
    {
        throw new \BadMethodCallException('Not expected in these tests.');
    }

    public function deleteMessage(string $messageId): void
    {
        throw new \BadMethodCallException('Not expected in these tests.');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function sendVerification(array $params): mixed
    {
        throw new \BadMethodCallException('Not expected in these tests.');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function checkVerification(array $params): mixed
    {
        throw new \BadMethodCallException('Not expected in these tests.');
    }
}
