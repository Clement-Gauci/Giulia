<?php
namespace App\Account\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'une tentative de connexion. Pas de clé étrangère vers `account` :
 * la plupart des lignes concernent justement des adresses inconnues, et une
 * tentative doit survivre à la suppression du compte visé.
 */
#[ORM\Entity]
#[ORM\Table(name: 'login_attempt')]
#[ORM\Index(name: 'idx_login_attempt_email', columns: ['kind', 'email', 'created_at'])]
#[ORM\Index(name: 'idx_login_attempt_ip', columns: ['kind', 'ip', 'created_at'])]
class LoginAttemptEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 20)]
    public string $kind;

    #[ORM\Column(length: 180, nullable: true)]
    public ?string $email = null;

    /** 45 caractères : de quoi loger une IPv6 encadrée par des crochets. */
    #[ORM\Column(length: 45)]
    public string $ip;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;
}
