<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Fixtures;

use Calisero\LaravelSms\Tests\Support\TestPhones;

/**
 * A notifiable the channel routes to through the conventional $phone property.
 */
class NotifiableWithPhone
{
    public string $phone = TestPhones::ALTERNATE;
}
