<?php

namespace Calisero\LaravelSms\Http;

use Calisero\Sms\Contracts\HttpClientInterface;
use Calisero\Sms\Http\RequestInterface;
use Calisero\Sms\Http\ResponseInterface;

/**
 * Sends every request with the package's User-Agent.
 *
 * The SDK's HttpClient sets its own (Calisero-SMS-PHP/<version>) just before it
 * hands the request to its transport; wrapping the transport is the last point
 * where the header can still be replaced.
 *
 * @internal
 */
class UserAgentHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private string $userAgent
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request->withHeader('User-Agent', $this->userAgent));
    }
}
