<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\ClientFactory;
use Calisero\LaravelSms\SdkClient;
use Calisero\LaravelSms\Tests\TestCase;
use Calisero\Sms\Http\BaseHttpClient;
use Calisero\Sms\Http\HttpClient;
use Calisero\Sms\Services\AccountService;
use Calisero\Sms\Services\MessageService;
use Calisero\Sms\Services\OptOutService;
use Calisero\Sms\Services\VerificationService;
use Calisero\Sms\SmsClient as SdkSmsClient;
use PHPUnit\Framework\Attributes\DataProvider;

class ClientFactoryTest extends TestCase
{
    public function test_it_builds_an_sdk_client_exposing_every_service(): void
    {
        $client = ClientFactory::make();

        $this->assertInstanceOf(SdkClient::class, $client);
        $this->assertInstanceOf(MessageService::class, $client->messages());
        $this->assertInstanceOf(AccountService::class, $client->accounts());
        $this->assertInstanceOf(VerificationService::class, $client->verifications());
        $this->assertInstanceOf(OptOutService::class, $client->optOuts());
    }

    /**
     * Regression test: the client used to come from the SDK's SmsClient::create(),
     * which fixes its own base URI, so calisero.base_uri did nothing.
     */
    public function test_it_uses_the_configured_base_uri(): void
    {
        config()->set('calisero.base_uri', 'https://staging.example.test/api/v1/');

        $this->assertSame('https://staging.example.test/api/v1', $this->httpClientOf(ClientFactory::make())->baseUri);
    }

    public function test_it_falls_back_to_the_production_base_uri(): void
    {
        config()->set('calisero.base_uri', '');

        $this->assertSame(ClientFactory::DEFAULT_BASE_URI, $this->httpClientOf(ClientFactory::make())->baseUri);
    }

    /**
     * Regression test: calisero.timeout and calisero.connect_timeout did nothing; the
     * SDK's own 30 s and 10 s applied.
     */
    #[DataProvider('timeouts')]
    public function test_it_uses_the_configured_timeouts(mixed $timeout, mixed $connectTimeout, int $expectedTimeout, int $expectedConnect): void
    {
        config()->set('calisero.timeout', $timeout);
        config()->set('calisero.connect_timeout', $connectTimeout);

        $curl = $this->httpClientOf(ClientFactory::make())->curl;

        $this->assertSame($expectedTimeout, $curl->timeout);
        $this->assertSame($expectedConnect, $curl->connectTimeout);
    }

    /**
     * @return iterable<string, array{mixed, mixed, int, int}>
     */
    public static function timeouts(): iterable
    {
        yield 'the package defaults' => [10.0, 3.0, 10, 3];
        yield 'strings from the environment' => ['20', '5', 20, 5];
        yield 'a fraction is rounded up' => [2.5, 0.2, 3, 1];
        yield 'zero would mean no timeout at all' => [0, 0, 1, 1];
        yield 'unset falls back to the defaults' => [null, null, 10, 3];
        yield 'not a number falls back to the defaults' => ['soon', 'later', 10, 3];
    }

    #[DataProvider('unusableApiKeys')]
    public function test_it_refuses_to_build_a_client_without_an_api_key(mixed $apiKey): void
    {
        config()->set('calisero.api_key', $apiKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Calisero API key is not configured (calisero.api_key)');

        ClientFactory::make();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableApiKeys(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    public function test_the_deprecated_factory_still_builds_the_sdks_own_client(): void
    {
        config()->set('calisero.api_key', 'a-real-looking-key');

        $this->assertInstanceOf(SdkSmsClient::class, ClientFactory::create());
    }

    public function test_the_deprecated_factory_still_refuses_a_missing_api_key(): void
    {
        config()->set('calisero.api_key', null);

        $this->expectException(\RuntimeException::class);

        ClientFactory::create();
    }

    /**
     * The SDK keeps the base URI and the timeouts private; read them back.
     *
     * @return object{baseUri: string, curl: object{timeout: int, connectTimeout: int}}
     */
    private function httpClientOf(SdkClient $client): object
    {
        $httpClient = $this->property($client->messages(), MessageService::class, 'httpClient');
        $this->assertInstanceOf(HttpClient::class, $httpClient);

        $curl = $this->property($httpClient, HttpClient::class, 'httpClient');
        $this->assertInstanceOf(BaseHttpClient::class, $curl);

        return (object) [
            'baseUri' => $this->property($httpClient, HttpClient::class, 'baseUri'),
            'curl' => (object) [
                'timeout' => $this->property($curl, BaseHttpClient::class, 'timeout'),
                'connectTimeout' => $this->property($curl, BaseHttpClient::class, 'connectTimeout'),
            ],
        ];
    }

    /**
     * @param class-string $class
     */
    private function property(object $object, string $class, string $name): mixed
    {
        return (new \ReflectionProperty($class, $name))->getValue($object);
    }
}
