<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Notification\SmsChannel;
use Calisero\LaravelSms\Notification\SmsMessage;
use Calisero\LaravelSms\Tests\Doubles\RecordingSmsClient;
use Calisero\LaravelSms\Tests\Fixtures\NotifiableWithoutPhone;
use Calisero\LaravelSms\Tests\Fixtures\NotifiableWithPhone;
use Calisero\LaravelSms\Tests\Fixtures\NotifiableWithRouteMethod;
use Calisero\LaravelSms\Tests\Fixtures\TestSmsNotification;
use Calisero\LaravelSms\Tests\Support\TestPhones;
use Calisero\LaravelSms\Tests\TestCase;
use Illuminate\Notifications\Notification;

/**
 * Tests the notification channel: recipient resolution and parameter mapping.
 */
class SmsChannelTest extends TestCase
{
    private RecordingSmsClient $client;

    private SmsChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new RecordingSmsClient();
        $this->channel = new SmsChannel($this->client);
    }

    public function test_it_maps_the_message_onto_the_send_parameters(): void
    {
        $notification = $this->notificationReturning(
            SmsMessage::create('Hello')
                ->to(TestPhones::DEFAULT)
                ->from('CALISERO')
                ->scheduleAt('2026-01-01T10:00:00Z')
                ->idempotencyKey('key-1')
        );

        $this->channel->send(new NotifiableWithoutPhone(), $notification);

        $this->assertSame([
            'to' => TestPhones::DEFAULT,
            'text' => 'Hello',
            'from' => 'CALISERO',
            'schedule_at' => '2026-01-01T10:00:00Z',
            'idempotency_key' => 'key-1',
        ], $this->client->lastParams);
    }

    public function test_it_omits_optional_parameters_that_were_not_set(): void
    {
        $notification = $this->notificationReturning(
            SmsMessage::create('Bare')->to(TestPhones::DEFAULT)
        );

        $this->channel->send(new NotifiableWithoutPhone(), $notification);

        $this->assertSame(['to' => TestPhones::DEFAULT, 'text' => 'Bare'], $this->client->lastParams);
    }

    public function test_it_routes_via_the_notifiable_route_method(): void
    {
        $this->channel->send(new NotifiableWithRouteMethod(), new TestSmsNotification('Routed'));

        $this->assertSame(TestPhones::OTHER, $this->client->lastParams['to']);
        $this->assertSame('Routed', $this->client->lastParams['text']);
        $this->assertSame('TEST', $this->client->lastParams['from']);
    }

    public function test_it_falls_back_to_the_notifiable_phone_property(): void
    {
        $this->channel->send(new NotifiableWithPhone(), new TestSmsNotification('Phone prop'));

        $this->assertSame(TestPhones::ALTERNATE, $this->client->lastParams['to']);
    }

    public function test_the_message_recipient_takes_precedence_over_the_notifiable(): void
    {
        $notification = $this->notificationReturning(
            SmsMessage::create('Explicit')->to(TestPhones::DEFAULT)
        );

        $this->channel->send(new NotifiableWithPhone(), $notification);

        $this->assertSame(TestPhones::DEFAULT, $this->client->lastParams['to']);
    }

    public function test_the_route_method_takes_precedence_over_the_phone_property(): void
    {
        $this->channel->send(new NotifiableWithRouteMethod(), new TestSmsNotification());

        $this->assertSame(TestPhones::OTHER, $this->client->lastParams['to']);
    }

    public function test_it_does_not_send_when_no_recipient_can_be_resolved(): void
    {
        $this->channel->send(new NotifiableWithoutPhone(), new TestSmsNotification());

        $this->assertNull($this->client->lastParams);
    }

    public function test_it_does_not_send_when_the_notification_returns_no_message(): void
    {
        $this->channel->send(new NotifiableWithPhone(), $this->notificationReturning(null));

        $this->assertNull($this->client->lastParams);
    }

    /**
     * A notification whose toCalisero() hands back exactly the given message.
     */
    private function notificationReturning(?SmsMessage $message): Notification
    {
        return new class ($message) extends Notification {
            public function __construct(private ?SmsMessage $message)
            {
            }

            public function toCalisero(mixed $notifiable): ?SmsMessage
            {
                return $this->message;
            }
        };
    }
}
