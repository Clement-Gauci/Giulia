<?php
namespace App\Account\UI;

use App\Account\Application\CodeSent;

/**
 * Connexion en cours d'aboutissement, rangée en session entre l'étape 1 et
 * l'étape 2. L'adresse ne transite jamais par l'URL.
 */
final readonly class PendingLogin
{
    public function __construct(
        public string $email,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $resendAvailableAt,
        public int $sendsLeft,
        public bool $remember,
    ) {}

    public static function from(CodeSent $sent, bool $remember): self
    {
        return new self($sent->email, $sent->expiresAt, $sent->resendAvailableAt, $sent->sendsLeft, $remember);
    }

    /** « g•••••t@giulia-pizza-gorges.fr » : assez pour se reconnaître, pas pour lire l'adresse. */
    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        $visible = mb_strlen($local) <= 2
            ? mb_substr($local, 0, 1)
            : mb_substr($local, 0, 1) . str_repeat('•', min(5, mb_strlen($local) - 2)) . mb_substr($local, -1);

        return $domain === '' ? $visible : $visible . '@' . $domain;
    }

    public function secondsBeforeResend(\DateTimeImmutable $now): int
    {
        return max(0, $this->resendAvailableAt->getTimestamp() - $now->getTimestamp());
    }
}
