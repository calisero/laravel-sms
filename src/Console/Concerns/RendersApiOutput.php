<?php

namespace Calisero\LaravelSms\Console\Concerns;

use Calisero\Sms\Dto\ResponseMeta;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\DailyLimitExceededException;
use Calisero\Sms\Exceptions\ForbiddenException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ServerException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ValidationException;

/**
 * Prints the outcome of a Calisero call the same way in every command.
 *
 * @mixin \Illuminate\Console\Command
 */
trait RendersApiOutput
{
    /**
     * Print the failure and return the command's exit code.
     */
    protected function renderFailure(\Throwable $e, string $notFound = 'Resource not found'): int
    {
        if ($e instanceof DailyLimitExceededException) {
            // Before RateLimitedException, which it extends
            $this->error('✗ Daily sending limit reached: '.$e->getMessage());
            $this->line('Daily limit: '.($e->getDailyLimit() ?? 'unknown').', resets at: '.($e->getResetsAt() ?? 'midnight, Romania time'));
        } elseif ($e instanceof ValidationException) {
            // 422 from API covers invalid phone, invalid code, exceeded attempts, etc.
            $this->error('✗ API validation error: '.$e->getMessage());
            $this->renderValidationErrors($e->getValidationErrors());
        } elseif ($e instanceof RateLimitedException) {
            $this->error('✗ Rate limited: '.$e->getMessage());
            $this->line('Retry after: '.($e->getRetryAfter() ?? 'unknown').'s');
        } elseif ($e instanceof UnauthorizedException || $e instanceof ForbiddenException) {
            $this->error('✗ Auth/permission error: '.$e->getMessage());
        } elseif ($e instanceof NotFoundException) {
            $this->error("✗ {$notFound}: ".$e->getMessage());
        } elseif ($e instanceof ServerException) {
            $this->error('✗ Server error: '.$e->getMessage());
        } elseif ($e instanceof ApiException) {
            $this->error('✗ API error: '.$e->getMessage().' (status: '.($e->getStatusCode() ?? 'unknown').')');
        } else {
            $this->error('✗ Unexpected failure: '.$e->getMessage());
        }

        if ($e instanceof ApiException && null !== $e->getTraceId()) {
            $this->line('Trace ID: '.$e->getTraceId().' (quote it to Calisero support)');
        }

        return self::FAILURE;
    }

    /**
     * Rows for what the answer's headers said about the account's daily limit and
     * the request; a value the answer did not carry is left out.
     *
     * @return list<array{string, string}>
     */
    protected function responseMetaRows(ResponseMeta $meta): array
    {
        $rows = [];

        if (null !== $meta->getDailyLimit()) {
            $rows[] = ['Daily Remaining', $meta->getDailyRemaining().' of '.$meta->getDailyLimit()];
        }

        if (null !== $meta->getTraceId()) {
            $rows[] = ['Trace ID', $meta->getTraceId()];
        }

        return $rows;
    }

    /**
     * A table of the links Calisero shortened in a message, with their clicks.
     *
     * @param \Calisero\Sms\Dto\ShortenedLink[] $links
     */
    protected function renderShortenedUrls(array $links): void
    {
        if ([] === $links) {
            return;
        }

        $this->table(
            ['Original Link', 'Shortened Link', 'Clicks', 'Last Click'],
            array_map(fn ($link) => [
                $link->getOriginalLink(),
                $link->getShortenedLink(),
                (string) $link->getClickCount(),
                $link->getLastClick() ?? '—',
            ], $links)
        );
    }

    /**
     * @param array<array-key, mixed> $errors
     */
    private function renderValidationErrors(array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        $rows = [];
        foreach ($errors as $field => $messages) {
            if (is_array($messages)) {
                $messages = implode('; ', array_map('strval', $messages));
            }
            $rows[] = [(string) $field, (string) $messages];
        }
        $this->table(['Field', 'Errors'], $rows);
    }
}
