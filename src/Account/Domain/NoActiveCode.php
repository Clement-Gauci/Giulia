<?php
namespace App\Account\Domain;

/**
 * Aucun code utilisable pour cette adresse : jamais émis, déjà consommé, expiré,
 * ou compte révoqué depuis l'envoi. Les quatre cas se confondent volontairement —
 * la réponse à donner est la même, et distinguer un compte révoqué renseignerait
 * un attaquant.
 */
final class NoActiveCode extends LoginRefused
{
    public static function forEmail(): self
    {
        return new self('Aucun code valable en cours — demandez-en un nouveau.');
    }
}
