<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Facades\Calisero;
use Illuminate\Console\Command;

class CheckVerificationCommand extends Command
{
    protected $signature = 'calisero:verification:check 
                            {to : The recipient phone number in E.164 format}
                            {code : The verification code to check}';

    protected $description = 'Check/verify a verification code';

    public function handle(): int
    {
        $to = $this->argument('to');
        $code = $this->argument('code');

        $this->info("Checking verification code for {$to}...");

        try {
            $response = Calisero::checkVerification([
                'to' => $to,
                'code' => $code,
            ]);

            if ($response->isValid ?? false) {
                $this->info('✓ Verification code is valid!');
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['To', $response->to ?? 'N/A'],
                        ['Valid', 'Yes'],
                        ['Status', $response->status ?? 'N/A'],
                    ]
                );

                return self::SUCCESS;
            } else {
                $this->error('✗ Verification code is invalid or expired');

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('✗ Failed to check verification code: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
