<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Tests\TestCase;
use Calisero\LaravelSms\Validation\Rules\SenderId;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;

class SenderIdTest extends TestCase
{
    private SenderId $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new SenderId();
    }

    #[DataProvider('validSenderIds')]
    public function test_it_passes_for_valid_sender_ids(string $senderId): void
    {
        $this->assertNull($this->failureFor($senderId), "Sender ID '{$senderId}' should be valid");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validSenderIds(): iterable
    {
        yield 'letters only' => ['MyCompany'];
        yield 'letters and digits' => ['Test123'];
        yield 'with hyphen' => ['SMS-Alert'];
        yield 'with dot' => ['My.Company'];
        yield 'with space' => ['Test 123'];
        yield 'shortest accepted (3 chars)' => ['ABC'];
        yield 'longest accepted (11 chars)' => ['12345678901'];
    }

    #[DataProvider('senderIdsOfInvalidLength')]
    public function test_it_fails_for_sender_ids_of_the_wrong_length(string $senderId): void
    {
        $this->assertSame(
            trans('calisero::validation.sender_id_length', ['attribute' => 'sender_id']),
            $this->failureFor($senderId),
            "Sender ID '{$senderId}' should be rejected for its length"
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function senderIdsOfInvalidLength(): iterable
    {
        yield 'too short (2 chars)' => ['AB'];
        yield 'too long (12 chars)' => ['123456789012'];
        yield 'empty' => [''];
    }

    #[DataProvider('senderIdsWithInvalidCharacters')]
    public function test_it_fails_for_sender_ids_with_disallowed_characters(string $senderId): void
    {
        $this->assertSame(
            trans('calisero::validation.sender_id_format', ['attribute' => 'sender_id']),
            $this->failureFor($senderId),
            "Sender ID '{$senderId}' should be rejected for its characters"
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function senderIdsWithInvalidCharacters(): iterable
    {
        yield 'at sign' => ['Test@123'];
        yield 'hash' => ['Test#123'];
        yield 'underscore' => ['Test_123'];
        yield 'slash' => ['Test/123'];
        yield 'plus' => ['Test+123'];
    }

    public function test_it_fails_for_non_string_values(): void
    {
        $this->assertSame(
            trans('calisero::validation.sender_id_string', ['attribute' => 'sender_id']),
            $this->failureFor(123),
            'Non-string values should be invalid'
        );
    }

    /**
     * Run the rule and return the failure message, or null when the value passed.
     */
    private function failureFor(mixed $value): ?string
    {
        $message = null;
        $this->rule->validate('sender_id', $value, function (string $reason) use (&$message): PotentiallyTranslatedString {
            $message = $reason;

            return new PotentiallyTranslatedString($reason, app('translator'));
        });

        return $message;
    }
}
