<?php
namespace App\Contact\Domain;

/**
 * Levée quand le message de contact n'a pas pu être remis au transport
 * (SMTP indisponible, authentification refusée, timeout…).
 */
final class ContactMailerException extends \RuntimeException
{
}
