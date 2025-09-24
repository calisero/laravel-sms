<?php

namespace Calisero\LaravelSms\Validation;

use Calisero\LaravelSms\Validation\Rules\PhoneE164;
use Calisero\LaravelSms\Validation\Rules\SenderId;

class Rule
{
    /**
     * Create a phone number E.164 validation rule.
     *
     * @return PhoneE164
     */
    public static function phoneE164(): PhoneE164
    {
        return new PhoneE164();
    }

    /**
     * Create a sender ID validation rule.
     *
     * @return SenderId
     */
    public static function senderId(): SenderId
    {
        return new SenderId();
    }
}
