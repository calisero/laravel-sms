<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Events\CreditCritical;
use Calisero\LaravelSms\Events\CreditLow;
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
