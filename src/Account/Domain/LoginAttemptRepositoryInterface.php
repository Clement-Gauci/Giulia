<?php
namespace App\Account\Domain;

interface LoginAttemptRepositoryInterface
{
    public function record(LoginAttempt $attempt): void;

    public function tallyForEmail(AttemptKind $kind, string $email, \DateTimeImmutable $since): AttemptTally;

    public function tallyForIp(AttemptKind $kind, string $ip, \DateTimeImmutable $since): AttemptTally;
}
