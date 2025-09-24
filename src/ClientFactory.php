<?php

namespace Calisero\LaravelSms;

use Calisero\Sms\SmsClient;
use Illuminate\Support\Facades\Config;

class ClientFactory
{
    /**
     * Create a configured Calisero SmsClient instance.
     */
    public static function create(): SmsClient
    {
        $apiKey = Config::get('calisero.api_key');

        if (! $apiKey) {
            throw new \RuntimeException('Calisero API key is not configured (calisero.api_key)');
        }

        return SmsClient::create($apiKey);
    }
}
