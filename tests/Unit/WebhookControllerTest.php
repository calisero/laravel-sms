<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Events\CreditCritical;
use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\DailyLimitLow;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Tests\Concerns\BuildsWebhookPayloads;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class WebhookControllerTest extends TestCase
{
    use BuildsWebhookPayloads;

    /**
     * @param class-string $expected
     */
    #[DataProvider('statusEvents')]
    public function test_it_dispatches_the_event_matching_the_status(string $status, string $expected): void
    {
        Event::fake();

        $payload = $this->webhookPayload(['status' => $status, 'messageId' => "uuid-{$status}"]);

        $this->postWebhook($payload)->assertOk()->assertJson(['ok' => true]);

        Event::assertDispatched($expected, function ($event) use ($payload) {
            return $event->messageData['messageId'] === $payload['messageId'];
        });

        foreach (self::messageEvents() as $other) {
            if ($other !== $expected) {
                Event::assertNotDispatched($other);
            }
        }
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function statusEvents(): iterable
    {
        yield 'delivered' => ['delivered', MessageDelivered::class];
        // Regression: the API reports a failed delivery as undelivered, which used to
        // dispatch nothing
        yield 'undelivered' => ['undelivered', MessageFailed::class];
        yield 'failed' => ['failed', MessageFailed::class];
        yield 'sent' => ['sent', MessageSent::class];
    }

    #[DataProvider('unrecognisedStatuses')]
    public function test_it_dispatches_nothing_for_an_unrecognised_status(mixed $status): void
    {
        Event::fake();

        $this->postWebhook($this->webhookPayload(['status' => $status]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        foreach (self::messageEvents() as $event) {
            Event::assertNotDispatched($event);
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unrecognisedStatuses(): iterable
    {
        yield 'an intermediate status' => ['processing'];
        yield 'an unknown status' => ['whatever'];
        yield 'the wrong case' => ['Delivered'];
        yield 'null' => [null];
    }

    public function test_it_dispatches_nothing_when_the_status_is_absent(): void
    {
        Event::fake();

        $payload = $this->webhookPayload();
        unset($payload['status']);

        $this->postWebhook($payload)->assertOk();

        foreach (self::messageEvents() as $event) {
            Event::assertNotDispatched($event);
        }
    }

    public function test_it_dispatches_the_low_credit_event(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: 500.0, critical: 100.0);

        $this->postWebhook($this->webhookPayload(['remainingBalance' => 450.00]))->assertOk();

        Event::assertDispatched(CreditLow::class, fn ($e) => 450.0 === $e->remainingBalance);
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_it_dispatches_only_the_critical_credit_event_below_both_thresholds(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: 500.0, critical: 100.0);

        $this->postWebhook($this->webhookPayload(['remainingBalance' => 50.00]))->assertOk();

        Event::assertDispatched(CreditCritical::class, fn ($e) => 50.0 === $e->remainingBalance);
        Event::assertNotDispatched(CreditLow::class);
    }

    public function test_it_dispatches_credit_events_on_the_threshold_itself(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: 500.0, critical: 100.0);

        $this->postWebhook($this->webhookPayload(['remainingBalance' => 500.00]))->assertOk();

        Event::assertDispatched(CreditLow::class, fn ($e) => 500.0 === $e->remainingBalance);
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_it_dispatches_no_credit_event_above_the_thresholds(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: 500.0, critical: 100.0);

        $this->postWebhook($this->webhookPayload(['remainingBalance' => 750.00]))->assertOk();

        Event::assertNotDispatched(CreditLow::class);
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_it_dispatches_no_credit_event_when_the_thresholds_are_disabled(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: null, critical: null);

        $this->postWebhook($this->webhookPayload(['remainingBalance' => 10.00]))->assertOk();

        Event::assertNotDispatched(CreditLow::class);
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_it_dispatches_no_credit_event_when_the_balance_is_absent(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: 500.0, critical: 100.0);

        $payload = $this->webhookPayload();
        unset($payload['remainingBalance']);

        $this->postWebhook($payload)->assertOk();

        Event::assertNotDispatched(CreditLow::class);
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_it_dispatches_no_credit_event_when_the_balance_is_not_numeric(): void
    {
        Event::fake();
        $this->setCreditThresholds(low: 500.0, critical: 100.0);

        $this->postWebhook($this->webhookPayload(['remainingBalance' => 'unknown']))->assertOk();

        Event::assertNotDispatched(CreditLow::class);
        Event::assertNotDispatched(CreditCritical::class);
    }

    /**
     * Thresholds arrive from the environment, so they may well be numeric strings.
     */
    public function test_it_accepts_numeric_string_thresholds(): void
    {
        Event::fake();
        config()->set('calisero.credit.low_threshold', '500');
        config()->set('calisero.credit.critical_threshold', '100');

        $this->postWebhook($this->webhookPayload(['remainingBalance' => '450']))->assertOk();

        Event::assertDispatched(CreditLow::class, fn ($e) => 450.0 === $e->remainingBalance);
        Event::assertNotDispatched(CreditCritical::class);
    }

    public function test_the_event_reads_the_payload_with_the_sdk(): void
    {
        Event::fake();

        $this->postWebhook($this->webhookPayload([
            'status' => 'delivered',
            'messageId' => 'uuid-typed',
            'deliveredAt' => '2026-01-01T12:00:24.000000Z',
            'dailyLimit' => 1000,
            'dailyRemaining' => 588,
            'sentToday' => 412,
        ]))->assertOk();

        Event::assertDispatched(MessageDelivered::class, function (MessageDelivered $event): bool {
            $message = $event->message();

            return null !== $message
                && 'uuid-typed' === $message->getMessageId()
                && 'delivered' === $message->getStatus()
                && '2026-01-01T12:00:24.000000Z' === $message->getDeliveredAt()
                && 0.0378 === $message->getPrice()
                && 1000 === $message->getDailyLimit()
                && 588 === $message->getDailyRemaining()
                && 412 === $message->getSentToday();
        });
    }

    public function test_the_event_has_no_typed_payload_when_a_required_field_is_missing(): void
    {
        Event::fake();

        $payload = $this->webhookPayload(['status' => 'sent']);
        unset($payload['messageId']);

        $this->postWebhook($payload)->assertOk();

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => null === $event->message()
            && 'sent' === $event->messageData['status']);
    }

    public function test_it_dispatches_the_daily_limit_event_at_or_below_the_threshold(): void
    {
        Event::fake();
        config()->set('calisero.daily_limit.low_threshold', 100);

        $this->postWebhook($this->webhookPayload(['dailyLimit' => 1000, 'dailyRemaining' => 100, 'sentToday' => 900]))->assertOk();

        Event::assertDispatched(DailyLimitLow::class, fn (DailyLimitLow $e): bool => 1000 === $e->dailyLimit
            && 100 === $e->dailyRemaining
            && 900 === $e->sentToday);
    }

    public function test_it_dispatches_the_daily_limit_event_once_the_limit_is_used_up(): void
    {
        Event::fake();
        config()->set('calisero.daily_limit.low_threshold', '50'); // as the environment gives it

        $this->postWebhook($this->webhookPayload(['dailyLimit' => 1000, 'dailyRemaining' => 0, 'sentToday' => 1000]))->assertOk();

        Event::assertDispatched(DailyLimitLow::class, fn (DailyLimitLow $e): bool => 0 === $e->dailyRemaining);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('noDailyLimitEvent')]
    public function test_it_dispatches_no_daily_limit_event(mixed $threshold, array $payload): void
    {
        Event::fake();
        config()->set('calisero.daily_limit.low_threshold', $threshold);

        $this->postWebhook($this->webhookPayload($payload))->assertOk();

        Event::assertNotDispatched(DailyLimitLow::class);
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function noDailyLimitEvent(): iterable
    {
        yield 'above the threshold' => [100, ['dailyLimit' => 1000, 'dailyRemaining' => 101]];
        yield 'the monitoring is disabled' => [null, ['dailyLimit' => 1000, 'dailyRemaining' => 0]];
        yield 'the account has no daily limit' => [100, ['dailyLimit' => null, 'dailyRemaining' => null]];
        yield 'values that are not counts' => [100, ['dailyLimit' => 'n/a', 'dailyRemaining' => -1]];
    }

    private function setCreditThresholds(?float $low, ?float $critical): void
    {
        config()->set('calisero.credit.low_threshold', $low);
        config()->set('calisero.credit.critical_threshold', $critical);
    }

    /**
     * @return list<class-string>
     */
    private static function messageEvents(): array
    {
        return [MessageDelivered::class, MessageFailed::class, MessageSent::class];
    }
}
