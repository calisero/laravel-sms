<?php

namespace Calisero\LaravelSms\Events;

use Calisero\LaravelSms\Events\Concerns\ReadsDeliveryPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched for a delivery webhook with the status undelivered (or failed).
 */
class MessageFailed
{
    use Dispatchable;
    use SerializesModels;
    use InteractsWithSockets;
    use ReadsDeliveryPayload;

    public function __construct(
        /** @var array<string, mixed> */
        public array $messageData
    ) {
    }
}
