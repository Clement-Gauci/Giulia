<?php
namespace App\Account\Infrastructure\Doctrine;

use App\Account\Domain\AttemptKind;
use App\Account\Domain\AttemptTally;
use App\Account\Domain\LoginAttempt;
use App\Account\Domain\LoginAttemptRepositoryInterface;
use App\Shared\Domain\Clock;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /**
     * Au-delà, une tentative n'intéresse plus aucune règle (la plus longue
     * fenêtre est le blocage, 15 minutes) : on garde une journée pour laisser
     * une piste d'audit lisible, et rien de plus.
     */
    private const string RETENTION = '-1 day';

    /**
     * Type Doctrine des instants. À passer explicitement à chaque paramètre de
     * requête : déduit de la valeur PHP, Doctrine choisirait `datetime` et
     * perdrait le décalage horaire.
     */
    private const string INSTANT = 'datetimetz_immutable';

    public function __construct(private EntityManagerInterface $em, private Clock $clock) {}

    public function record(LoginAttempt $attempt): void
    {
        $entity = new LoginAttemptEntity();
        $entity->kind = $attempt->kind()->value;
        $entity->email = $attempt->email();
        $entity->ip = $attempt->ip();
        $entity->createdAt = $attempt->at();

        $this->em->persist($entity);
        $this->em->flush();

        $this->purge();
    }

    public function tallyForEmail(AttemptKind $kind, string $email, \DateTimeImmutable $since): AttemptTally
    {
        return $this->tally($kind, $since, 'a.email = :value', strtolower(trim($email)));
    }

    public function tallyForIp(AttemptKind $kind, string $ip, \DateTimeImmutable $since): AttemptTally
    {
        return $this->tally($kind, $since, 'a.ip = :value', $ip);
    }

    private function tally(AttemptKind $kind, \DateTimeImmutable $since, string $criterion, string $value): AttemptTally
    {
        /** @var array{count: int|string, lastAt: \DateTimeImmutable|string|null} $row */
        $row = $this->em->createQueryBuilder()
            ->select('COUNT(a.id) AS count', 'MAX(a.createdAt) AS lastAt')
            ->from(LoginAttemptEntity::class, 'a')
            ->where('a.kind = :kind')
            ->andWhere($criterion)
            ->andWhere('a.createdAt >= :since')
            ->setParameter('kind', $kind->value)
            ->setParameter('value', $value)
            // Type explicite, sans quoi Doctrine déduit `datetime` de la valeur PHP
            // et envoie l'instant SANS son décalage : PostgreSQL le lit alors dans
            // le fuseau de sa session, et toute la fenêtre glisse d'autant.
            ->setParameter('since', $since, self::INSTANT)
            ->getQuery()
            ->getSingleResult();

        $lastAt = $row['lastAt'];

        return new AttemptTally(
            (int) $row['count'],
            $lastAt === null ? null : $this->intoApplicationTimezone((string) $lastAt),
        );
    }

    /**
     * L'instant lu porte le décalage de la session PostgreSQL, qui n'a aucune
     * raison d'être celui de l'application : un Debian neuf installe la base en
     * UTC. On le ramène donc au fuseau de l'horloge — la même heure, mais
     * exprimée comme la lit la pizzeria. Sans quoi un message bâti dessus
     * annonce l'heure du serveur de base.
     */
    private function intoApplicationTimezone(string $instant): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($instant))->setTimezone($this->clock->now()->getTimezone());
    }

    private function purge(): void
    {
        $this->em->createQueryBuilder()
            ->delete(LoginAttemptEntity::class, 'a')
            ->where('a.createdAt < :limit')
            ->setParameter('limit', $this->clock->now()->modify(self::RETENTION), self::INSTANT)
            ->getQuery()
            ->execute();
    }
}
