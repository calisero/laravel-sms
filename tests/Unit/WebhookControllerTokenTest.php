<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class WebhookControllerTokenTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('calisero.webhook.token', 'test-secret');
    }

    private function postPayload(array $payload, array $headers = [])
    {
        return $this->postJson(config('calisero.webhook.path'), $payload, $headers);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'price' => 0.0378,
            'sender' => 'CALISERO',
            'sentAt' => now()->toIso8601String(),
            'status' => 'sent',
            'messageId' => 'uuid-token-123',
            'recipient' => '+40123456789',
            'scheduleAt' => now()->toIso8601String(),
            'deliveredAt' => null,
            'remainingBalance' => 1000.00,
        ], $overrides);
    }

    public function test_valid_token_allows_processing(): void
    {
        Event::fake();

        $payload = $this->basePayload(['status' => 'delivered']);

        $this->postJson(config('calisero.webhook.path').'?token=test-secret', $payload)
            ->assertOk()->assertJson(['ok' => true]);

        Event::assertDispatched(MessageDelivered::class);
    }

    public function test_invalid_token_rejected(): void
    {
        Event::fake();

        $payload = $this->basePayload(['status' => 'failed']);

        $this->postJson(config('calisero.webhook.path').'?token=wrong', $payload)
            ->assertStatus(401)
            ->assertJson(['error' => 'Invalid webhook token']);

        Event::assertNotDispatched(MessageFailed::class);
        Event::assertNotDispatched(MessageSent::class);
        Event::assertNotDispatched(MessageDelivered::class);
    }

    public function test_missing_token_rejected(): void
    {
        Event::fake();
        $payload = $this->basePayload(['status' => 'sent']);

        $this->postPayload($payload)
            ->assertStatus(401)
            ->assertJson(['error' => 'Invalid webhook token']);

        Event::assertNotDispatched(MessageSent::class);
        Event::assertNotDispatched(MessageFailed::class);
        Event::assertNotDispatched(MessageDelivered::class);
    }
}
