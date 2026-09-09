<?php
namespace App\Account\Domain;

/**
 * Plafond d'envois atteint pour l'adresse. Ce n'est pas un blocage : la limite
 * se relâche seule dès que le code courant expire.
 */
final class SendLimitReached extends LoginRefused
{
    public static function forWindow(): self
    {
        return new self(sprintf(
            'Limite de %d envois atteinte pour cette adresse. Réessayez dans %d minutes.',
            LoginPolicy::MAX_SENDS_PER_WINDOW,
            LoginPolicy::CODE_LIFETIME_MINUTES,
        ));
    }
}
