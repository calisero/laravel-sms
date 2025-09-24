<?php

namespace Calisero\LaravelSms\Http\Controllers;

use Calisero\LaravelSms\Events\CreditCritical;
use Calisero\LaravelSms\Events\CreditLow;
use Calisero\LaravelSms\Events\MessageDelivered;
use Calisero\LaravelSms\Events\MessageFailed;
use Calisero\LaravelSms\Events\MessageSent;
use Calisero\LaravelSms\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;

class WebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware(VerifyWebhookSignature::class);
    }

    /**
     * Handle incoming webhook based on the new payload shape.
     *
     * Expected payload example:
     * {
     *   "price": 0.0378,
     *   "sender": "CALISERO",
     *   "sentAt": "2025-09-19T11:59:44.000000Z", // only for sent and delivered
     *   "status": "sent" | "delivered" | "failed",
     *   "messageId": "019961d8-3338-700c-be17-10d061f03a5c",
     *   "recipient": "+40742***350",
     *   "scheduleAt": "2025-09-19T11:59:42.000000Z",
     *   "deliveredAt": "2025-09-19T12:00:24.000000Z", // only for delivered
     *   "remainingBalance": 999.43
     * }
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $status = $payload['status'] ?? null;

        if ($status === 'delivered') {
            Event::dispatch(new MessageDelivered($payload));
        } elseif ($status === 'failed') {
            Event::dispatch(new MessageFailed($payload));
        } elseif ($status === 'sent') {
            Event::dispatch(new MessageSent($payload));
        }

        // Credit threshold monitoring (optional)
        if (array_key_exists('remainingBalance', $payload)) {
            $remaining = $this->toFloatOrNull($payload['remainingBalance']);
            if ($remaining !== null) {
                $critical = $this->configFloat('calisero.credit.critical_threshold');
                $low = $this->configFloat('calisero.credit.low_threshold');

                if ($critical !== null && $remaining <= $critical) {
                    Event::dispatch(new CreditCritical($remaining));
                } elseif ($low !== null && $remaining <= $low) { // only if not critical
                    Event::dispatch(new CreditLow($remaining));
                }
            }
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

    private function configFloat(string $key): ?float
    {
        $val = config($key);
        if ($val === null || $val === '') {
            return null;
        }

        return is_numeric($val) ? (float) $val : null;
    }
}
