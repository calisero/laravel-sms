<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Http\UserAgentHttpClient;
use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Support\UserAgent;
use Calisero\LaravelSms\Tests\TestCase;
use Calisero\Sms\Auth\BearerTokenAuthProvider;
use Calisero\Sms\Contracts\HttpClientInterface;
use Calisero\Sms\Http\Factory\HttpFactory;
use Calisero\Sms\Http\HttpClient;
use Calisero\Sms\Http\RequestInterface;
use Calisero\Sms\Http\Response;
use Calisero\Sms\Http\ResponseInterface;

/**
 * Tests the User-Agent header: what it names, and that it reaches the wire in place
 * of the SDK's own.
 */
class UserAgentTest extends TestCase
{
    public function test_it_names_the_package_php_laravel_and_the_platform(): void
    {
        $this->assertSame(
            'Calisero-SMS-Laravel/' . SmsClient::VERSION
            . ' (PHP ' . PHP_VERSION . '; Laravel ' . $this->app->version() . '; ' . strtolower(PHP_OS_FAMILY . ' ' . php_uname('m')) . ')',
            UserAgent::build()
        );
    }

    /**
     * Unlike the Node library's appInfo, nothing in the configuration reaches the
     * header: it always identifies the package alone.
     */
    public function test_the_configuration_cannot_change_it(): void
    {
        $expected = UserAgent::build();

        config()->set('app.name', 'MyShop');
        config()->set('calisero.user_agent', 'Custom/1.0');
        config()->set('calisero.app_info', ['name' => 'MyShop', 'version' => '2.1.0']);

        $this->assertSame($expected, UserAgent::build());
    }

    /**
     * The SDK's HttpClient sets its own User-Agent just before handing the request
     * to its transport: the package's must replace it.
     */
    public function test_it_replaces_the_sdks_user_agent_on_the_wire(): void
    {
        $transport = new class () implements HttpClientInterface {
            public ?RequestInterface $lastRequest = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200, [], '{"data": {}}');
            }
        };

        $httpClient = new HttpClient(
            new UserAgentHttpClient($transport, UserAgent::build()),
            new HttpFactory(),
            new BearerTokenAuthProvider('test-key')
        );
        $httpClient->get('/accounts/acc-123');

        $this->assertNotNull($transport->lastRequest);
        $this->assertSame([UserAgent::build()], $transport->lastRequest->getHeaders()['User-Agent'] ?? null);
    }

    public function test_the_version_matches_the_latest_release_of_the_changelog(): void
    {
        $changelog = (string) file_get_contents(__DIR__ . '/../../CHANGELOG.md');

        $this->assertSame(1, preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $match));
        $this->assertSame($match[1], SmsClient::VERSION);
    }
}
