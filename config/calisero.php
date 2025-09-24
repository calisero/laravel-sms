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

    'account_id' => env('CALISERO_ACCOUNT_ID'), // added for balance queries

    'timeout' => env('CALISERO_TIMEOUT', 10.0),

    'connect_timeout' => env('CALISERO_CONNECT_TIMEOUT', 3.0),

    'retries' => env('CALISERO_RETRIES', 5),

    'retry_backoff_ms' => env('CALISERO_RETRY_BACKOFF_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook handling for delivery status updates.
    | Set the webhook secret in your environment file.
    |
    */

    'webhook' => [
        'secret' => env('CALISERO_WEBHOOK_SECRET'),
        'path' => env('CALISERO_WEBHOOK_PATH', 'calisero/webhook'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for SMS operations.
    |
    */

    'logging' => [
        'channel' => env('CALISERO_LOG_CHANNEL', 'default'),
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
];
