<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\AccountMailerException;
use App\Account\Infrastructure\SymfonyLoginCodeMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class SymfonyLoginCodeMailerTest extends TestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private function account(): Account
    {
        return Account::create(self::EMAIL, 'Clément', new \DateTimeImmutable());
    }

    public function test_it_mails_the_code_to_the_account_holder(): void
    {
        $spy = $this->spy();

        (new SymfonyLoginCodeMailer($spy, 'hello@giulia-pizza-gorges.fr'))
            ->sendLoginCode($this->account(), '204815', new \DateTimeImmutable('2026-09-09 10:00:00'), new \DateTimeImmutable('2026-09-09 10:10:00'));

        self::assertInstanceOf(TemplatedEmail::class, $spy->sent);
        self::assertSame(self::EMAIL, $spy->sent->getTo()[0]->getAddress());
        self::assertSame('hello@giulia-pizza-gorges.fr', $spy->sent->getFrom()[0]->getAddress());
        self::assertSame('emails/login_code.html.twig', $spy->sent->getHtmlTemplate());
        self::assertSame('204815', $spy->sent->getContext()['code']);
    }

    public function test_the_code_never_appears_in_the_subject(): void
    {
        $spy = $this->spy();

        (new SymfonyLoginCodeMailer($spy, 'hello@giulia-pizza-gorges.fr'))
            ->sendLoginCode($this->account(), '204815', new \DateTimeImmutable(), new \DateTimeImmutable('+10 minutes'));

        // Les sujets sont journalisés par les serveurs de messagerie : le code
        // n'a rien à y faire.
        self::assertStringNotContainsString('204815', (string) $spy->sent?->getSubject());
    }

    public function test_a_transport_failure_becomes_a_domain_exception(): void
    {
        $mailer = new SymfonyLoginCodeMailer(
            new class implements MailerInterface {
                public function send(RawMessage $message, ?Envelope $envelope = null): void
                {
                    throw new TransportException('SMTP down');
                }
            },
            'hello@giulia-pizza-gorges.fr',
        );

        $this->expectException(AccountMailerException::class);
        $mailer->sendLoginCode($this->account(), '204815', new \DateTimeImmutable(), new \DateTimeImmutable('+10 minutes'));
    }

    private function spy(): MailerInterface
    {
        return new class implements MailerInterface {
            public ?TemplatedEmail $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent = $message;
            }
        };
    }
}
