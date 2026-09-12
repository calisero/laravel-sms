<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Fixtures;

use Calisero\LaravelSms\Tests\Support\TestPhones;
use Illuminate\Notifications\Notification;

/**
 * A notifiable that routes explicitly, and whose $phone must therefore be ignored.
 */
class NotifiableWithRouteMethod
{
    public string $phone = TestPhones::ALTERNATE;

    public function routeNotificationForCalisero(Notification $notification): string
    {
        return TestPhones::OTHER;
    }
}
