<?php
namespace App\Tests\Account\Support;

use App\Account\Domain\AttemptKind;
use App\Account\Domain\AttemptTally;
use App\Account\Domain\LoginAttempt;
use App\Account\Domain\LoginAttemptRepositoryInterface;

final class InMemoryLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /** @var LoginAttempt[] */
    private array $attempts = [];

    public function record(LoginAttempt $attempt): void
    {
        $this->attempts[] = $attempt;
    }

    public function tallyForEmail(AttemptKind $kind, string $email, \DateTimeImmutable $since): AttemptTally
    {
        return $this->tally($kind, $since, static fn (LoginAttempt $a): bool => $a->email() === $email);
    }

    public function tallyForIp(AttemptKind $kind, string $ip, \DateTimeImmutable $since): AttemptTally
    {
        return $this->tally($kind, $since, static fn (LoginAttempt $a): bool => $a->ip() === $ip);
    }

    private function tally(AttemptKind $kind, \DateTimeImmutable $since, callable $matches): AttemptTally
    {
        $last = null;
        $count = 0;

        foreach ($this->attempts as $attempt) {
            if ($attempt->kind() !== $kind || $attempt->at() < $since || !$matches($attempt)) {
                continue;
            }

            $count++;
            if ($last === null || $attempt->at() > $last) {
                $last = $attempt->at();
            }
        }

        return new AttemptTally($count, $last);
    }
}
