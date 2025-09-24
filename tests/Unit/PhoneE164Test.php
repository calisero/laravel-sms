<?php

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Tests\TestCase;
use Calisero\LaravelSms\Validation\Rules\PhoneE164;

class PhoneE164Test extends TestCase
{
    private PhoneE164 $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new PhoneE164();
    }

    public function test_it_passes_for_valid_e164_numbers(): void
    {
        $validNumbers = [
            '+1234567890',
            '+447123456789',
            '+33123456789',
            '+4912345678901',
        ];

        foreach ($validNumbers as $number) {
            $failed = false;
            $this->rule->validate('phone', $number, function () use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed, "Number {$number} should be valid");
        }
    }

    public function test_it_fails_for_invalid_numbers(): void
    {
        $invalidNumbers = [
            '1234567890',        // Missing +
            '+0123456789',       // Leading zero after +
            '+123456789012345678', // Too long
            '+123',              // Too short
            'not-a-number',      // Not numeric
            '',                  // Empty
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $this->rule->validate('phone', $number, function () use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "Number {$number} should be invalid");
        }
    }

    public function test_it_fails_for_non_string_values(): void
    {
        $failed = false;
        $this->rule->validate('phone', 123456789, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Non-string values should be invalid');
    }
}
