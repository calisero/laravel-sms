<?php

namespace Calisero\LaravelSms;

use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\LaravelSms\Support\ScheduleAt;
use Calisero\LaravelSms\Support\WebhookConfig;
use Calisero\Sms\Dto\Account;
use Calisero\Sms\Dto\CreateMessageRequest;
use Calisero\Sms\Dto\CreateMessageResponse;
use Calisero\Sms\Dto\CreateVerificationRequest;
use Calisero\Sms\Dto\CreateVerificationResponse;
use Calisero\Sms\Dto\GetMessageResponse;
use Calisero\Sms\Dto\GetVerificationResponse;
use Calisero\Sms\Dto\PaginatedMessages;
use Calisero\Sms\Dto\VerificationCheckRequest;
use Illuminate\Support\Facades\Route;

class SmsClient implements SmsClientContract
{
    /**
     * The package's version, sent in the User-Agent header; keep it in step with CHANGELOG.md.
     */
    public const VERSION = '1.3.1';

    /**
     * @param object $client Exposes messages(), accounts() and verifications() like the
     *                       Calisero SDK: SdkClient, or \Calisero\Sms\SmsClient.
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
     *  - validity (int, optional) -> hours
     *  - schedule_at (DateTimeInterface, or a 'Y-m-d H:i:s' string in Romania time, optional)
     *  - callback_url (string, optional)
     *  - shorten_urls (bool, optional)
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
        $shortenUrls = $params['shorten_urls'] ?? $params['shortenUrls'] ?? null;

        $request = new CreateMessageRequest(
            recipient: $recipient,
            body: $body,
            visibleBody: null !== $visibleBody ? (string) $visibleBody : null,
            validity: null !== $validity ? (int) $validity : null,
            scheduleAt: null !== $scheduleAt ? $this->scheduleAt($scheduleAt) : null,
            callbackUrl: null !== $callbackUrl ? (string) $callbackUrl : null,
            sender: null !== $sender ? (string) $sender : null,
            shortenUrls: null !== $shortenUrls ? filter_var($shortenUrls, FILTER_VALIDATE_BOOLEAN) : null,
        );

        return $this->client->messages()->create($request);
    }

    /**
     * Get the configured account: credit, status and daily sending limit.
     * Requires `calisero.account_id` config or CALISERO_ACCOUNT_ID env.
     */
    public function getAccount(): Account
    {
        $accountId = config('calisero.account_id');
        if (! $accountId) {
            throw new \RuntimeException('Account ID not configured (calisero.account_id)');
        }

        return $this->client->accounts()->get((string) $accountId)->getData();
    }

    /**
     * Get account balance (credit) for configured account.
     * Requires `calisero.account_id` config or CALISERO_ACCOUNT_ID env.
     *
     * @return float
     */
    public function getBalance(): float
    {
        return $this->getAccount()->getCredit();
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

    /**
     * List messages with pagination.
     */
    public function listMessages(int $page = 1): PaginatedMessages
    {
        return $this->client->messages()->list($page);
    }

    /**
     * Delete a message by ID (only if not yet sent).
     */
    public function deleteMessage(string $messageId): void
    {
        $this->client->messages()->delete($messageId);
    }

    /**
     * Send a verification code to a phone number.
     *
     * Accepted params keys: to (or phone, required), brand, template, expires_in (or expiresIn).
     *
     * @param array<string, mixed> $params
     * @throws \Exception
     */
    public function sendVerification(array $params): CreateVerificationResponse
    {
        $expiresIn = $params['expires_in'] ?? $params['expiresIn'] ?? null;
        $brand = $params['brand'] ?? null;
        $template = $params['template'] ?? null;

        $request = new CreateVerificationRequest(
            phone: $this->verificationPhone($params),
            brand: null !== $brand ? (string) $brand : null,
            template: null !== $template ? (string) $template : null,
            expiresIn: null !== $expiresIn ? (int) $expiresIn : null
        );

        return $this->client->verifications()->create($request);
    }

    /**
     * Check/verify a verification code.
     *
     * Accepted params keys: to (or phone, required), code (required).
     *
     * @param array<string, mixed> $params
     * @throws \Exception
     */
    public function checkVerification(array $params): GetVerificationResponse
    {
        $phone = $this->verificationPhone($params);
        $code = (string) ($params['code'] ?? '');

        if ('' === $code) {
            throw new \InvalidArgumentException('The "code" parameter is required');
        }

        $request = new VerificationCheckRequest(
            phone: $phone,
            code: $code
        );

        return $this->client->verifications()->validate($request);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function verificationPhone(array $params): string
    {
        $phone = (string) ($params['to'] ?? $params['phone'] ?? '');

        if ('' === $phone) {
            throw new \InvalidArgumentException('The "to" (or "phone") parameter is required');
        }

        return $phone;
    }

    private function scheduleAt(mixed $scheduleAt): string
    {
        return ScheduleAt::format($scheduleAt instanceof \DateTimeInterface ? $scheduleAt : (string) $scheduleAt);
    }

    private function shouldInjectCallback(): bool
    {
        return WebhookConfig::enabled() && (bool) config('calisero.webhook.path');
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
