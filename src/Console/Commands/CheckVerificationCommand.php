<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Facades\Calisero;
use Illuminate\Console\Command;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ForbiddenException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\ServerException;

class CheckVerificationCommand extends Command
{
    protected $signature = 'calisero:verification:check
                            {to : Phone number}
                            {code : The verification code}';
    
    protected $description = 'Check a verification code (validation handled by Calisero API)';
    
    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $code = (string) $this->argument('code');
        
        $this->info("Verifying code for {$to}...");
        
        try {
            $response = Calisero::checkVerification([
                'to' => $to,
                'code' => $code,
            ]);
            
            $verification = $response->getData();
            $status = $verification->getStatus();
            $isVerified = 'verified' === $status;
            
            if ($isVerified) {
                $this->info('✓ Code verified');
            }
            
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $verification->getId()],
                    ['Phone', $verification->getPhone()],
                    ['Status', $verification->getStatus()],
                    ['Verified At', $verification->getVerifiedAt() ?? '—'],
                    ['Expires At', $verification->getExpiresAt()],
                    ['Attempts', (string) $verification->getAttempts()],
                    ['Expired', $verification->isExpired() ? 'Yes' : 'No'],
                ]
            );
            
            return $isVerified ? self::SUCCESS : self::FAILURE;
        } catch (ValidationException $e) {
            // 422 from API covers invalid phone, invalid code, exceeded attempts, etc.
            $this->error('✗ API validation error: '.$e->getMessage());
            $errors = $e->getValidationErrors();
            if (!empty($errors)) {
                $rows = [];
                foreach ($errors as $field => $messages) {
                    if (is_array($messages)) { $messages = implode('; ', array_map('strval', $messages)); }
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
            $this->error('✗ Verification not found: '.$e->getMessage());
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
