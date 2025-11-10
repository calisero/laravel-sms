<?php

// Example: Temporarily overriding Calisero config values at runtime.
// Useful inside a queued job or a maintenance script to adjust timeouts.

use Illuminate\Support\Facades\Config;
use Calisero\LaravelSms\Facades\Calisero;

// Original timeout
$originalTimeout = config('calisero.timeout');

// Override for a specific block (e.g., slower network conditions)
Config::set('calisero.timeout', 20.0);
Config::set('calisero.retries', 8);

try {
    Calisero::sendSms([
        'to' => '+1234567890',
        'text' => 'Sending with extended timeout',
        'from' => 'MyApp',
        'idempotencyKey' => 'greeting-' . bin2hex(random_bytes(4)),
    ]);
} finally {
    // Always restore to avoid side effects for subsequent operations
    Config::set('calisero.timeout', $originalTimeout);
}

