<?php
namespace App\Contact\UI;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactFormData
{
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Indiquez votre e-mail.')]
    #[Assert\Email(message: 'E-mail invalide.')]
    public string $email = '';

    public ?string $phone = null;

    #[Assert\NotBlank]
    public ?string $subject = 'general';

    #[Assert\NotBlank(message: 'Écrivez votre message.')]
    #[Assert\Length(min: 5, minMessage: 'Message trop court.')]
    public ?string $message = '';
}
