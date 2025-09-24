<?php

namespace Calisero\LaravelSms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditLow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public float $remainingBalance
    ) {
    }
}
