<?php
namespace App\Tests\Account\Support;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRepositoryInterface;

final class InMemoryAccountRepository implements AccountRepositoryInterface
{
    /** @var array<string, Account> */
    private array $accounts = [];

    public function __construct(Account ...$accounts)
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->email()] = $account;
        }
    }

    public function findByEmail(string $email): ?Account
    {
        return $this->accounts[strtolower(trim($email))] ?? null;
    }

    public function exists(string $email): bool
    {
        return isset($this->accounts[strtolower(trim($email))]);
    }

    public function save(Account $account): void
    {
        $this->accounts[$account->email()] = $account;
    }

    /** @return Account[] */
    public function all(): array
    {
        return array_values($this->accounts);
    }
}
