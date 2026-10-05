<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Console\Concerns\RendersApiOutput;
use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;

class StatusSmsCommand extends Command
{
    use RendersApiOutput;

    protected $signature = 'calisero:sms:status {id : The SMS message ID}';

    protected $description = 'Fetch and display the status and details of an SMS message';

    public function handle(SmsClient $client): int
    {
        $id = (string) $this->argument('id');
        if ('' === $id) {
            $this->error('✗ Message id must not be empty');

            return self::FAILURE;
        }

        $this->info("Retrieving SMS status for ID: {$id}...");

        try {
            $response = $client->getMessageStatus($id); // GetMessageResponse
            $message = $response->getData();

            $this->info('✓ SMS retrieved successfully');
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $message->getId()],
                    ['Recipient', $message->getRecipient()],
                    ['Sender', $message->getSender() ?? '—'],
                    ['Body', $message->getBody()],
                    ['Parts', (string) $message->getParts()],
                    ['Status', $message->getStatus()],
                    ['Created At', $message->getCreatedAt()],
                    ['Scheduled At', $message->getScheduledAt() ?? '—'],
                    ['Sent At', $message->getSentAt() ?? '—'],
                    ['Delivered At', $message->getDeliveredAt() ?? '—'],
                    ['Callback URL', $message->getCallbackUrl() ?? '—'],
                ]
            );
            $this->renderShortenedUrls($message->getShortenedUrls());

            // Quick status summary line
            $this->line('Status: '. $message->getStatus());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->renderFailure($e, 'Message not found');
        }
    }
}
