<?php

namespace Calisero\LaravelSms\Tests;

use Calisero\LaravelSms\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Calisero' => \Calisero\LaravelSms\Facades\Calisero::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('calisero.api_key', 'test-api-key');
        $app['config']->set('calisero.webhook.enabled', true); // ensure route loads for webhook tests
    }
}
