<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Events\CreditCritical;
use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class WebhookControllerTest extends TestCase
{
    private function postPayload(array $payload)
    {
        return $this->postJson(config('calisero.webhook.path'), $payload);
    }

    public function test_it_dispatches_delivered_event(): void
    {
        Event::fake();

        $payload = [
            'price' => 0.0378,
            'sender' => 'CALISERO',
            'sentAt' => '2025-09-19T11:59:44.000000Z',
            'status' => 'delivered',
            'messageId' => 'uuid-123',
            'recipient' => '+40123456789',
            'scheduleAt' => '2025-09-19T11:59:42.000000Z',
            'deliveredAt' => '2025-09-19T12:00:24.000000Z',
            'remainingBalance' => 999.43,
        ];

        $response = $this->postPayload($payload);
        $response->assertOk()->assertJson(['ok' => true]);

        Event::assertDispatched(MessageDelivered::class, function ($event) use ($payload) {
            return $event->messageData['messageId'] === $payload['messageId'];
        });
        Event::assertNotDispatched(MessageFailed::class);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_it_dispatches_failed_event(): void
    {
        Event::fake();

        $payload = [
            'price' => 0.0378,
            'sender' => 'CALISERO',
            'sentAt' => '2025-09-19T11:59:44.000000Z',
            'status' => 'failed',
            'messageId' => 'uuid-456',
            'recipient' => '+40123456789',
            'scheduleAt' => '2025-09-19T11:59:42.000000Z',
            'deliveredAt' => null,
            'remainingBalance' => 998.99,
        ];

        $response = $this->postPayload($payload);
        $response->assertOk()->assertJson(['ok' => true]);

        Event::assertDispatched(MessageFailed::class, function ($event) use ($payload) {
            return $event->messageData['messageId'] === $payload['messageId'];
        });
        Event::assertNotDispatched(MessageDelivered::class);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_it_dispatches_sent_event(): void
    {
        Event::fake();

        $payload = [
            'price' => 0.0378,
            'sender' => 'CALISERO',
            'sentAt' => '2025-09-19T11:59:44.000000Z',
            'status' => 'sent',
            'messageId' => 'uuid-555',
            'recipient' => '+40123456789',
            'scheduleAt' => '2025-09-19T11:59:42.000000Z',
            'deliveredAt' => null,
            'remainingBalance' => 999.00,
        ];

        $response = $this->postPayload($payload);
        $response->assertOk()->assertJson(['ok' => true]);

        Event::assertDispatched(MessageSent::class, function ($event) use ($payload) {
            return $event->messageData['messageId'] === $payload['messageId'];
        });
        Event::assertNotDispatched(MessageDelivered::class);
        Event::assertNotDispatched(MessageFailed::class);
    }

    public function test_it_ignores_unknown_status(): void
    {
        Event::fake();

        $payload = [
            'price' => 0.0378,
            'sender' => 'CALISERO',
            'sentAt' => '2025-09-19T11:59:44.000000Z',
            'status' => 'processing',
            'messageId' => 'uuid-789',
            'recipient' => '+40123456789',
            'scheduleAt' => '2025-09-19T11:59:42.000000Z',
            'deliveredAt' => null,
            'remainingBalance' => 997.10,
        ];

        $response = $this->postPayload($payload);
        $response->assertOk()->assertJson(['ok' => true]);

        Event::assertNotDispatched(MessageDelivered::class);
        Event::assertNotDispatched(MessageFailed::class);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_it_dispatches_credit_low_event(): void
    {
        Event::fake();
        config()->set('calisero.credit.low_threshold', 500.0);
        config()->set('calisero.credit.critical_threshold', 100.0);

        $payload = [
            'price' => 0.01,
            'sender' => 'CALISERO',
            'sentAt' => now()->toIso8601String(),
            'status' => 'sent',
            'messageId' => 'uuid-low-1',
            'recipient' => '+40123456789',
            'scheduleAt' => now()->toIso8601String(),
            'deliveredAt' => null,
            'remainingBalance' => 450.00,
        ];

        $this->postPayload($payload)->assertOk();

        Event::assertDispatched(CreditLow::class, function ($e) {
            return $e->remainingBalance === 450.0;
        });
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_it_dispatches_credit_critical_event_only(): void
    {
        Event::fake();
        config()->set('calisero.credit.low_threshold', 500.0);
        config()->set('calisero.credit.critical_threshold', 100.0);

        $payload = [
            'price' => 0.01,
            'sender' => 'CALISERO',
            'sentAt' => now()->toIso8601String(),
            'status' => 'sent',
            'messageId' => 'uuid-critical-1',
            'recipient' => '+40123456789',
            'scheduleAt' => now()->toIso8601String(),
            'deliveredAt' => null,
            'remainingBalance' => 50.00,
        ];

        $this->postPayload($payload)->assertOk();

        Event::assertDispatched(CreditCritical::class, function ($e) {
            return $e->remainingBalance === 50.0;
        });
        Event::assertNotDispatched(CreditLow::class);
    }

    public function test_no_credit_events_when_thresholds_disabled(): void
    {
        Event::fake();
        config()->set('calisero.credit.low_threshold', null);
        config()->set('calisero.credit.critical_threshold', null);

        $payload = [
            'price' => 0.01,
            'sender' => 'CALISERO',
            'sentAt' => now()->toIso8601String(),
            'status' => 'sent',
            'messageId' => 'uuid-none-1',
            'recipient' => '+40123456789',
            'scheduleAt' => now()->toIso8601String(),
            'deliveredAt' => null,
            'remainingBalance' => 10.00,
        ];

        $this->postPayload($payload)->assertOk();

        Event::assertNotDispatched(CreditLow::class);
        Event::assertNotDispatched(CreditCritical::class);
    }
}
