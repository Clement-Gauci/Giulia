<?php
namespace App\Account\Domain;

interface LoginCodeMailerInterface
{
    /**
     * @throws AccountMailerException si le message n'a pas pu être remis au transport
     */
    public function sendLoginCode(Account $account, string $code, \DateTimeImmutable $expiresAt): void;
}
