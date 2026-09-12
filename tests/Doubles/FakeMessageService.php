<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Doubles;

use Calisero\Sms\Dto\CreateMessageRequest;
use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\GetMessageResponse;
use Calisero\Sms\Dto\Message;
use Calisero\Sms\Dto\PaginatedMessages;
use Calisero\Sms\Dto\PaginationLinks;
use Calisero\Sms\Dto\PaginationMeta;

/**
 * Stand-in for the SDK's MessageService that records what it was called with.
 */
class FakeMessageService
{
    /** @var array<string, mixed>|null Payload of the last create() call, as sent to the SDK. */
    public ?array $lastPayload = null;

    public ?string $lastRetrievedId = null;

    public ?int $lastListedPage = null;

    public ?string $lastDeletedId = null;

    /** Set to throw from create(), to exercise the error path. */
    public ?\Throwable $createException = null;

    public function create(CreateMessageRequest $request): CreateMessageResponse
    {
        $this->lastPayload = $request->toArray();

        if (null !== $this->createException) {
            throw $this->createException;
        }

        return new CreateMessageResponse($this->message('fake-id', $this->lastPayload));
    }

    public function get(string $messageId): GetMessageResponse
    {
        $this->lastRetrievedId = $messageId;

        return new GetMessageResponse($this->message($messageId, [
            'recipient' => '',
            'body' => '',
        ]));
    }

    public function list(int $page = 1): PaginatedMessages
    {
        $this->lastListedPage = $page;

        return new PaginatedMessages(
            [],
            new PaginationLinks(null, null, null, null),
            new PaginationMeta(currentPage: $page, from: null, path: '/messages', perPage: 25, to: null),
        );
    }

    public function delete(string $messageId): void
    {
        $this->lastDeletedId = $messageId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function message(string $id, array $payload): Message
    {
        return new Message(
            id: $id,
            recipient: (string) ($payload['recipient'] ?? ''),
            body: (string) ($payload['body'] ?? ''),
            parts: 1,
            createdAt: '2026-01-01T00:00:00.000000Z',
            scheduledAt: isset($payload['schedule_at']) ? (string) $payload['schedule_at'] : null,
            sentAt: null,
            deliveredAt: null,
            callbackUrl: isset($payload['callback_url']) ? (string) $payload['callback_url'] : null,
            status: 'queued',
            sender: isset($payload['sender']) ? (string) $payload['sender'] : null,
        );
    }
}
