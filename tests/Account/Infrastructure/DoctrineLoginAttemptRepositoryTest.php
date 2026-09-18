<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\AttemptKind;
use App\Account\Domain\LoginAttempt;
use App\Account\Infrastructure\Doctrine\DoctrineLoginAttemptRepository;
use App\Account\Infrastructure\Doctrine\LoginAttemptEntity;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\FrozenClock;

final class DoctrineLoginAttemptRepositoryTest extends DatabaseTestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';
    private const IP = '203.0.113.7';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2026-09-09 10:00:00');
    }

    private function repository(): DoctrineLoginAttemptRepository
    {
        return new DoctrineLoginAttemptRepository($this->em, new FrozenClock($this->now));
    }

    public function test_it_counts_only_the_matching_kind_and_address(): void
    {
        $repository = $this->repository();
        $repository->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-5 minutes')));
        $repository->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-1 minute')));
        $repository->record(LoginAttempt::wrongCode('autre@example.com', self::IP, $this->now));
        $repository->record(LoginAttempt::codeSent(self::EMAIL, self::IP, $this->now));

        $tally = $repository->tallyForEmail(AttemptKind::WrongCode, self::EMAIL, $this->now->modify('-15 minutes'));

        self::assertSame(2, $tally->count);
        self::assertSame($this->now->modify('-1 minute')->getTimestamp(), $tally->lastAt?->getTimestamp());
    }

    public function test_it_ignores_what_falls_outside_the_window(): void
    {
        $repository = $this->repository();
        $repository->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-20 minutes')));

        $tally = $repository->tallyForEmail(AttemptKind::WrongCode, self::EMAIL, $this->now->modify('-15 minutes'));

        self::assertSame(0, $tally->count);
        self::assertNull($tally->lastAt);
    }

    public function test_it_counts_by_ip_across_addresses(): void
    {
        $repository = $this->repository();
        $repository->record(LoginAttempt::unknownEmail('un@example.com', self::IP, $this->now));
        $repository->record(LoginAttempt::unknownEmail('deux@example.com', self::IP, $this->now));
        $repository->record(LoginAttempt::unknownEmail('trois@example.com', '198.51.100.4', $this->now));

        self::assertSame(2, $repository->tallyForIp(AttemptKind::UnknownEmail, self::IP, $this->now->modify('-15 minutes'))->count);
    }

    public function test_recording_purges_the_stale_rows(): void
    {
        $repository = $this->repository();
        $repository->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-2 days')));
        $repository->record(LoginAttempt::wrongCode(self::EMAIL, self::IP, $this->now->modify('-1 hour')));

        self::assertCount(1, $this->em->getRepository(LoginAttemptEntity::class)->findAll());
    }
}
