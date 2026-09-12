<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Tests\Doubles\FakeSdkClient;
use Calisero\LaravelSms\Tests\Support\TestPhones;
use Calisero\LaravelSms\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the wrapper around the upstream SDK client: argument mapping, parameter
 * aliases, delegation and error handling.
 */
class SmsClientTest extends TestCase
{
    private FakeSdkClient $sdk;

    private SmsClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep the webhook out of the way so it cannot inject a callback_url.
        config()->set('calisero.webhook.enabled', false);

        $this->sdk = new FakeSdkClient(credit: 123.45);
        $this->client = new SmsClient($this->sdk);
    }

    public function test_it_maps_the_required_parameters(): void
    {
        $this->client->sendSms(['to' => TestPhones::DEFAULT, 'text' => 'Hello']);

        $payload = $this->sdk->messageService->lastPayload;
        $this->assertNotNull($payload);
        $this->assertSame(TestPhones::DEFAULT, $payload['recipient']);
        $this->assertSame('Hello', $payload['body']);
    }

    public function test_it_returns_the_sdk_response(): void
    {
        $response = $this->client->sendSms(['to' => TestPhones::DEFAULT, 'text' => 'Hello']);

        $this->assertSame(TestPhones::DEFAULT, $response->getData()->getRecipient());
        $this->assertSame('Hello', $response->getData()->getBody());
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('missingRequiredParameters')]
    public function test_it_rejects_missing_required_parameters(array $params): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Both "to" and "text" parameters are required');

        $this->client->sendSms($params);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function missingRequiredParameters(): iterable
    {
        yield 'no recipient' => [['text' => 'Hello']];
        yield 'empty recipient' => [['to' => '', 'text' => 'Hello']];
        yield 'no body' => [['to' => TestPhones::DEFAULT]];
        yield 'empty body' => [['to' => TestPhones::DEFAULT, 'text' => '']];
        yield 'neither' => [[]];
    }

    /**
     * The wrapper accepts both snake_case (Laravel style) and camelCase (SDK style)
     * for every optional parameter.
     *
     * @param array<string, mixed> $params
     */
    #[DataProvider('optionalParameterAliases')]
    public function test_it_accepts_both_naming_styles_for_optional_parameters(
        array $params,
        string $sdkKey,
        mixed $expected
    ): void {
        $this->client->sendSms(array_merge(['to' => TestPhones::DEFAULT, 'text' => 'Hello'], $params));

        $payload = $this->sdk->messageService->lastPayload;
        $this->assertNotNull($payload);
        $this->assertSame($expected, $payload[$sdkKey]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, mixed}>
     */
    public static function optionalParameterAliases(): iterable
    {
        yield 'visible_body' => [['visible_body' => 'Shown'], 'visible_body', 'Shown'];
        yield 'visibleBody' => [['visibleBody' => 'Shown'], 'visible_body', 'Shown'];
        yield 'schedule_at' => [['schedule_at' => '2026-01-01T10:00:00Z'], 'schedule_at', '2026-01-01T10:00:00Z'];
        yield 'scheduleAt' => [['scheduleAt' => '2026-01-01T10:00:00Z'], 'schedule_at', '2026-01-01T10:00:00Z'];
        yield 'callback_url' => [['callback_url' => 'https://example.test/cb'], 'callback_url', 'https://example.test/cb'];
        yield 'callbackUrl' => [['callbackUrl' => 'https://example.test/cb'], 'callback_url', 'https://example.test/cb'];
    }

    public function test_it_maps_the_sender_and_casts_the_validity(): void
    {
        $this->client->sendSms([
            'to' => TestPhones::DEFAULT,
            'text' => 'Hello',
            'from' => 'CALISERO',
            'validity' => '48',
        ]);

        $payload = $this->sdk->messageService->lastPayload;
        $this->assertNotNull($payload);
        $this->assertSame('CALISERO', $payload['sender']);
        $this->assertSame(48, $payload['validity']);
    }

    public function test_it_rethrows_sdk_failures(): void
    {
        $this->sdk->messageService->createException = new \RuntimeException('SDK exploded');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SDK exploded');

        $this->client->sendSms(['to' => TestPhones::DEFAULT, 'text' => 'Hello']);
    }

    public function test_it_returns_the_account_credit_as_the_balance(): void
    {
        config()->set('calisero.account_id', 'acc-123');

        $this->assertSame(123.45, $this->client->getBalance());
        $this->assertSame('acc-123', $this->sdk->accountService->lastRetrievedId);
    }

    public function test_it_refuses_to_read_the_balance_without_an_account_id(): void
    {
        config()->set('calisero.account_id', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account ID not configured (calisero.account_id)');

        $this->client->getBalance();
    }

    public function test_it_delegates_the_message_status_lookup(): void
    {
        $response = $this->client->getMessageStatus('msg-42');

        $this->assertSame('msg-42', $this->sdk->messageService->lastRetrievedId);
        $this->assertSame('msg-42', $response->getData()->getId());
    }

    public function test_it_delegates_the_message_listing_with_the_requested_page(): void
    {
        $this->client->listMessages(3);

        $this->assertSame(3, $this->sdk->messageService->lastListedPage);
    }

    public function test_it_lists_the_first_page_by_default(): void
    {
        $this->client->listMessages();

        $this->assertSame(1, $this->sdk->messageService->lastListedPage);
    }

    /**
     * Regression test: deleteMessage() used to call a deleteMessage() method on the SDK
     * client, which does not exist there and raised an Error at runtime. Deletion goes
     * through the message service, like every other message operation.
     */
    public function test_it_deletes_through_the_sdk_message_service(): void
    {
        $this->client->deleteMessage('msg-99');

        $this->assertSame('msg-99', $this->sdk->messageService->lastDeletedId);
    }
}
