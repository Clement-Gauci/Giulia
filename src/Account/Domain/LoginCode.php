<?php
namespace App\Account\Domain;

/**
 * Code de connexion à usage unique envoyé par e-mail.
 *
 * Le code en clair ne vit que le temps de l'envoi : seul son hachage est
 * conservé. L'espace de recherche est petit (10⁶), le hachage ne protège donc
 * pas d'une attaque hors ligne — il évite qu'une fuite de la base livre des
 * codes rejouables tant qu'ils sont valides. Le vrai garde-fou est le plafond
 * d'essais, tenu par `tries()`.
 */
final readonly class LoginCode
{
    public function __construct(
        private string $email,
        private string $codeHash,
        private \DateTimeImmutable $expiresAt,
        private \DateTimeImmutable $createdAt,
        private int $tries = 0,
        private ?\DateTimeImmutable $consumedAt = null,
    ) {}

    public static function issue(string $email, string $code, \DateTimeImmutable $now, int $lifetimeMinutes): self
    {
        return new self(
            $email,
            password_hash($code, PASSWORD_DEFAULT),
            $now->modify(sprintf('+%d minutes', $lifetimeMinutes)),
            $now,
        );
    }

    public function matches(string $candidate): bool
    {
        return password_verify($candidate, $this->codeHash);
    }

    /** Le code reste valable pendant toute la minute de son échéance. */
    public function isExpired(\DateTimeImmutable $at): bool
    {
        return $at > $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function withFailedTry(): self
    {
        return new self($this->email, $this->codeHash, $this->expiresAt, $this->createdAt, $this->tries + 1, $this->consumedAt);
    }

    public function consume(\DateTimeImmutable $at): self
    {
        return new self($this->email, $this->codeHash, $this->expiresAt, $this->createdAt, $this->tries, $at);
    }

    public function email(): string { return $this->email; }
    public function codeHash(): string { return $this->codeHash; }
    public function expiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function tries(): int { return $this->tries; }
    public function consumedAt(): ?\DateTimeImmutable { return $this->consumedAt; }
}
