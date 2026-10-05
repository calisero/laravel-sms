<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Console\Concerns\RendersApiOutput;
use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;

class SendTestSmsCommand extends Command
{
    use RendersApiOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calisero:sms:test
                            {to : Recipient phone number}
                            {--from= : Optional sender ID}
                            {--text=Hello from Calisero : Message text}
                            {--visible-body= : Visible body override}
                            {--validity= : Validity period in hours}
                            {--schedule-at= : Schedule datetime, Y-m-d H:i:s in Romania time}
                            {--callback-url= : Explicit callback URL}
                            {--shorten-urls : Shorten the links of the text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test SMS message (validation handled by Calisero API)';

    /**
     * Execute the console command.
     */
    public function handle(SmsClient $client): int
    {
        $to = (string) $this->argument('to');
        $from = $this->option('from');
        $text = (string) $this->option('text');
        $visibleBody = $this->option('visible-body');
        $validity = $this->option('validity');
        $scheduleAt = $this->option('schedule-at');
        $callbackUrl = $this->option('callback-url');

        $this->info('Creating SMS message...');

        $params = [
            'to' => $to,
            'text' => $text,
        ];
        if ($from) {
            $params['from'] = (string) $from;
        }
        if ($visibleBody) {
            $params['visible_body'] = (string) $visibleBody;
        }
        if ($validity !== null) {
            $params['validity'] = (int) $validity;
        }
        if ($scheduleAt) {
            $params['schedule_at'] = (string) $scheduleAt;
        }
        if ($callbackUrl) {
            $params['callback_url'] = (string) $callbackUrl;
        }
        if ($this->option('shorten-urls')) {
            $params['shorten_urls'] = true;
        }

        try {
            $response = $client->sendSms($params);
            $message = $response->getData();

            $this->info('✓ SMS created');
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
                    ...$this->responseMetaRows($response->getResponseMeta()),
                ]
            );
            $this->renderShortenedUrls($message->getShortenedUrls());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->renderFailure($e);
        }
    }
}
