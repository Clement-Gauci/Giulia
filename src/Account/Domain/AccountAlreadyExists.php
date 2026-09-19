<?php
namespace App\Account\Domain;

final class AccountAlreadyExists extends \DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('Un compte existe déjà pour « %s ».', $email));
    }
}
