<?php
namespace App\Account\Infrastructure\Security;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRole;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptateur entre le domaine et Symfony Security. Il existe pour que
 * `Account` n'ait jamais à connaître le framework.
 *
 * `getRole()` et `isActive()` ne servent pas qu'à l'affichage : ce sont les
 * `signature_properties` du cookie « rester connecté » (voir security.yaml).
 * Changer le rôle d'un compte ou le désactiver invalide donc immédiatement
 * tous les cookies déjà émis.
 */
final readonly class AccountUser implements UserInterface
{
    public function __construct(private Account $account) {}

    public function getRoles(): array
    {
        return match ($this->account->role()) {
            AccountRole::Manager => ['ROLE_MANAGER'],
            AccountRole::Shop => ['ROLE_SHOP'],
        };
    }

    public function getUserIdentifier(): string
    {
        return $this->account->email();
    }

    public function getRole(): AccountRole
    {
        return $this->account->role();
    }

    public function isActive(): bool
    {
        return $this->account->isActive();
    }

    public function getName(): string
    {
        return $this->account->name();
    }

    public function account(): Account
    {
        return $this->account;
    }
}
