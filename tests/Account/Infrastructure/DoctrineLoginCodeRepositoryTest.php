<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\LoginCode;
use App\Account\Infrastructure\Doctrine\DoctrineAccountRepository;
use App\Account\Infrastructure\Doctrine\DoctrineLoginCodeRepository;
use App\Account\Infrastructure\Doctrine\LoginCodeEntity;
use App\Tests\Support\DatabaseTestCase;

final class DoctrineLoginCodeRepositoryTest extends DatabaseTestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTimeImmutable('2026-09-09 10:00:00');
        (new DoctrineAccountRepository($this->em))->save(
            Account::create(self::EMAIL, 'Clément', $this->now),
        );
    }

    private function repository(): DoctrineLoginCodeRepository
    {
        return new DoctrineLoginCodeRepository($this->em);
    }

    public function test_it_stores_and_restores_every_field(): void
    {
        $repository = $this->repository();
        $repository->save(LoginCode::issue(self::EMAIL, '204815', $this->now, 10)->withFailedTry());
        $this->em->clear();

        $code = $repository->findFor(self::EMAIL);

        self::assertNotNull($code);
        self::assertTrue($code->matches('204815'));
        self::assertSame(1, $code->tries());
        self::assertFalse($code->isConsumed());
        self::assertSame($this->now->modify('+10 minutes')->getTimestamp(), $code->expiresAt()->getTimestamp());
        self::assertSame(self::EMAIL, $code->email());
    }

    public function test_it_keeps_the_consumption_instant(): void
    {
        $repository = $this->repository();
        $repository->save(LoginCode::issue(self::EMAIL, '204815', $this->now, 10)->consume($this->now->modify('+1 minute')));
        $this->em->clear();

        self::assertTrue($repository->findFor(self::EMAIL)?->isConsumed());
    }

    public function test_a_new_code_replaces_the_previous_one(): void
    {
        $repository = $this->repository();
        $repository->save(LoginCode::issue(self::EMAIL, '111111', $this->now, 10));
        $repository->save(LoginCode::issue(self::EMAIL, '222222', $this->now->modify('+1 minute'), 10));
        $this->em->clear();

        self::assertTrue($repository->findFor(self::EMAIL)?->matches('222222'));
        self::assertCount(1, $this->em->getRepository(LoginCodeEntity::class)->findAll());
    }

    public function test_deleting_leaves_nothing(): void
    {
        $repository = $this->repository();
        $repository->save(LoginCode::issue(self::EMAIL, '204815', $this->now, 10));
        $repository->deleteFor(self::EMAIL);
        $this->em->clear();

        self::assertNull($repository->findFor(self::EMAIL));
    }

    public function test_an_address_without_code_returns_nothing(): void
    {
        self::assertNull($this->repository()->findFor('personne@example.com'));
    }
}
