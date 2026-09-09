<?php
namespace App\Account\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Modèle de persistance d'un compte. Distinct de `App\Account\Domain\Account`,
 * qui est immuable : Doctrine a besoin de pouvoir réécrire ses propriétés.
 *
 * Les instants sont en `datetimetz_immutable` (donc `timestamptz` côté
 * PostgreSQL) : les fenêtres de blocage se comparent alors sur des instants
 * absolus, sans dépendre du fuseau de PHP ni des bascules d'heure d'été.
 */
#[ORM\Entity]
#[ORM\Table(name: 'account')]
class AccountEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    public string $email;

    #[ORM\Column(length: 120)]
    public string $name;

    #[ORM\Column(length: 20)]
    public string $role;

    #[ORM\Column]
    public bool $active = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $lastLoginAt = null;
}
