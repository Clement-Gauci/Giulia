<?php
namespace App\Account\Domain;

/**
 * Trace d'une tentative de connexion. Persistée plutôt que gardée en session :
 * un blocage qu'on contourne en vidant ses cookies ne bloque rien.
 */
final readonly class LoginAttempt
{
    public function __construct(
        private AttemptKind $kind,
        private ?string $email,
        private string $ip,
        private \DateTimeImmutable $at,
    ) {}

    public static function unknownEmail(string $email, string $ip, \DateTimeImmutable $at): self
    {
        return new self(AttemptKind::UnknownEmail, $email, $ip, $at);
    }

    public static function wrongCode(string $email, string $ip, \DateTimeImmutable $at): self
    {
        return new self(AttemptKind::WrongCode, $email, $ip, $at);
    }

    public static function codeSent(string $email, string $ip, \DateTimeImmutable $at): self
    {
        return new self(AttemptKind::CodeSent, $email, $ip, $at);
    }

    public function kind(): AttemptKind { return $this->kind; }
    public function email(): ?string { return $this->email; }
    public function ip(): string { return $this->ip; }
    public function at(): \DateTimeImmutable { return $this->at; }
}
