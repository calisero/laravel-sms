<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Tests\TestCase;
use Calisero\Sms\Dto\CreateMessageRequest;
use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\Message;
use Illuminate\Support\Facades\Route;

/**
 * Tests automatic callback_url injection logic.
 */
class CallbackUrlInjectionTest extends TestCase
{
    private FakeSdkSmsClient $fakeSdk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeSdk = new FakeSdkSmsClient();
        $this->app->bind(SmsClientContract::class, function () {
            return new SmsClient($this->fakeSdk); // inject fake SDK
        });

        config()->set('calisero.webhook.enabled', true);
        config()->set('calisero.webhook.path', 'calisero/webhook');
        config()->set('app.url', 'https://app.test');
        Route::post(config('calisero.webhook.path'), fn () => 'ok')->name('calisero.webhook');
    }

    public function test_callback_url_is_injected_when_enabled_and_absent(): void
    {
        /** @var SmsClientContract $client */
        $client = $this->app->make(SmsClientContract::class);

        $client->sendSms([
            'to' => '+12345678901',
            'text' => 'Test',
        ]);

        $payload = $this->fakeSdk->messagesService->lastPayload;

        $this->assertArrayHasKey('callback_url', $payload);
        $this->assertSame(route('calisero.webhook'), $payload['callback_url']);
    }

    public function test_no_injection_when_disabled(): void
    {
        config()->set('calisero.webhook.enabled', false);
        /** @var SmsClientContract $client */
        $client = $this->app->make(SmsClientContract::class);

        $client->sendSms([
            'to' => '+12345678902',
            'text' => 'Test2',
        ]);

        $payload = $this->fakeSdk->messagesService->lastPayload;
        $this->assertArrayNotHasKey('callback_url', $payload);
    }

    public function test_explicit_callback_url_is_preserved(): void
    {
        config()->set('calisero.webhook.enabled', true);
        /** @var SmsClientContract $client */
        $client = $this->app->make(SmsClientContract::class);

        $client->sendSms([
            'to' => '+12345678903',
            'text' => 'Test3',
            'callback_url' => 'https://override.test/callback',
        ]);

        $payload = $this->fakeSdk->messagesService->lastPayload;
        $this->assertSame('https://override.test/callback', $payload['callback_url']);
    }
}

// --- Test Doubles ---------------------------------------------------------

class FakeSdkSmsClient
{
    public FakeMessageService $messagesService;

    public function __construct()
    {
        $this->messagesService = new FakeMessageService();
    }

    public function messages(): FakeMessageService
    {
        return $this->messagesService;
    }

    // Unused in these tests
    public function accounts()
    {
    }
}

class FakeMessageService
{
    /** @var array<string,mixed>|null */
    public ?array $lastPayload = null;

    public function create(CreateMessageRequest $request): CreateMessageResponse
    {
        $this->lastPayload = $request->toArray();
        // Return minimal valid response
        $message = new Message(
            id: 'fake-id',
            recipient: $this->lastPayload['recipient'],
            body: $this->lastPayload['body'],
            parts: 1,
            createdAt: now()->toIso8601String(),
            scheduledAt: $this->lastPayload['schedule_at'] ?? null,
            sentAt: null,
            deliveredAt: null,
            callbackUrl: $this->lastPayload['callback_url'] ?? null,
            status: 'queued',
            sender: $this->lastPayload['sender'] ?? null,
        );

        return new CreateMessageResponse($message);
    }
}
