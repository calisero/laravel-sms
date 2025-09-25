<?php

namespace Calisero\LaravelSms;

use Calisero\LaravelSms\Console\Commands\SendTestSmsCommand;
use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\LaravelSms\Notification\SmsChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/calisero.php',
            'calisero'
        );

        $this->app->singleton(SmsClientContract::class, function ($app) {
            return new SmsClient(ClientFactory::create());
        });

        $this->app->alias(SmsClientContract::class, 'calisero');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/calisero.php' => config_path('calisero.php'),
        ], 'calisero-config');

        // Load package translations
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'calisero');

        // Publish translations for customization
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/calisero'),
        ], 'calisero-translations');

        $this->registerCommands();
        $this->registerNotificationChannel();
        $this->registerWebhookRoutes();
    }

    /**
     * Register artisan commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendTestSmsCommand::class,
            ]);
        }
    }

    /**
     * Register SMS notification channel.
     */
    private function registerNotificationChannel(): void
    {
        $this->app->make(ChannelManager::class)->extend('calisero', function ($app) {
            return new SmsChannel($app->make(SmsClientContract::class));
        });
    }

    /**
     * Register webhook routes if enabled.
     */
    private function registerWebhookRoutes(): void
    {
        if (config('calisero.webhook.enabled') === true) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        }
    }
}
