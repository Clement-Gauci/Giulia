<?php
namespace App\Account\Domain;

/**
 * Les règles de cadence et de blocage de la connexion, reprises telles quelles
 * de la maquette `.claude/design-system/Connexion.dc.html`.
 */
final class LoginPolicy
{
    /** Durée de vie d'un code, et fenêtre sur laquelle le plafond d'envois se calcule. */
    public const int CODE_LIFETIME_MINUTES = 10;

    /** Délai imposé entre deux envois pour une même adresse. */
    public const int RESEND_DELAY_SECONDS = 45;

    /** Un envoi initial plus trois renvois, sur la durée de vie d'un code. */
    public const int MAX_SENDS_PER_WINDOW = 4;

    /** Codes erronés tolérés pour une adresse avant blocage. */
    public const int MAX_CODE_TRIES = 3;

    /** Adresses non enregistrées tolérées depuis une IP avant blocage. */
    public const int MAX_UNKNOWN_EMAILS = 3;

    /** Durée du blocage, comptée depuis la dernière tentative fautive. */
    public const int BLOCK_MINUTES = 15;
}
