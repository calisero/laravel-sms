<?php

namespace Calisero\LaravelSms\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneE164 implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(trans('calisero::validation.phone_e164_string'));

            return;
        }

        // E.164 format: + followed by 7 to 15 digits (minimum realistic phone number length)
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $value)) {
            $fail(trans('calisero::validation.phone_e164'));
        }
    }
}
