<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Calisero API Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used to connect to the Calisero SMS API.
    | You can find your API key in your Calisero dashboard.
    |
    */

    'base_uri' => env('CALISERO_BASE_URI', 'https://rest.calisero.ro/api/v1'),

    'api_key' => env('CALISERO_API_KEY'),

    'account_id' => env('CALISERO_ACCOUNT_ID'), // needed by getAccount(), getBalance() and calisero:account

    // Seconds; cURL takes whole seconds, so a fraction is rounded up.
    'timeout' => env('CALISERO_TIMEOUT', 10.0),

    'connect_timeout' => env('CALISERO_CONNECT_TIMEOUT', 3.0),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Enable webhooks for delivery status & balance events. If enabled, the
    | route at the configured path is registered and its URL is sent as the
    | callback_url of every message that does not set its own. Set a token
    | to require it as the ?token= query parameter of every callback.
    |
    */

    'webhook' => [
        'path' => env('CALISERO_WEBHOOK_PATH', 'calisero/webhook'),
        'middleware' => ['api'],
        'enabled' => env('CALISERO_WEBHOOK_ENABLED', false),
        'token' => env('CALISERO_WEBHOOK_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Monitoring (Optional)
    |--------------------------------------------------------------------------
    |
    | Configure optional balance thresholds to emit events when your remaining
    | account credit becomes low or critical. Leave null (default) to disable.
    |
    | CALISERO_CREDIT_LOW=500        (example)
    | CALISERO_CREDIT_CRITICAL=100   (example)
    |
    */

    'credit' => [
        'low_threshold' => env('CALISERO_CREDIT_LOW'), // float|string|null
        'critical_threshold' => env('CALISERO_CREDIT_CRITICAL'), // float|string|null
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Sending Limit Monitoring (Optional)
    |--------------------------------------------------------------------------
    |
    | Emit DailyLimitLow when a delivery webhook reports that the account can
    | send this many messages or fewer before its daily limit, which resets at
    | midnight, Romania time. Leave null (default) to disable.
    |
    | CALISERO_DAILY_LIMIT_LOW=100   (example)
    |
    */

    'daily_limit' => [
        'low_threshold' => env('CALISERO_DAILY_LIMIT_LOW'), // int|string|null
    ],
];
