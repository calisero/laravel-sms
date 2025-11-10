<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;
use Calisero\Sms\Exceptions\ApiException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\UnauthorizedException;
use Calisero\Sms\Exceptions\ForbiddenException;
use Calisero\Sms\Exceptions\ServerException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ValidationException;

class StatusSmsCommand extends Command
{
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

            // Quick status summary line
            $this->line('Status: '. $message->getStatus());

            return self::SUCCESS;
        } catch (NotFoundException $e) {
            $this->error('✗ Message not found: '.$e->getMessage());
            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->error('✗ Validation error: '.$e->getMessage());
            return self::FAILURE;
        } catch (RateLimitedException $e) {
            $this->error('✗ Rate limited: '.$e->getMessage());
            $this->line('Retry after seconds: '.($e->getRetryAfter() ?? 'unknown'));
            return self::FAILURE;
        } catch (UnauthorizedException|ForbiddenException $e) {
            $this->error('✗ Auth/permission error: '.$e->getMessage());
            return self::FAILURE;
        } catch (ServerException $e) {
            $this->error('✗ Server error: '.$e->getMessage());
            return self::FAILURE;
        } catch (ApiException $e) {
            $this->error('✗ API error: '.$e->getMessage().' (status: '.$e->getStatusCode().', request: '.$e->getRequestId().')');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('✗ Failed to retrieve message: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
