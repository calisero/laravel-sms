<?php

namespace Calisero\LaravelSms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        $secret = config('calisero.webhook.secret');

        if (! $secret) {
            abort(500, 'Webhook secret not configured');
        }

        if (! $signature) {
            abort(401, 'Missing webhook signature');
        }

        $expectedSignature = hash_hmac('sha256', $payload, (string) $secret);

        if (! hash_equals($expectedSignature, (string) $signature)) {
            abort(401, 'Invalid webhook signature');
        }

        return $next($request);
    }
}
