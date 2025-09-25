<?php

use Calisero\LaravelSms\Http\Controllers\WebhookController;
use Calisero\LaravelSms\Http\Middleware\ValidateWebhookToken;
use Illuminate\Support\Facades\Route;

$middlewares = config('calisero.webhook.middleware', ['api']);

$token = config('calisero.webhook.token');
if ($token) {
    $middlewares[] = ValidateWebhookToken::class;
}

Route::post(config('calisero.webhook.path'), [WebhookController::class, 'handle'])
    ->middleware($middlewares)
    ->name('calisero.webhook');
