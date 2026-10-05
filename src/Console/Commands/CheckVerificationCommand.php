<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Console\Concerns\RendersApiOutput;
use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;

class CheckVerificationCommand extends Command
{
    use RendersApiOutput;

    protected $signature = 'calisero:verification:check
                            {to : Phone number}
                            {code : The verification code}';

    protected $description = 'Check a verification code (validation handled by Calisero API)';

    public function handle(SmsClient $client): int
    {
        $to = (string) $this->argument('to');
        $code = (string) $this->argument('code');

        $this->info("Verifying code for {$to}...");

        try {
            $response = $client->checkVerification([
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
        } catch (\Throwable $e) {
            return $this->renderFailure($e, 'Verification not found');
        }
    }
}
