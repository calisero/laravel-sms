<?php

namespace Calisero\LaravelSms\Facades;

use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Calisero\Sms\Dto\CreateMessageResponse sendSms(array<string, mixed> $params)
 * @method static float getBalance()
 * @method static \Calisero\Sms\Dto\GetMessageResponse getMessageStatus(string $messageId)
 * @method static \Calisero\Sms\Dto\PaginatedMessages listMessages(int $page = 1)
 * @method static void deleteMessage(string $messageId)
 * @method static mixed sendVerification(array<string, mixed> $params)
 * @method static mixed checkVerification(array<string, mixed> $params)
 *
 * @see \Calisero\LaravelSms\SmsClient
 */
class Calisero extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return SmsClient::class;
    }
}
