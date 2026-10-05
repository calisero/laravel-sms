<?php

namespace Calisero\LaravelSms\Http\Controllers;

use Calisero\LaravelSms\Events\CreditCritical;
use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\DailyLimitLow;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\MessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;

class WebhookController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Handle incoming webhook based on the new payload shape.
     *
     * Expected payload example:
     * {
     *   "price": 0.0378,
     *   "sender": "CALISERO",
     *   "sentAt": "2025-09-19T11:59:44.000000Z", // only for sent and delivered
     *   "status": "sent" | "delivered" | "undelivered",
     *   "messageId": "019961d8-3338-700c-be17-10d061f03a5c",
     *   "recipient": "+40742***350",
     *   "scheduleAt": "2025-09-19T11:59:42.000000Z",
     *   "deliveredAt": "2025-09-19T12:00:24.000000Z", // only for delivered
     *   "remainingBalance": 999.43,
     *   "dailyLimit": 1000, // null when the account has no daily limit
     *   "dailyRemaining": 588, // null when the account has no daily limit
     *   "sentToday": 412
     * }
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $status = $payload['status'] ?? null;

        if ('delivered' === $status) {
            Event::dispatch(new MessageDelivered($payload));
        } elseif ('undelivered' === $status || 'failed' === $status) {
            // The API reports a failed delivery as undelivered; failed is kept for
            // callers that post it themselves.
            Event::dispatch(new MessageFailed($payload));
        } elseif ('sent' === $status) {
            Event::dispatch(new MessageSent($payload));
        }

        // Credit threshold monitoring (optional)
        if (array_key_exists('remainingBalance', $payload)) {
            $remaining = $this->toFloatOrNull($payload['remainingBalance']);
            if (null !== $remaining) {
                $critical = $this->configFloat('calisero.credit.critical_threshold');
                $low = $this->configFloat('calisero.credit.low_threshold');

                if (null !== $critical && $remaining <= $critical) {
                    Event::dispatch(new CreditCritical($remaining));
                } elseif (null !== $low && $remaining <= $low) { // only if not critical
                    Event::dispatch(new CreditLow($remaining));
                }
            }
        }

        // Daily sending limit monitoring (optional); both are null while the
        // account has no daily limit
        $dailyLimit = $this->toIntOrNull($payload['dailyLimit'] ?? null);
        $dailyRemaining = $this->toIntOrNull($payload['dailyRemaining'] ?? null);
        $dailyLow = $this->toIntOrNull(config('calisero.daily_limit.low_threshold'));

        if (null !== $dailyLimit && null !== $dailyRemaining && null !== $dailyLow && $dailyRemaining <= $dailyLow) {
            Event::dispatch(new DailyLimitLow(
                $dailyLimit,
                $dailyRemaining,
                $this->toIntOrNull($payload['sentToday'] ?? null)
            ));
        }

        return response()->json(['ok' => true]);
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * An integer, or a string of digits as the environment gives thresholds.
     */
    private function toIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && 1 === preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    private function configFloat(string $key): ?float
    {
        $val = config($key);
        if (null === $val || '' === $val) {
            return null;
        }

        return is_numeric($val) ? (float) $val : null;
    }
}
