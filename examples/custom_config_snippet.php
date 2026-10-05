<?php

// Example: A one-off client with a longer timeout.
// Useful inside a queued job or a maintenance script on a slow network.
//
// The client behind the Calisero facade is a singleton, built from config/calisero.php
// the first time it is used: changing the config afterwards does not affect it. Build
// a separate client instead.

use Calisero\LaravelSms\ClientFactory;
use Calisero\LaravelSms\SmsClient;
use Illuminate\Support\Facades\Config;

$originalTimeout = config('calisero.timeout');

Config::set('calisero.timeout', 30); // seconds

try {
    $client = new SmsClient(ClientFactory::make());
} finally {
    // Always restore to avoid side effects for subsequent operations
    Config::set('calisero.timeout', $originalTimeout);
}

$response = $client->sendSms([
    'to' => '+40712345678',
    'text' => 'Sending with extended timeout',
    'from' => 'MyApp', // Only if approved by Calisero
]);

echo "Message ID: {$response->getData()->getId()}\n";
