<?php
namespace App\Contact\AntiSpam;

enum TokenVerdict
{
    /** Jeton authentique, rempli à une vitesse humaine. */
    case Ok;
    /** Absent, malformé, falsifié ou signé ailleurs : la page n'a pas été rendue ici. */
    case Invalid;
    /** Renvoyé trop vite après l'affichage du formulaire. */
    case TooFast;
    /** Page restée ouverte trop longtemps (onglet oublié, cache). */
    case Expired;
}
