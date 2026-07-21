<?php
namespace App\Contact\Domain;

interface ContactMailerInterface
{
    public function send(ContactMessage $message): void;
}
