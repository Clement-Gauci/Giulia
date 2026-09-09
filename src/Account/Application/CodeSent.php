<?php
namespace App\Account\Application;

/**
 * Ce que l'écran de saisie du code a besoin de savoir après un envoi. Le
 * contrôleur le range en session : la vue rend alors le compte à rebours et le
 * nombre de renvois restants sans requête supplémentaire, et un rafraîchissement
 * de page ne renvoie pas de code.
 */
final readonly class CodeSent
{
    public function __construct(
        public string $email,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $resendAvailableAt,
        public int $sendsLeft,
    ) {}
}
