<?php

namespace Calisero\LaravelSms\Console\Commands;

use Illuminate\Console\Command;

class VerifyWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calisero:webhook:verify {signature} {--payload=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify webhook signature for development purposes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $signature = (string) $this->argument('signature');
        $payload = (string) ($this->option('payload') ?? '{}');
        $secret = config('calisero.webhook.secret');

        if (! $secret) {
            $this->error('Webhook secret not configured. Set CALISERO_WEBHOOK_SECRET in your environment.');

            return Command::FAILURE;
        }

        $expectedSignature = hash_hmac('sha256', $payload, (string) $secret);

        if (hash_equals($expectedSignature, $signature)) {
            $this->info('✓ Webhook signature is valid');
            $this->line("Expected: {$expectedSignature}");
            $this->line("Received: {$signature}");

            return Command::SUCCESS;
        } else {
            $this->error('✗ Webhook signature is invalid');
            $this->line("Expected: {$expectedSignature}");
            $this->line("Received: {$signature}");

            return Command::FAILURE;
        }
    }
}
