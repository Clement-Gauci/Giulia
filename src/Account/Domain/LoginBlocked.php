<?php
namespace App\Account\Domain;

final class LoginBlocked extends LoginRefused
{
    private function __construct(
        private readonly \DateTimeImmutable $until,
        private readonly string $reason,
    ) {
        parent::__construct(sprintf('Connexion bloquée jusqu\'à %s : %s', $until->format('H:i'), $reason));
    }

    public static function untilWith(\DateTimeImmutable $until, string $reason): self
    {
        return new self($until, $reason);
    }

    /** Le blocage court BLOCK_MINUTES après la dernière tentative fautive. */
    public static function after(\DateTimeImmutable $lastAttemptAt, string $reason): self
    {
        return new self($lastAttemptAt->modify(sprintf('+%d minutes', LoginPolicy::BLOCK_MINUTES)), $reason);
    }

    public function until(): \DateTimeImmutable
    {
        return $this->until;
    }

    /** Ce que la maquette affiche sous le titre « Accès temporairement bloqué ». */
    public function reason(): string
    {
        return $this->reason;
    }

    public function secondsLeft(\DateTimeImmutable $now): int
    {
        return max(0, $this->until->getTimestamp() - $now->getTimestamp());
    }
}
