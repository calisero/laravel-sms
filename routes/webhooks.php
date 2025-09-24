<?php

use Calisero\LaravelSms\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post(config('calisero.webhook.path'), [WebhookController::class, 'handle'])
    ->middleware(config('calisero.webhook.middleware'))
    ->name('calisero.webhook');
