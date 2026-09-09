<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRole;
use App\Account\Infrastructure\Doctrine\AccountEntity;
use App\Account\Infrastructure\Doctrine\DoctrineAccountRepository;
use App\Tests\Support\DatabaseTestCase;

final class DoctrineAccountRepositoryTest extends DatabaseTestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private function repository(): DoctrineAccountRepository
    {
        return new DoctrineAccountRepository($this->em);
    }

    public function test_it_stores_and_restores_every_field(): void
    {
        $created = new \DateTimeImmutable('2026-09-09 10:00:00');
        $repository = $this->repository();

        $repository->save(new Account(self::EMAIL, 'Clément', AccountRole::Manager, true, $created, $created->modify('+1 hour')));
        $this->em->clear();

        $account = $repository->findByEmail(self::EMAIL);

        self::assertNotNull($account);
        self::assertSame(self::EMAIL, $account->email());
        self::assertSame('Clément', $account->name());
        self::assertSame(AccountRole::Manager, $account->role());
        self::assertTrue($account->isActive());
        self::assertSame($created->getTimestamp(), $account->createdAt()->getTimestamp());
        self::assertSame($created->modify('+1 hour')->getTimestamp(), $account->lastLoginAt()?->getTimestamp());
    }

    public function test_an_unknown_address_returns_nothing(): void
    {
        self::assertNull($this->repository()->findByEmail('personne@example.com'));
        self::assertFalse($this->repository()->exists('personne@example.com'));
    }

    public function test_saving_twice_updates_the_same_account(): void
    {
        $repository = $this->repository();
        $now = new \DateTimeImmutable('2026-09-09 10:00:00');
        $account = Account::create(self::EMAIL, 'Clément', AccountRole::Manager, $now);

        $repository->save($account);
        $repository->save($account->withLastLoginAt($now->modify('+5 minutes')));
        $this->em->clear();

        self::assertSame(
            $now->modify('+5 minutes')->getTimestamp(),
            $repository->findByEmail(self::EMAIL)?->lastLoginAt()?->getTimestamp(),
        );
        self::assertCount(1, $this->em->getRepository(AccountEntity::class)->findAll());
    }

    public function test_lookup_tolerates_a_different_case(): void
    {
        $this->repository()->save(Account::create(self::EMAIL, 'Clément', AccountRole::Manager, new \DateTimeImmutable()));
        $this->em->clear();

        self::assertTrue($this->repository()->exists('Gerant@Giulia-Pizza-Gorges.FR'));
    }
}
