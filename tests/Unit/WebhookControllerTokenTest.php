<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Tests\Concerns\BuildsWebhookPayloads;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class WebhookControllerTokenTest extends TestCase
{
    use BuildsWebhookPayloads;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('calisero.webhook.token', 'test-secret');
    }

    public function test_a_valid_token_allows_processing(): void
    {
        Event::fake();

        $this->postWebhook($this->webhookPayload(['status' => 'delivered']), token: 'test-secret')
            ->assertOk()
            ->assertJson(['ok' => true]);

        Event::assertDispatched(MessageDelivered::class);
    }

    #[DataProvider('rejectedTokens')]
    public function test_it_rejects_a_request_without_the_configured_token(?string $token): void
    {
        Event::fake();

        $this->postWebhook($this->webhookPayload(['status' => 'delivered']), token: $token)
            ->assertStatus(401)
            ->assertJson(['error' => 'Invalid webhook token']);

        Event::assertNotDispatched(MessageDelivered::class);
        Event::assertNotDispatched(MessageFailed::class);
        Event::assertNotDispatched(MessageSent::class);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function rejectedTokens(): iterable
    {
        yield 'a wrong token' => ['wrong'];
        yield 'an empty token' => [''];
        yield 'no token at all' => [null];
        yield 'the token in the wrong case' => ['TEST-SECRET'];
        yield 'a prefix of the token' => ['test-sec'];
        yield 'the token with extra characters' => ['test-secret-extra'];
    }
}
