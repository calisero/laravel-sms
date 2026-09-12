<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Tests\Support\TestPhones;
use Calisero\LaravelSms\Tests\TestCase;
use Calisero\LaravelSms\Validation\Rules\PhoneE164;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;

class PhoneE164Test extends TestCase
{
    private PhoneE164 $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new PhoneE164();
    }

    #[DataProvider('validNumbers')]
    public function test_it_passes_for_valid_e164_numbers(string $number): void
    {
        $this->assertNull($this->failureFor($number), "Number {$number} should be valid");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNumbers(): iterable
    {
        yield 'ten digits' => [TestPhones::DEFAULT];
        yield 'shortest accepted (7 digits)' => [TestPhones::SHORTEST_VALID];
        yield 'longest accepted (15 digits)' => [TestPhones::LONGEST_VALID];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_fails_for_invalid_numbers(string $number): void
    {
        $this->assertSame(
            trans('calisero::validation.phone_e164'),
            $this->failureFor($number),
            "Number {$number} should be invalid"
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'missing plus' => [ltrim(TestPhones::DEFAULT, '+')];
        yield 'leading zero after plus' => ['+0995550100'];
        yield 'too long (16 digits)' => [TestPhones::TOO_LONG];
        yield 'too short (6 digits)' => [TestPhones::TOO_SHORT];
        yield 'not numeric' => ['not-a-number'];
        yield 'empty' => [''];
        yield 'spaces' => ['+999 555 0100'];
        yield 'plus only' => ['+'];
    }

    public function test_it_fails_for_non_string_values(): void
    {
        $this->assertSame(
            trans('calisero::validation.phone_e164_string'),
            $this->failureFor(9995550100),
            'Non-string values should be invalid'
        );
    }

    /**
     * Run the rule and return the failure message, or null when the value passed.
     */
    private function failureFor(mixed $value): ?string
    {
        $message = null;
        $this->rule->validate('phone', $value, function (string $reason) use (&$message): PotentiallyTranslatedString {
            $message = $reason;

            return new PotentiallyTranslatedString($reason, app('translator'));
        });

        return $message;
    }
}
