<?php

namespace Calisero\LaravelSms;

use Calisero\Sms\Auth\BearerTokenAuthProvider;
use Calisero\Sms\Http\BaseHttpClient;
use Calisero\Sms\Http\Factory\HttpFactory;
use Calisero\Sms\Http\HttpClient;
use Calisero\Sms\SmsClient;
use Illuminate\Support\Facades\Config;

class ClientFactory
{
    public const DEFAULT_BASE_URI = 'https://rest.calisero.ro/api/v1';

    /**
     * Create an SDK client on the configured API key, base URI and timeouts.
     */
    public static function make(): SdkClient
    {
        $baseUri = (string) Config::get('calisero.base_uri');

        return new SdkClient(new HttpClient(
            new BaseHttpClient(
                self::seconds(Config::get('calisero.timeout'), 10),
                self::seconds(Config::get('calisero.connect_timeout'), 3)
            ),
            new HttpFactory(),
            new BearerTokenAuthProvider(self::apiKey()),
            '' !== $baseUri ? $baseUri : self::DEFAULT_BASE_URI
        ));
    }

    /**
     * Create a Calisero SmsClient instance.
     *
     * @deprecated since 1.3.0, use make(): the SDK's SmsClient::create() fixes the
     *             base URI and a 30 s timeout, so this ignores calisero.base_uri,
     *             calisero.timeout and calisero.connect_timeout.
     */
    public static function create(): SmsClient
    {
        return SmsClient::create(self::apiKey());
    }

    private static function apiKey(): string
    {
        $apiKey = Config::get('calisero.api_key');

        if (! $apiKey) {
            throw new \RuntimeException('Calisero API key is not configured (calisero.api_key)');
        }

        return (string) $apiKey;
    }

    /**
     * cURL takes whole seconds: round a fraction up, and never down to 0, which
     * cURL reads as "no timeout".
     */
    private static function seconds(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max(1, (int) ceil((float) $value));
    }
}
