<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Facades\Calisero;
use Illuminate\Console\Command;

class SendVerificationCommand extends Command
{
    protected $signature = 'calisero:verification:send 
                            {to : The recipient phone number in E.164 format}
                            {--brand= : Brand name (required if no template)}
                            {--template= : Message template containing {code} placeholder}
                            {--expires-in= : Code expiration time in minutes (1-10)}';

    protected $description = 'Send a verification code to a phone number via SMS';

    public function handle(): int
    {
        $to = $this->argument('to');
        $brand = $this->option('brand');
        $template = $this->option('template');
        $expiresIn = $this->option('expires-in');

        // Validate that either brand or template is provided
        if (! $brand && ! $template) {
            $this->error('✗ Either --brand or --template must be provided');

            return self::FAILURE;
        }

        $this->info("Sending verification code to {$to}...");

        try {
            $params = [
                'to' => $to,
            ];

            if ($brand) {
                $params['brand'] = $brand;
            }

            if ($template) {
                $params['template'] = $template;
            }

            if ($expiresIn) {
                $params['expires_in'] = (int) $expiresIn;
            }

            $response = Calisero::sendVerification($params);

            $this->info('✓ Verification code sent successfully!');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Phone', $response->phone ?? 'N/A'],
                    ['Status', $response->status ?? 'N/A'],
                    ['Expires At', $response->expires_at ?? 'N/A'],
                    ['Brand', $response->brand ?? 'N/A'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to send verification code: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
