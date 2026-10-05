<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Console\Concerns\RendersApiOutput;
use Calisero\LaravelSms\Contracts\SmsClient;
use Calisero\Sms\Dto\CreateVerificationResponse;
use Illuminate\Console\Command;

class SendVerificationCommand extends Command
{
    use RendersApiOutput;

    protected $signature = 'calisero:verification:send
                            {to : The recipient phone number}
                            {--brand= : Optional brand name}
                            {--template= : Optional message template containing {code}}
                            {--expires-in= : Optional code expiration time in minutes}';

    protected $description = 'Send a verification code (all input validated by Calisero API)';

    public function handle(SmsClient $client): int
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

            $response = $client->sendVerification($params);
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
                    // A custom implementation of the contract may return another type
                    ...($response instanceof CreateVerificationResponse ? $this->responseMetaRows($response->getResponseMeta()) : []),
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->renderFailure($e);
        }
    }
}
