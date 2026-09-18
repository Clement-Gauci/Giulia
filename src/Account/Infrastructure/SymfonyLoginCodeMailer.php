<?php
namespace App\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\AccountMailerException;
use App\Account\Domain\LoginCodeMailerInterface;
use App\Account\Domain\LoginPolicy;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final readonly class SymfonyLoginCodeMailer implements LoginCodeMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
    ) {}

    public function sendLoginCode(Account $account, string $code, \DateTimeImmutable $requestedAt, \DateTimeImmutable $expiresAt): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($account->email())
            ->subject('Votre code de connexion au dashboard Giulia')
            ->htmlTemplate('emails/login_code.html.twig')
            ->context([
                'code' => $code,
                'account' => $account,
                'requested_at' => $requestedAt,
                'expires_at' => $expiresAt,
                'expires_in_minutes' => LoginPolicy::CODE_LIFETIME_MINUTES,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            throw new AccountMailerException('Échec de la remise du code de connexion.', previous: $e);
        }
    }
}
