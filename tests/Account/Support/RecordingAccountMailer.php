<?php
namespace App\Tests\Account\Support;

use App\Account\Domain\Account;
use App\Account\Domain\AccountMailerException;
use App\Account\Domain\AccountMailerInterface;

final class RecordingAccountMailer implements AccountMailerInterface
{
    /** @var Account[] */
    public array $sent = [];

    public function __construct(private bool $fails = false) {}

    public static function failing(): self
    {
        return new self(true);
    }

    public function sendAccountCreated(Account $account): void
    {
        if ($this->fails) {
            throw new AccountMailerException("Échec simulé de l'envoi.");
        }

        $this->sent[] = $account;
    }
}
