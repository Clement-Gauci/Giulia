<?php
namespace App\Account\Infrastructure\Security;

use App\Account\Domain\Account;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptateur entre le domaine et Symfony Security. Il existe pour que
 * `Account` n'ait jamais à connaître le framework.
 *
 * `isActive()` ne sert pas qu'à l'affichage : c'est la `signature_properties`
 * du cookie « rester connecté » (voir security.yaml). Désactiver un compte
 * invalide donc immédiatement tous les cookies déjà émis.
 */
final readonly class AccountUser implements UserInterface
{
    public function __construct(private Account $account) {}

    /**
     * Un seul rôle : le dashboard ne sert qu'à modifier le contenu du site, et
     * qui y entre peut tout y faire. Symfony exige néanmoins un rôle nommé pour
     * que `access_control` ait quelque chose à exiger.
     */
    public function getRoles(): array
    {
        return ['ROLE_ADMIN'];
    }

    public function getUserIdentifier(): string
    {
        return $this->account->email();
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
