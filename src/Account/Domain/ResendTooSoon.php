<?php
namespace App\Account\Domain;

final class ResendTooSoon extends LoginRefused
{
    private function __construct(private readonly \DateTimeImmutable $availableAt)
    {
        parent::__construct(sprintf('Un nouveau code ne peut être demandé qu\'à partir de %s.', $availableAt->format('H:i:s')));
    }

    public static function until(\DateTimeImmutable $availableAt): self
    {
        return new self($availableAt);
    }

    public function availableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function secondsLeft(\DateTimeImmutable $now): int
    {
        return max(0, $this->availableAt->getTimestamp() - $now->getTimestamp());
    }
}
