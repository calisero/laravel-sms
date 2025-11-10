<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\ForbiddenException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ServerException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ValidationException;
use Illuminate\Console\Command;

class SendVerificationCommand extends Command
{
    protected $signature = 'calisero:verification:send
                            {to : The recipient phone number}
                            {--brand= : Optional brand name}
                            {--template= : Optional message template containing {code}}
                            {--expires-in= : Optional code expiration time in minutes}';

    protected $description = 'Send a verification code (all input validated by Calisero API)';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $brand = $this->option('brand');
        $template = $this->option('template');
        $expiresIn = $this->option('expires-in');

        $this->info("Sending verification code to {$to}...");

        try {
            $params = [ 'to' => $to ];
            if ($brand) {
                $params['brand'] = $brand;
            }
            if ($template) {
                $params['template'] = $template;
            }
            if ($expiresIn !== null) {
                $params['expires_in'] = (int) $expiresIn;
            }

            $response = Calisero::sendVerification($params);
            $verification = $response->getData();

            $this->info('✓ Verification code sent');
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $verification->getId()],
                    ['Phone', $verification->getPhone()],
                    ['Status', $verification->getStatus()],
                    ['Brand', $verification->getBrand() ?? '—'],
                    ['Template', $verification->getTemplate() ?? '—'],
                    ['Created At', $verification->getCreatedAt()],
                    ['Expires At', $verification->getExpiresAt()],
                    ['Attempts', (string) $verification->getAttempts()],
                    ['Expired', $verification->isExpired() ? 'Yes' : 'No'],
                ]
            );

            return self::SUCCESS;
        } catch (ValidationException $e) {
            // API returns explicit validation errors (422) including phone/template/etc problems
            $this->error('✗ API validation error: '.$e->getMessage());
            $errors = $e->getValidationErrors();
            if (! empty($errors)) {
                $rows = [];
                foreach ($errors as $field => $messages) {
                    if (is_array($messages)) {
                        $messages = implode('; ', array_map('strval', $messages));
                    }
                    $rows[] = [$field, (string) $messages];
                }
                $this->table(['Field', 'Errors'], $rows);
            }

            return self::FAILURE;
        } catch (RateLimitedException $e) {
            $this->error('✗ Rate limited: '.$e->getMessage());
            $this->line('Retry after: '.($e->getRetryAfter() ?? 'unknown').'s');

            return self::FAILURE;
        } catch (UnauthorizedException|ForbiddenException $e) {
            $this->error('✗ Auth/permission error: '.$e->getMessage());

            return self::FAILURE;
        } catch (NotFoundException $e) {
            $this->error('✗ Resource not found: '.$e->getMessage());

            return self::FAILURE;
        } catch (ServerException $e) {
            $this->error('✗ Server error: '.$e->getMessage());

            return self::FAILURE;
        } catch (ApiException $e) {
            $this->error('✗ API error: '.$e->getMessage().' (status: '.$e->getStatusCode().', request: '.$e->getRequestId().')');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('✗ Unexpected failure: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
