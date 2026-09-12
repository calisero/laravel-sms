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
    }

    public function test_it_builds_fluently(): void
    {
        $message = SmsMessage::create('Initial')
            ->content('Final')
            ->from('CALISERO')
            ->to(TestPhones::DEFAULT)
            ->scheduleAt('2026-01-01T10:00:00Z')
            ->idempotencyKey('key-42');

        $this->assertSame('Final', $message->content);
        $this->assertSame('CALISERO', $message->from);
        $this->assertSame(TestPhones::DEFAULT, $message->to);
        $this->assertSame('2026-01-01T10:00:00Z', $message->scheduleAt);
        $this->assertSame('key-42', $message->idempotencyKey);
    }

    public function test_each_setter_returns_the_same_instance(): void
    {
        $message = SmsMessage::create('Chained');

        $this->assertSame($message, $message->content('New'));
        $this->assertSame($message, $message->from('SENDER'));
        $this->assertSame($message, $message->to(TestPhones::ALTERNATE));
        $this->assertSame($message, $message->scheduleAt('2026-01-01T10:00:00Z'));
        $this->assertSame($message, $message->idempotencyKey('key-43'));
    }

    public function test_it_accepts_constructor_arguments(): void
    {
        $message = new SmsMessage(
            content: 'Built',
            from: 'CALISERO',
            to: TestPhones::OTHER,
            scheduleAt: '2026-02-02T12:00:00Z',
            idempotencyKey: 'key-44'
        );

        $this->assertSame('Built', $message->content);
        $this->assertSame('CALISERO', $message->from);
        $this->assertSame(TestPhones::OTHER, $message->to);
        $this->assertSame('2026-02-02T12:00:00Z', $message->scheduleAt);
        $this->assertSame('key-44', $message->idempotencyKey);
    }
}
