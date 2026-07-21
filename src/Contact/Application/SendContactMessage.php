<?php
namespace App\Contact\Application;

use App\Contact\Domain\ContactMailerInterface;
use App\Contact\Domain\ContactMessage;

final readonly class SendContactMessage
{
    public function __construct(private ContactMailerInterface $mailer) {}

    public function __invoke(ContactMessage $message): void
    {
        $this->mailer->send($message);
    }
}
