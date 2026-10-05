<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Notification\SmsMessage;
use Calisero\LaravelSms\Tests\Support\TestPhones;
use Calisero\LaravelSms\Tests\TestCase;

/**
 * Tests the fluent SmsMessage builder used by notifications.
 */
class SmsMessageTest extends TestCase
{
    public function test_it_starts_empty(): void
    {
        $message = SmsMessage::create();

        $this->assertSame('', $message->content);
        $this->assertNull($message->from);
        $this->assertNull($message->to);
        $this->assertNull($message->scheduleAt);
        $this->assertNull($message->idempotencyKey);
        $this->assertNull($message->shortenUrls);
        $this->assertNull($message->visibleBody);
        $this->assertNull($message->validity);
        $this->assertNull($message->callbackUrl);
    }

    public function test_it_builds_fluently(): void
    {
        $message = SmsMessage::create('Initial')
            ->content('Final')
            ->from('CALISERO')
            ->to(TestPhones::DEFAULT)
            ->scheduleAt('2026-01-01 10:00:00')
            ->idempotencyKey('key-42')
            ->shortenUrls()
            ->visibleBody('Shown')
            ->validity(24)
            ->callbackUrl('https://example.test/cb');

        $this->assertSame('Final', $message->content);
        $this->assertSame('CALISERO', $message->from);
        $this->assertSame(TestPhones::DEFAULT, $message->to);
        $this->assertSame('2026-01-01 10:00:00', $message->scheduleAt);
        $this->assertSame('key-42', $message->idempotencyKey);
        $this->assertTrue($message->shortenUrls);
        $this->assertSame('Shown', $message->visibleBody);
        $this->assertSame(24, $message->validity);
        $this->assertSame('https://example.test/cb', $message->callbackUrl);
    }

    public function test_url_shortening_can_be_turned_off_explicitly(): void
    {
        $this->assertFalse(SmsMessage::create()->shortenUrls(false)->shortenUrls);
    }

    /**
     * The API reads schedule_at as a 'Y-m-d H:i:s' in Romania time.
     */
    public function test_a_schedule_date_time_is_converted_to_romania_time(): void
    {
        $message = SmsMessage::create()->scheduleAt(new \DateTimeImmutable('2026-07-15 08:00:00', new \DateTimeZone('UTC')));

        $this->assertSame('2026-07-15 11:00:00', $message->scheduleAt);
    }

    public function test_each_setter_returns_the_same_instance(): void
    {
        $message = SmsMessage::create('Chained');

        $this->assertSame($message, $message->content('New'));
        $this->assertSame($message, $message->from('SENDER'));
        $this->assertSame($message, $message->to(TestPhones::ALTERNATE));
        $this->assertSame($message, $message->scheduleAt('2026-01-01 10:00:00'));
        $this->assertSame($message, $message->idempotencyKey('key-43'));
        $this->assertSame($message, $message->shortenUrls());
        $this->assertSame($message, $message->visibleBody('Shown'));
        $this->assertSame($message, $message->validity(1));
        $this->assertSame($message, $message->callbackUrl('https://example.test/cb'));
    }

    public function test_it_accepts_constructor_arguments(): void
    {
        $message = new SmsMessage(
            content: 'Built',
            from: 'CALISERO',
            to: TestPhones::OTHER,
            scheduleAt: '2026-02-02 12:00:00',
            idempotencyKey: 'key-44',
            shortenUrls: true,
            visibleBody: 'Shown',
            validity: 12,
            callbackUrl: 'https://example.test/cb'
        );

        $this->assertSame('Built', $message->content);
        $this->assertSame('CALISERO', $message->from);
        $this->assertSame(TestPhones::OTHER, $message->to);
        $this->assertSame('2026-02-02 12:00:00', $message->scheduleAt);
        $this->assertSame('key-44', $message->idempotencyKey);
        $this->assertTrue($message->shortenUrls);
        $this->assertSame('Shown', $message->visibleBody);
        $this->assertSame(12, $message->validity);
        $this->assertSame('https://example.test/cb', $message->callbackUrl);
    }
}
