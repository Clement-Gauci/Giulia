<?php
namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Socle des tests d'intégration : reconstruit le schéma de la base de test avant
 * chaque test, de sorte qu'aucun ne dépende de ce qu'un autre a laissé.
 *
 * La base visée est celle de `.env.test.local`, suffixée `_test` par
 * `config/packages/doctrine.yaml`. Elle doit exister :
 *   php bin/console doctrine:database:create --env=test
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }
}
