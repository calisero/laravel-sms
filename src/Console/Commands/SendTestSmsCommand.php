<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;
use Calisero\Sms\Exceptions\ValidationException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ForbiddenException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\ServerException;

class SendTestSmsCommand extends Command
{
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
                            {--validity= : Validity period in minutes}
                            {--schedule-at= : ISO8601 schedule datetime}
                            {--callback-url= : Explicit callback URL}';

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
        if ($from) { $params['from'] = (string) $from; }
        if ($visibleBody) { $params['visible_body'] = (string) $visibleBody; }
        if ($validity !== null) { $params['validity'] = (int) $validity; }
        if ($scheduleAt) { $params['schedule_at'] = (string) $scheduleAt; }
        if ($callbackUrl) { $params['callback_url'] = (string) $callbackUrl; }

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
                ]
            );

            return self::SUCCESS;
        } catch (ValidationException $e) {
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
