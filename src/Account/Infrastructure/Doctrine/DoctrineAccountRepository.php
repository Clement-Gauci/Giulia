<?php
namespace App\Account\Infrastructure\Doctrine;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRepositoryInterface;
use App\Account\Domain\AccountRole;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccountRepository implements AccountRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findByEmail(string $email): ?Account
    {
        $entity = $this->entity($email);

        return $entity === null ? null : self::toDomain($entity);
    }

    public function exists(string $email): bool
    {
        return $this->entity($email) !== null;
    }

    public function save(Account $account): void
    {
        $entity = $this->entity($account->email()) ?? new AccountEntity();

        $entity->email = $account->email();
        $entity->name = $account->name();
        $entity->role = $account->role()->value;
        $entity->active = $account->isActive();
        $entity->createdAt = $account->createdAt();
        $entity->lastLoginAt = $account->lastLoginAt();

        $this->em->persist($entity);
        $this->em->flush();
    }

    private function entity(string $email): ?AccountEntity
    {
        return $this->em->getRepository(AccountEntity::class)
            ->findOneBy(['email' => strtolower(trim($email))]);
    }

    private static function toDomain(AccountEntity $entity): Account
    {
        return new Account(
            $entity->email,
            $entity->name,
            AccountRole::from($entity->role),
            $entity->active,
            $entity->createdAt,
            $entity->lastLoginAt,
        );
    }
}
