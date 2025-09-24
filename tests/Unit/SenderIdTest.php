<?php

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Tests\TestCase;
use Calisero\LaravelSms\Validation\Rules\SenderId;

class SenderIdTest extends TestCase
{
    private SenderId $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new SenderId();
    }

    public function test_it_passes_for_valid_sender_ids(): void
    {
        $validSenderIds = [
            'ABC',
            'Test123',
            'MyCompany',
            'SMS-Alert',
            'My.Company',
            'Test 123',
            '12345678901', // 11 chars
        ];

        foreach ($validSenderIds as $senderId) {
            $failed = false;
            $this->rule->validate('sender_id', $senderId, function () use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed, "Sender ID '{$senderId}' should be valid");
        }
    }

    public function test_it_fails_for_invalid_sender_ids(): void
    {
        $invalidSenderIds = [
            'AB',                    // Too short
            '123456789012',          // Too long
            'Test@Company',          // Invalid character
            'Test#123',             // Invalid character
            'Test_123',             // Invalid character
            '',                     // Empty
        ];

        foreach ($invalidSenderIds as $senderId) {
            $failed = false;
            $this->rule->validate('sender_id', $senderId, function () use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "Sender ID '{$senderId}' should be invalid");
        }
    }

    public function test_it_fails_for_non_string_values(): void
    {
        $failed = false;
        $this->rule->validate('sender_id', 123, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Non-string values should be invalid');
    }
}
