<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Contracts\SmsClient as SmsClientContract;
use Calisero\LaravelSms\SmsClient;
use Calisero\LaravelSms\Tests\Doubles\FakeSdkClient;
use Calisero\LaravelSms\Tests\Support\TestPhones;
use Calisero\LaravelSms\Tests\TestCase;
use Calisero\Sms\Dto\ResponseMeta;
use Calisero\Sms\Dto\ShortenedLink;
use Calisero\Sms\Exceptions\DailyLimitExceededException;
use Calisero\Sms\Exceptions\NotFoundException;
use Calisero\Sms\Exceptions\RateLimitedException;
use Calisero\Sms\Exceptions\ValidationException;

/**
 * Tests the artisan commands over the package's client and a fake SDK.
 */
class ConsoleCommandsTest extends TestCase
{
    private const TRACE_ID = '9b80eef1-49d4-4502-85a8-febb68cc11a7';

    private FakeSdkClient $sdk;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('calisero.webhook.enabled', false);
        config()->set('calisero.account_id', 'acc-123');

        $this->useSdk(new FakeSdkClient());
    }

    public function test_the_test_command_sends_and_shows_the_daily_limit_left(): void
    {
        $this->sdk->messageService->responseMeta = new ResponseMeta(self::TRACE_ID, 240, 239, 1000, 872);

        $this->artisan('calisero:sms:test', ['to' => TestPhones::DEFAULT, '--text' => 'Hi'])
            ->expectsOutputToContain('✓ SMS created')
            ->expectsOutputToContain('872 of 1000')
            ->expectsOutputToContain(self::TRACE_ID)
            ->assertSuccessful();

        $this->assertSame('Hi', $this->sdk->messageService->lastPayload['body'] ?? null);
        $this->assertArrayNotHasKey('shorten_urls', $this->sdk->messageService->lastPayload ?? []);
    }

    public function test_the_test_command_omits_the_daily_limit_of_an_account_without_one(): void
    {
        $this->artisan('calisero:sms:test', ['to' => TestPhones::DEFAULT])
            ->doesntExpectOutputToContain('Daily Remaining')
            ->assertSuccessful();
    }

    public function test_the_test_command_asks_for_shortened_urls_and_lists_them(): void
    {
        $this->sdk->messageService->shortenedUrls = [$this->shortenedLink()];

        $this->artisan('calisero:sms:test', [
            'to' => TestPhones::DEFAULT,
            '--text' => 'See https://example.test/offer',
            '--shorten-urls' => true,
        ])
            ->expectsOutputToContain('https://calisero.ro/s/ghJKPV')
            ->assertSuccessful();

        $this->assertTrue($this->sdk->messageService->lastPayload['shorten_urls'] ?? null);
    }

    public function test_the_test_command_explains_a_refusal_of_the_daily_limit(): void
    {
        $this->sdk->messageService->createException = new DailyLimitExceededException(
            'This account can send at most 1,000 messages a day.',
            0,
            null,
            429,
            self::TRACE_ID,
            [],
            21600,
            null,
            null,
            null,
            1000,
            0,
            '2026-10-06T00:00:00+03:00'
        );

        $this->artisan('calisero:sms:test', ['to' => TestPhones::DEFAULT])
            ->expectsOutputToContain('✗ Daily sending limit reached: This account can send at most 1,000 messages a day.')
            ->expectsOutputToContain('Daily limit: 1000, resets at: 2026-10-06T00:00:00+03:00')
            ->expectsOutputToContain('Trace ID: '.self::TRACE_ID)
            ->assertFailed();
    }

    public function test_the_test_command_tells_the_request_rate_limit_apart(): void
    {
        $this->sdk->messageService->createException = new RateLimitedException('Too Many Attempts.', 0, null, 429, null, [], 30);

        $this->artisan('calisero:sms:test', ['to' => TestPhones::DEFAULT])
            ->expectsOutputToContain('✗ Rate limited: Too Many Attempts.')
            ->expectsOutputToContain('Retry after: 30s')
            ->doesntExpectOutputToContain('Daily sending limit')
            ->assertFailed();
    }

    public function test_the_test_command_lists_the_fields_the_api_refused(): void
    {
        $this->sdk->messageService->createException = new ValidationException(
            'The recipient field is invalid.',
            0,
            null,
            422,
            self::TRACE_ID,
            [],
            ['recipient' => ['The recipient field is invalid.']]
        );

        $this->artisan('calisero:sms:test', ['to' => TestPhones::DEFAULT])
            ->expectsOutputToContain('✗ API validation error: The recipient field is invalid.')
            ->expectsTable(['Field', 'Errors'], [['recipient', 'The recipient field is invalid.']])
            ->assertFailed();
    }

    public function test_the_test_command_sends_the_validity_in_hours(): void
    {
        $this->artisan('calisero:sms:test', ['to' => TestPhones::DEFAULT, '--validity' => '48'])->assertSuccessful();

        $this->assertSame(48, $this->sdk->messageService->lastPayload['validity'] ?? null);
    }

    public function test_the_status_command_lists_the_shortened_urls_with_their_clicks(): void
    {
        $this->sdk->messageService->shortenedUrls = [$this->shortenedLink(clicks: 7)];

        $this->artisan('calisero:sms:status', ['id' => 'msg-1'])
            ->expectsOutputToContain('✓ SMS retrieved successfully')
            ->expectsTable(
                ['Original Link', 'Shortened Link', 'Clicks', 'Last Click'],
                [['https://example.test/offer', 'https://calisero.ro/s/ghJKPV', '7', '2026-10-05T09:12:00.000000Z']]
            )
            ->assertSuccessful();
    }

    public function test_the_status_command_reports_an_unknown_message(): void
    {
        $this->useClient(new class () extends SmsClient {
            public function __construct()
            {
                parent::__construct(new FakeSdkClient());
            }

            public function getMessageStatus(string $messageId): \Calisero\Sms\Dto\GetMessageResponse
            {
                throw new NotFoundException('Resource not found!', 0, null, 404, 'trace-404');
            }
        });

        $this->artisan('calisero:sms:status', ['id' => 'missing'])
            ->expectsOutputToContain('✗ Message not found: Resource not found!')
            ->expectsOutputToContain('Trace ID: trace-404')
            ->assertFailed();
    }

    public function test_the_account_command_shows_the_daily_limit(): void
    {
        $this->useSdk(new FakeSdkClient(credit: 250.5, dailyLimit: 1000, dailyRemaining: 873, sentToday: 127));

        $this->artisan('calisero:account')
            ->expectsTable(['Property', 'Value'], [
                ['ID', 'acc-123'],
                ['Name', 'Test Account'],
                ['Status', 'active'],
                ['Sandbox', 'Yes'],
                ['Credit', '250.5'],
                ['Daily Limit', '1000'],
                ['Daily Remaining', '873'],
                ['Sent Today', '127'],
            ])
            ->expectsOutputToContain('resets at midnight, Romania time')
            ->assertSuccessful();
    }

    public function test_the_account_command_shows_an_account_without_a_daily_limit(): void
    {
        $this->artisan('calisero:account')
            ->expectsOutputToContain('None')
            ->doesntExpectOutputToContain('resets at midnight')
            ->assertSuccessful();
    }

    public function test_the_account_command_needs_an_account_id(): void
    {
        config()->set('calisero.account_id', null);

        $this->artisan('calisero:account')
            ->expectsOutputToContain('✗ Unexpected failure: Account ID not configured (calisero.account_id)')
            ->assertFailed();
    }

    private function useSdk(FakeSdkClient $sdk): void
    {
        $this->sdk = $sdk;
        $this->useClient(new SmsClient($sdk));
    }

    private function useClient(SmsClientContract $client): void
    {
        $this->app->instance(SmsClientContract::class, $client);
    }

    private function shortenedLink(int $clicks = 0): ShortenedLink
    {
        return new ShortenedLink(
            '019adfbb-40a1-71ee-bcb5-8d551b8cfdae',
            'https://example.test/offer',
            'https://calisero.ro/s/ghJKPV',
            $clicks,
            $clicks > 0 ? '2026-10-05T09:12:00.000000Z' : null,
            '2026-10-05T08:00:00.000000Z'
        );
    }
}
