<?php
namespace App\Account\Domain;

interface AccountMailerInterface
{
    /**
     * Informe la personne qu'un accès au dashboard vient d'être ouvert pour elle.
     *
     * @throws AccountMailerException si le message n'a pas pu être remis au transport
     */
    public function sendAccountCreated(Account $account): void;
}
