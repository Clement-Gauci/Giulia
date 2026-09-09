<?php
namespace App\Account\Domain;

final readonly class Account
{
    /**
     * Le constructeur suppose une adresse déjà normalisée : il valide sans
     * transformer, de sorte qu'une ligne corrompue en base lève au chargement.
     * La normalisation est le rôle de `create()`, seule porte d'entrée d'un
     * compte neuf.
     */
    public function __construct(
        private string $email,
        private string $name,
        private AccountRole $role,
        private bool $active,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $lastLoginAt = null,
    ) {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('Adresse e-mail invalide : « %s ».', $this->email));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Le nom du compte ne peut pas être vide.');
        }
    }

    public static function create(string $email, string $name, AccountRole $role, \DateTimeImmutable $now): self
    {
        return new self(strtolower(trim($email)), trim($name), $role, true, $now);
    }

    public function withLastLoginAt(\DateTimeImmutable $at): self
    {
        return new self($this->email, $this->name, $this->role, $this->active, $this->createdAt, $at);
    }

    public function email(): string { return $this->email; }
    public function name(): string { return $this->name; }
    public function role(): AccountRole { return $this->role; }
    public function isActive(): bool { return $this->active; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function lastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
}
