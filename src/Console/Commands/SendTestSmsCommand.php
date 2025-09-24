<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;

class SendTestSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calisero:sms:test {to} {--from=} {--text=Hello from Calisero}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test SMS message using Calisero';

    /**
     * Execute the console command.
     */
    public function handle(SmsClient $client): int
    {
        $to = (string) $this->argument('to');
        $from = $this->option('from');
        $text = (string) $this->option('text');

        $params = [
            'to' => $to,
            'text' => $text,
        ];

        if ($from) {
            $params['from'] = (string) $from;
        }

        try {
            $response = $client->sendSms($params); // CreateMessageResponse
            $message = $response->getData();

            $this->info('SMS sent successfully!');
            $this->line('Message ID: ' . $message->getId());
            $this->line("To: {$to}");
            $this->line("Text: {$text}");

            if ($from) {
                $this->line('From: ' . (string) $from);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send SMS: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
