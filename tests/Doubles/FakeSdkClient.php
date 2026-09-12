<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Doubles;

/**
 * Stand-in for the upstream Calisero SDK client.
 *
 * Exposes the same service accessors the package relies on, so the wrapper can be
 * exercised without any HTTP traffic. Deliberately declares nothing else: a wrapper
 * method that reaches for an accessor the real SDK does not have will fail here too.
 */
class FakeSdkClient
{
    public FakeMessageService $messageService;

    public FakeAccountService $accountService;

    public function __construct(float $credit = 0.0)
    {
        $this->messageService = new FakeMessageService();
        $this->accountService = new FakeAccountService($credit);
    }

    public function messages(): FakeMessageService
    {
        return $this->messageService;
    }

    public function accounts(): FakeAccountService
    {
        return $this->accountService;
    }
}
