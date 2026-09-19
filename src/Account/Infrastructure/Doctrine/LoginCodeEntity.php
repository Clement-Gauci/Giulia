<?php
namespace App\Account\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Le code de connexion courant d'un compte. L'unicité sur `account_id` matérialise
 * la règle « une adresse n'a jamais qu'un seul code valable » : un nouvel envoi
 * remplace le précédent.
 */
#[ORM\Entity]
#[ORM\Table(name: 'login_code')]
#[ORM\UniqueConstraint(name: 'uniq_login_code_account', columns: ['account_id'])]
class LoginCodeEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AccountEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public AccountEntity $account;

    #[ORM\Column(length: 255)]
    public string $codeHash;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $consumedAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    public int $tries = 0;
}
