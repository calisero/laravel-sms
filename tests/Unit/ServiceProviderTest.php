<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Console\Commands\AccountCommand;
use Calisero\LaravelSms\Console\Commands\CheckVerificationCommand;
use Calisero\LaravelSms\Console\Commands\SendTestSmsCommand;
use Calisero\LaravelSms\Console\Commands\SendVerificationCommand;
use Calisero\LaravelSms\Console\Commands\StatusSmsCommand;
use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\LaravelSms\Notification\SmsChannel;
use Calisero\LaravelSms\SdkClient;
use Calisero\LaravelSms\ServiceProvider;
use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

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

    /**
     * The client is built by ClientFactory::make(), so it honours calisero.base_uri
     * and the timeouts.
     */
    public function test_it_wraps_the_sdk_client_built_from_the_configuration(): void
    {
        $client = $this->app->make(SmsClientContract::class);

        $this->assertInstanceOf(SdkClient::class, (new \ReflectionProperty(SmsClient::class, 'client'))->getValue($client));
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
        $this->assertInstanceOf(StatusSmsCommand::class, $commands['calisero:sms:status'] ?? null);
        $this->assertInstanceOf(AccountCommand::class, $commands['calisero:account'] ?? null);
    }

    public function test_it_registers_the_webhook_route_when_enabled(): void
    {
        $route = Route::getRoutes()->getByName('calisero.webhook');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
    }

    /**
     * env() turns "true" into true but leaves "1" a string; the route used to need
     * true itself, while the callback_url injection took any truthy value, so
     * CALISERO_WEBHOOK_ENABLED=1 sent Calisero a callback URL nothing answered.
     */
    #[DataProvider('webhookFlags')]
    public function test_it_reads_the_webhook_flag_as_a_boolean(mixed $enabled, bool $registered): void
    {
        Route::setRoutes(new RouteCollection());
        config()->set('calisero.webhook.enabled', $enabled);

        $provider = $this->app->getProvider(ServiceProvider::class);
        $this->assertInstanceOf(ServiceProvider::class, $provider);
        $provider->boot();
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame($registered, null !== Route::getRoutes()->getByName('calisero.webhook'));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function webhookFlags(): iterable
    {
        yield 'true' => [true, true];
        yield 'the string "1"' => ['1', true];
        yield 'the string "true"' => ['true', true];
        yield 'false' => [false, false];
        yield 'the string "false"' => ['false', false];
        yield 'the string "0"' => ['0', false];
        yield 'null' => [null, false];
    }

    public function test_it_loads_the_package_translations(): void
    {
        $this->assertNotSame(
            'calisero::validation.phone_e164',
            trans('calisero::validation.phone_e164')
        );
    }
}
