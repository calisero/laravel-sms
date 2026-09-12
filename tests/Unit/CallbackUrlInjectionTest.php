<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Tests\Doubles\FakeSdkClient;
use Calisero\LaravelSms\Tests\Support\TestPhones;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * Tests automatic callback_url injection logic.
 */
class CallbackUrlInjectionTest extends TestCase
{
    private FakeSdkClient $sdk;

    private SmsClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sdk = new FakeSdkClient();
        $this->client = new SmsClient($this->sdk);

        config()->set('calisero.webhook.enabled', true);
        config()->set('calisero.webhook.path', 'calisero/webhook');
        config()->set('app.url', 'https://app.test');
        Route::post(config('calisero.webhook.path'), fn () => 'ok')->name('calisero.webhook');
    }

    public function test_callback_url_is_injected_when_enabled_and_absent(): void
    {
        $this->send();

        $this->assertArrayHasKey('callback_url', $this->payload());
        $this->assertSame(route('calisero.webhook'), $this->payload()['callback_url']);
    }

    public function test_no_injection_when_disabled(): void
    {
        config()->set('calisero.webhook.enabled', false);

        $this->send();

        $this->assertArrayNotHasKey('callback_url', $this->payload());
    }

    public function test_no_injection_when_the_path_is_empty(): void
    {
        config()->set('calisero.webhook.path', '');

        $this->send();

        $this->assertArrayNotHasKey('callback_url', $this->payload());
    }

    public function test_explicit_callback_url_is_preserved(): void
    {
        $this->send(['callback_url' => 'https://override.test/callback']);

        $this->assertSame('https://override.test/callback', $this->payload()['callback_url']);
    }

    public function test_callback_url_injection_appends_token_when_configured(): void
    {
        config()->set('calisero.webhook.token', 'abc123');

        $this->send();

        $this->assertSame(route('calisero.webhook') . '?token=abc123', $this->payload()['callback_url']);
    }

    public function test_the_appended_token_is_url_encoded(): void
    {
        config()->set('calisero.webhook.token', 'a b&c=d');

        $this->send();

        $this->assertSame(
            route('calisero.webhook') . '?token=' . rawurlencode('a b&c=d'),
            $this->payload()['callback_url']
        );
    }

    public function test_explicit_callback_url_with_existing_token_not_modified(): void
    {
        config()->set('calisero.webhook.token', 'abc123');

        $explicit = 'https://override.test/callback?token=zzz';
        $this->send(['callback_url' => $explicit]);

        $this->assertSame($explicit, $this->payload()['callback_url']);
    }

    /**
     * With no named route available the URL is assembled from app.url and the
     * configured path instead.
     */
    public function test_it_falls_back_to_the_app_url_when_the_route_is_not_registered(): void
    {
        $this->refreshApplicationWithoutWebhookRoute();

        $this->send();

        $this->assertSame('https://app.test/calisero/webhook', $this->payload()['callback_url']);
    }

    public function test_the_fallback_url_also_carries_the_token(): void
    {
        $this->refreshApplicationWithoutWebhookRoute();
        config()->set('calisero.webhook.token', 'abc123');

        $this->send();

        $this->assertSame('https://app.test/calisero/webhook?token=abc123', $this->payload()['callback_url']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function send(array $extra = []): void
    {
        $this->client->sendSms(array_merge([
            'to' => TestPhones::DEFAULT,
            'text' => 'Test',
        ], $extra));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $payload = $this->sdk->messageService->lastPayload;
        $this->assertNotNull($payload, 'Expected the SDK to have been called.');

        return $payload;
    }

    /**
     * Drop the routes registered in setUp so the named route lookup misses.
     */
    private function refreshApplicationWithoutWebhookRoute(): void
    {
        Route::setRoutes(new \Illuminate\Routing\RouteCollection());
        config()->set('calisero.webhook.enabled', true);
        config()->set('calisero.webhook.path', 'calisero/webhook');
        config()->set('app.url', 'https://app.test');
    }
}
