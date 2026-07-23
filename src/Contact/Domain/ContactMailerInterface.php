<?php
namespace App\Contact\Domain;

interface ContactMailerInterface
{
    /**
     * @throws ContactMailerException si le message n'a pas pu être remis au transport
     */
    public function send(ContactMessage $message): void;
}
