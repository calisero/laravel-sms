<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Concerns;

use Calisero\LaravelSms\Tests\Support\TestPhones;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Helpers for posting Calisero webhook callbacks at the configured path.
 */
trait BuildsWebhookPayloads
{
    /**
     * A complete webhook payload, with only the fields under test overridden.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function webhookPayload(array $overrides = []): array
    {
        return array_merge([
            'price' => 0.0378,
            'sender' => 'CALISERO',
            'sentAt' => '2026-01-01T11:59:44.000000Z',
            'status' => 'sent',
            'messageId' => 'uuid-123',
            'recipient' => TestPhones::DEFAULT,
            'scheduleAt' => '2026-01-01T11:59:42.000000Z',
            'deliveredAt' => null,
            'remainingBalance' => 1000.00,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return TestResponse<Response>
     */
    protected function postWebhook(array $payload, array $headers = [], ?string $token = null): TestResponse
    {
        $path = (string) config('calisero.webhook.path');

        if (null !== $token) {
            $path .= '?token=' . $token;
        }

        return $this->postJson($path, $payload, $headers);
    }
}
