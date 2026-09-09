<?php
namespace App\Account\Infrastructure\Security;

use App\Account\Domain\LoginRefused;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Emballe un refus du domaine pour le faire traverser le pare-feu, qui n'accepte
 * que des `AuthenticationException`. Le refus d'origine reste accessible pour
 * que l'écran sache quoi afficher — essais restants, fin de blocage.
 */
final class LoginRefusedException extends AuthenticationException
{
    public function __construct(private readonly LoginRefused $refusal)
    {
        parent::__construct($refusal->getMessage(), previous: $refusal);
    }

    public function refusal(): LoginRefused
    {
        return $this->refusal;
    }
}
