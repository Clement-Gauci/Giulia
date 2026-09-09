<?php
namespace App\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\AccountMailerException;
use App\Account\Domain\AccountMailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SymfonyAccountMailer implements AccountMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private string $fromEmail,
    ) {}

    public function sendAccountCreated(Account $account): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($account->email())
            ->subject('Votre accès au dashboard Giulia')
            ->htmlTemplate('emails/account_created.html.twig')
            ->context([
                'account' => $account,
                // URL absolue : l'e-mail est lu hors de toute requête HTTP, et la
                // commande de création tourne en CLI (d'où DEFAULT_URI dans .env).
                'login_url' => $this->urls->generate('admin_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            throw new AccountMailerException("Échec de la remise de l'e-mail d'ouverture d'accès.", previous: $e);
        }
    }
}
