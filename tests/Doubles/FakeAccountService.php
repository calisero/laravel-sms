<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Doubles;

use Calisero\Sms\Dto\Account;
use Calisero\Sms\Dto\GetAccountResponse;

/**
 * Stand-in for the SDK's AccountService that returns a fixed credit balance.
 */
class FakeAccountService
{
    public ?string $lastRetrievedId = null;

    public function __construct(
        private float $credit = 0.0
    ) {
    }

    public function get(string $accountId): GetAccountResponse
    {
        $this->lastRetrievedId = $accountId;

        return new GetAccountResponse(new Account(
            id: $accountId,
            code: 'ACC-TEST',
            name: 'Test Account',
            description: null,
            fiscalCode: null,
            registryNumber: null,
            iban: null,
            city: 'Test City',
            state: 'Test State',
            country: 'Test Country',
            address: 'Test Address',
            postalCode: null,
            email: null,
            phone: null,
            contactPerson: null,
            credit: $this->credit,
            status: 'active',
            sandbox: true,
            createdAt: '2026-01-01T00:00:00.000000Z',
        ));
    }
}
