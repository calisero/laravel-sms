<?php

namespace Calisero\LaravelSms\Console\Commands;

use Calisero\LaravelSms\Console\Concerns\RendersApiOutput;
use Calisero\LaravelSms\Contracts\SmsClient;
use Illuminate\Console\Command;

class AccountCommand extends Command
{
    use RendersApiOutput;

    protected $signature = 'calisero:account';

    protected $description = 'Show the configured account: credit, status and daily sending limit';

    public function handle(SmsClient $client): int
    {
        try {
            $account = $client->getAccount();
        } catch (\Throwable $e) {
            return $this->renderFailure($e, 'Account not found');
        }

        $dailyLimit = $account->getDailyLimit();

        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $account->getId()],
                ['Name', $account->getName()],
                ['Status', $account->getStatus()],
                ['Sandbox', $account->isSandbox() ? 'Yes' : 'No'],
                ['Credit', (string) $account->getCredit()],
                ['Daily Limit', null !== $dailyLimit ? (string) $dailyLimit : 'None'],
                ['Daily Remaining', null !== $dailyLimit ? (string) $account->getDailyRemaining() : '—'],
                ['Sent Today', (string) $account->getSentToday()],
            ]
        );

        if (null !== $dailyLimit) {
            $this->line('The daily limit resets at midnight, Romania time.');
        }

        return self::SUCCESS;
    }
}
