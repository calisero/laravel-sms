<?php

namespace Calisero\LaravelSms;

use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\Sms\Dto\CreateMessageRequest;
use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\GetMessageResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class SmsClient implements SmsClientContract
{
    /**
     * @param object $client Expected to expose messages() and accounts() accessors similar to the Calisero SDK.
     */
    public function __construct(
        private object $client
    ) {
    }

    /**
     * Send an SMS message.
     *
     * Accepted params keys (user-land):
     *  - to (string, required) -> recipient
     *  - text (string, required) -> body
     *  - from (string, optional) -> sender
     *  - visible_body (string, optional)
     *  - validity (int, optional)
     *  - schedule_at (ISO8601 string, optional)
     *  - callback_url (string, optional)
     *
     * @param array<string, mixed> $params
     * @return \Calisero\Sms\Dto\CreateMessageResponse
     */
    public function sendSms(array $params): CreateMessageResponse
    {
        $recipient = (string) ($params['to'] ?? '');
        $body = (string) ($params['text'] ?? '');

        if ('' === $recipient || '' === $body) {
            throw new \InvalidArgumentException('Both "to" and "text" parameters are required');
        }

        // Accept camelCase or snake_case for optional parameters.
        $visibleBody = $params['visible_body'] ?? $params['visibleBody'] ?? null;
        $validity = $params['validity'] ?? null;
        $scheduleAt = $params['schedule_at'] ?? $params['scheduleAt'] ?? null;
        $callbackUrl = $params['callback_url'] ?? $params['callbackUrl'] ?? null;
        // Auto-inject callback URL if enabled and none explicitly provided
        if (null === $callbackUrl && $this->shouldInjectCallback()) {
            $callbackUrl = $this->buildCallbackUrl();
        }
        $sender = $params['from'] ?? null;

        $request = new CreateMessageRequest(
            recipient: $recipient,
            body: $body,
            visibleBody: null !== $visibleBody ? (string) $visibleBody : null,
            validity: null !== $validity ? (int) $validity : null,
            scheduleAt: null !== $scheduleAt ? (string) $scheduleAt : null,
            callbackUrl: null !== $callbackUrl ? (string) $callbackUrl : null,
            sender: null !== $sender ? (string) $sender : null,
        );

        try {
            $response = $this->client->messages()->create($request);
            $message = $response->getData();

            Log::channel(config('calisero.logging.channel', 'default'))
                ->info('SMS created successfully', [
                    'to' => $message->getRecipient(),
                    'from' => $message->getSender(),
                    'message_id' => $message->getId(),
                    'status' => $message->getStatus(),
                ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(config('calisero.logging.channel', 'default'))
                ->error('Failed to create SMS', [
                    'to' => $recipient,
                    'from' => $sender,
                    'error' => $e->getMessage(),
                ]);

            throw $e;
        }
    }

    /**
     * Get account balance (credit) for configured account.
     * Requires `calisero.account_id` config or CALISERO_ACCOUNT_ID env.
     *
     * @return float
     */
    public function getBalance(): float
    {
        $accountId = config('calisero.account_id');
        if (! $accountId) {
            throw new \RuntimeException('Account ID not configured (calisero.account_id)');
        }

        $accountResponse = $this->client->accounts()->get((string) $accountId);

        return $accountResponse->getData()->getCredit();
    }

    /**
     * Get message status by ID.
     *
     * @return \Calisero\Sms\Dto\GetMessageResponse
     */
    public function getMessageStatus(string $messageId): GetMessageResponse
    {
        return $this->client->messages()->get($messageId);
    }

    private function shouldInjectCallback(): bool
    {
        return (bool) config('calisero.webhook.enabled') && (bool) config('calisero.webhook.path');
    }

    private function buildCallbackUrl(): string
    {
        try {
            if (Route::has('calisero.webhook')) {
                $url = (string) route('calisero.webhook');

                return $this->appendTokenIfNeeded($url);
            }
        } catch (\Throwable) {
            // fall back below
        }

        $path = ltrim((string) config('calisero.webhook.path'), '/');
        $base = rtrim((string) config('app.url'), '/');
        $url = '' !== $base ? $base . '/' . $path : '/' . $path;

        return $this->appendTokenIfNeeded($url);
    }

    private function appendTokenIfNeeded(string $url): string
    {
        $token = config('calisero.webhook.token');
        if (null === $token || '' === $token) {
            return $url;
        }
        // Do not append if already present
        if (str_contains($url, 'token=')) {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'token=' . rawurlencode((string) $token);
    }
}
