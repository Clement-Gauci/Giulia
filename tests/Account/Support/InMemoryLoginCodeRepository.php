<?php
namespace App\Tests\Account\Support;

use App\Account\Domain\LoginCode;
use App\Account\Domain\LoginCodeRepositoryInterface;

final class InMemoryLoginCodeRepository implements LoginCodeRepositoryInterface
{
    /** @var array<string, LoginCode> une seule ligne par adresse, comme en base */
    private array $codes = [];

    public function save(LoginCode $code): void
    {
        $this->codes[$code->email()] = $code;
    }

    public function findFor(string $email): ?LoginCode
    {
        return $this->codes[$email] ?? null;
    }

    public function deleteFor(string $email): void
    {
        unset($this->codes[$email]);
    }
}
