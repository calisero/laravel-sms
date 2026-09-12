<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Console\Commands\CheckVerificationCommand;
use Calisero\LaravelSms\Console\Commands\SendTestSmsCommand;
use Calisero\LaravelSms\Console\Commands\SendVerificationCommand;
use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\LaravelSms\Notification\SmsChannel;
use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Route;

/**
 * Verifies the package wiring against the host framework: container bindings,
 * notification channel driver, artisan commands, config merge and routes.
 */
class ServiceProviderTest extends TestCase
{
    public function test_it_binds_the_sms_client_contract_as_a_singleton(): void
    {
        $first = $this->app->make(SmsClientContract::class);
        $second = $this->app->make(SmsClientContract::class);

        $this->assertInstanceOf(SmsClient::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_it_registers_the_calisero_container_alias(): void
    {
        $this->assertSame(
            $this->app->make(SmsClientContract::class),
            $this->app->make('calisero')
        );
    }

    public function test_it_merges_the_package_configuration(): void
    {
        $this->assertSame('test-api-key', config('calisero.api_key'));
        $this->assertIsArray(config('calisero.webhook'));
        $this->assertArrayHasKey('path', config('calisero.webhook'));
    }

    public function test_it_extends_the_notification_channel_manager(): void
    {
        $channel = $this->app->make(ChannelManager::class)->driver('calisero');

        $this->assertInstanceOf(SmsChannel::class, $channel);
    }

    public function test_it_registers_the_artisan_commands(): void
    {
        $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();

        $this->assertInstanceOf(SendTestSmsCommand::class, $commands['calisero:sms:test'] ?? null);
        $this->assertInstanceOf(SendVerificationCommand::class, $commands['calisero:verification:send'] ?? null);
        $this->assertInstanceOf(CheckVerificationCommand::class, $commands['calisero:verification:check'] ?? null);
    }

    public function test_it_registers_the_webhook_route_when_enabled(): void
    {
        $route = Route::getRoutes()->getByName('calisero.webhook');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
    }

    public function test_it_loads_the_package_translations(): void
    {
        $this->assertNotSame(
            'calisero::validation.phone_e164',
            trans('calisero::validation.phone_e164')
        );
    }
}
