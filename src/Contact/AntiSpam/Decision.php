<?php
namespace App\Contact\AntiSpam;

enum Decision
{
    /** Message légitime : on l'envoie. */
    case Accept;
    /** Écarté sans rien envoyer, mais réponse indiscernable d'un succès. */
    case Silence;
    /** Cas honnête (page périmée) : on demande au visiteur de renvoyer. */
    case Retry;
}
