<?php
namespace App\Contact\Infrastructure;

use App\Contact\Domain\ContactMailerException;
use App\Contact\Domain\ContactMailerInterface;
use App\Contact\Domain\ContactMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SymfonyContactMailer implements ContactMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $toEmail,
    ) {}

    public function send(ContactMessage $message): void
    {
        $body = sprintf(
            "Nom : %s\nE-mail : %s\nTéléphone : %s\nSujet : %s\n\n%s",
            $message->name(),
            $message->email(),
            $message->phone() ?? '—',
            $message->subject()->label(),
            $message->message(),
        );

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($this->toEmail)
            ->replyTo($message->email())
            ->subject(sprintf('[%s] %s', $message->subject()->label(), $message->name()))
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Frontière d'infrastructure : tout échec de remise (transport SMTP,
            // exception enveloppée par le bus Messenger…) est traduit en exception
            // du domaine pour que la couche UI puisse le gérer sans exposer de 500.
            throw new ContactMailerException('Échec de la remise du message de contact.', previous: $e);
        }
    }
}
