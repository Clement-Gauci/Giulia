<?php
namespace App\Tests\Account\Support;

use App\Account\Domain\Account;
use App\Account\Domain\LoginCodeMailerInterface;

final class RecordingLoginCodeMailer implements LoginCodeMailerInterface
{
    public ?Account $lastAccount = null;
    public ?string $lastCode = null;
    public ?\DateTimeImmutable $lastExpiresAt = null;

    public function sendLoginCode(Account $account, string $code, \DateTimeImmutable $expiresAt): void
    {
        $this->lastAccount = $account;
        $this->lastCode = $code;
        $this->lastExpiresAt = $expiresAt;
    }
}
