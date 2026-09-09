<?php
namespace App\Account\Infrastructure\Security;

use App\Account\Domain\AccountRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<AccountUser>
 */
final readonly class AccountUserProvider implements UserProviderInterface
{
    public function __construct(private AccountRepositoryInterface $accounts) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $account = $this->accounts->findByEmail($identifier);

        // Un compte désactivé est traité comme inexistant : c'est ce qui fait
        // qu'une révocation coupe aussi les sessions et les cookies en cours,
        // puisque `refreshUser` passe par ici à chaque requête.
        if ($account === null || !$account->isActive()) {
            throw new UserNotFoundException(sprintf('Aucun compte actif pour « %s ».', $identifier));
        }

        return new AccountUser($account);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AccountUser) {
            throw new UnsupportedUserException(sprintf('Utilisateur inattendu : %s.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === AccountUser::class || is_subclass_of($class, AccountUser::class);
    }
}
