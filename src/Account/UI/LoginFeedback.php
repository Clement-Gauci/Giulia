<?php
namespace App\Account\UI;

use App\Account\Domain\LoginBlocked;
use App\Account\Domain\LoginRefused;
use App\Account\Domain\ResendTooSoon;
use App\Account\Domain\UnknownEmail;
use App\Account\Domain\WrongCode;

/**
 * Ce qu'il reste d'un refus une fois traversée la redirection : un message, et
 * de quoi rendre l'écran de blocage. On range ça plutôt que l'exception
 * elle-même, dont la trace n'a rien à faire en session.
 */
final readonly class LoginFeedback
{
    private function __construct(
        public string $message,
        public ?int $triesLeft = null,
        public ?\DateTimeImmutable $blockedUntil = null,
        public ?string $blockedReason = null,
    ) {}

    public static function fromRefusal(LoginRefused $refusal): self
    {
        return match (true) {
            $refusal instanceof LoginBlocked => new self(
                $refusal->getMessage(),
                blockedUntil: $refusal->until(),
                blockedReason: $refusal->reason(),
            ),
            $refusal instanceof UnknownEmail => new self(
                sprintf(
                    'Cette adresse n\'est pas enregistrée. Encore %d essai(s) avant blocage.',
                    $refusal->triesLeft(),
                ),
                triesLeft: $refusal->triesLeft(),
            ),
            $refusal instanceof WrongCode => new self(
                sprintf('Code incorrect. Encore %d essai(s).', $refusal->triesLeft()),
                triesLeft: $refusal->triesLeft(),
            ),
            $refusal instanceof ResendTooSoon => new self($refusal->getMessage()),
            default => new self($refusal->getMessage()),
        };
    }

    public static function message(string $message): self
    {
        return new self($message);
    }

    public function isBlocking(): bool
    {
        return $this->blockedUntil !== null;
    }

    public function secondsLeft(\DateTimeImmutable $now): int
    {
        return $this->blockedUntil === null
            ? 0
            : max(0, $this->blockedUntil->getTimestamp() - $now->getTimestamp());
    }
}
