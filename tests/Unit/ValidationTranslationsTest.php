<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Tests\TestCase;
use Calisero\LaravelSms\Validation\Rules\PhoneE164;
use Calisero\LaravelSms\Validation\Rules\SenderId;
use Illuminate\Support\Facades\Validator;

class ValidationTranslationsTest extends TestCase
{
    public function test_phone_e164_english_message(): void
    {
        $this->app->setLocale('en');

        $validator = Validator::make([
            'phone' => '12345',
        ], [
            'phone' => [new PhoneE164()],
        ]);

        $this->assertTrue($validator->fails());
        $messages = $validator->errors()->get('phone');
        $this->assertNotEmpty($messages);
        $this->assertSame('The phone must be a valid E.164 phone number.', $messages[0]);
    }

    public function test_phone_e164_romanian_message(): void
    {
        $this->app->setLocale('ro');

        $validator = Validator::make([
            'phone' => '12345',
        ], [
            'phone' => [new PhoneE164()],
        ]);

        $this->assertTrue($validator->fails());
        $messages = $validator->errors()->get('phone');
        $this->assertNotEmpty($messages);
        $this->assertSame('phone trebuie să fie un număr de telefon valid în format E.164.', $messages[0]);
    }

    public function test_sender_id_length_romanian(): void
    {
        $this->app->setLocale('ro');

        $validator = Validator::make([
            'sender_id' => 'AB',
        ], [
            'sender_id' => [new SenderId()],
        ]);

        $this->assertTrue($validator->fails());
        $messages = $validator->errors()->get('sender_id');
        $this->assertNotEmpty($messages);
        $this->assertSame('sender_id trebuie să aibă între 3 și 11 caractere.', $messages[0]);
    }
}
