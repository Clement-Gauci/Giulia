<?php
namespace App\Account\Domain;

interface AccountRepositoryInterface
{
    public function findByEmail(string $email): ?Account;

    public function exists(string $email): bool;

    /** Crée le compte s'il est inconnu, met à jour ses champs sinon. */
    public function save(Account $account): void;
}
