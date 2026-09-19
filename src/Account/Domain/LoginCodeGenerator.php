<?php
namespace App\Account\Domain;

interface LoginCodeGenerator
{
    /** Un code à 6 chiffres, zéros de tête compris. */
    public function generate(): string;
}
