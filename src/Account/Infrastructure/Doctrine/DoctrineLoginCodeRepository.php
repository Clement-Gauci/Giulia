<?php
namespace App\Account\Infrastructure\Doctrine;

use App\Account\Domain\LoginCode;
use App\Account\Domain\LoginCodeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLoginCodeRepository implements LoginCodeRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function save(LoginCode $code): void
    {
        $account = $this->account($code->email());

        if ($account === null) {
            throw new \LogicException(sprintf('Aucun compte pour « %s » : impossible d\'y rattacher un code.', $code->email()));
        }

        $entity = $this->entity($code->email()) ?? new LoginCodeEntity();

        $entity->account = $account;
        $entity->codeHash = $code->codeHash();
        $entity->expiresAt = $code->expiresAt();
        $entity->createdAt = $code->createdAt();
        $entity->consumedAt = $code->consumedAt();
        $entity->tries = $code->tries();

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findFor(string $email): ?LoginCode
    {
        $entity = $this->entity($email);

        return $entity === null ? null : new LoginCode(
            $entity->account->email,
            $entity->codeHash,
            $entity->expiresAt,
            $entity->createdAt,
            $entity->tries,
            $entity->consumedAt,
        );
    }

    public function deleteFor(string $email): void
    {
        $entity = $this->entity($email);

        if ($entity !== null) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

    private function entity(string $email): ?LoginCodeEntity
    {
        $account = $this->account($email);

        return $account === null
            ? null
            : $this->em->getRepository(LoginCodeEntity::class)->findOneBy(['account' => $account]);
    }

    private function account(string $email): ?AccountEntity
    {
        return $this->em->getRepository(AccountEntity::class)
            ->findOneBy(['email' => strtolower(trim($email))]);
    }
}
