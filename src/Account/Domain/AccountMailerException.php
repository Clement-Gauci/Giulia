<?php
namespace App\Account\Domain;

/**
 * Levée quand un e-mail du contexte Account n'a pas pu être remis au transport
 * (SMTP indisponible, authentification refusée, timeout…).
 */
final class AccountMailerException extends \RuntimeException
{
}
