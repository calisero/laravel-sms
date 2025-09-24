<?php

namespace Calisero\LaravelSms\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SenderId implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(trans('calisero::validation.sender_id_string', ['attribute' => $attribute]));

            return;
        }

        $length = strlen($value);

        // Sender ID must be 3-11 characters
        if ($length < 3 || $length > 11) {
            $fail(trans('calisero::validation.sender_id_length', ['attribute' => $attribute]));

            return;
        }

        // Only alphanumeric characters and allowed punctuation
        if (! preg_match('/^[a-zA-Z0-9\-.\s]+$/', $value)) {
            $fail(trans('calisero::validation.sender_id_format', ['attribute' => $attribute]));
        }
    }
}
