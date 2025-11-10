<?php

namespace Calisero\LaravelSms\Facades;

use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed sendSms(array $params)
 * @method static mixed getBalance()
 * @method static mixed getMessageStatus(string $messageId)
 * @method static mixed sendVerification(array $params)
 * @method static mixed checkVerification(array $params)
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
