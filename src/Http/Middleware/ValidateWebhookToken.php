<?php

namespace Calisero\LaravelSms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateWebhookToken
{
    /**
     * Validate the configured webhook token from the query string.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = (string) config('calisero.webhook.token');

        if ('' === $expected) {
            return $next($request); // Should not happen if middleware not registered
        }

        $provided = $this->extractProvidedToken($request);

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'invalid_webhook_token',
            ], 401);
        }

        return $next($request);
    }

    private function extractProvidedToken(Request $request): ?string
    {
        $queryToken = $request->query('token');
        if (is_string($queryToken) && '' !== $queryToken) {
            return $queryToken;
        }
        return null;
    }
}
