<?php
namespace App\Account\Domain;

/**
 * Base commune des refus de connexion, pour que l'appelant puisse tous les
 * attraper d'un coup quand le détail lui est indifférent.
 */
abstract class LoginRefused extends \DomainException
{
}
