<?php
namespace App\Account\Domain;

enum AccountRole: string
{
    /** Compte partagé du poste de la pizzeria. */
    case Shop = 'shop';

    /** Compte nominatif d'un gérant. */
    case Manager = 'manager';
}
