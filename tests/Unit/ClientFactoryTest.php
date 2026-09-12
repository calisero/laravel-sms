<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\ClientFactory;
use Calisero\LaravelSms\Tests\TestCase;
use Calisero\Sms\SmsClient as SdkSmsClient;
use PHPUnit\Framework\Attributes\DataProvider;

class ClientFactoryTest extends TestCase
{
    public function test_it_builds_an_sdk_client_from_the_configured_api_key(): void
    {
        config()->set('calisero.api_key', 'a-real-looking-key');

        $this->assertInstanceOf(SdkSmsClient::class, ClientFactory::create());
    }

    #[DataProvider('unusableApiKeys')]
    public function test_it_refuses_to_build_a_client_without_an_api_key(mixed $apiKey): void
    {
        config()->set('calisero.api_key', $apiKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Calisero API key is not configured (calisero.api_key)');

        ClientFactory::create();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableApiKeys(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }
}
