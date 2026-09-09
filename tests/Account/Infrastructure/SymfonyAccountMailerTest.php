<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Domain\Account;
use App\Account\Domain\AccountMailerException;
use App\Account\Domain\AccountRole;
use App\Account\Infrastructure\SymfonyAccountMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SymfonyAccountMailerTest extends TestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private function account(): Account
    {
        return Account::create(self::EMAIL, 'Clément', AccountRole::Manager, new \DateTimeImmutable());
    }

    public function test_it_tells_the_person_where_to_log_in(): void
    {
        $spy = $this->spy();

        (new SymfonyAccountMailer($spy, $this->urls(), 'hello@giulia-pizza-gorges.fr'))
            ->sendAccountCreated($this->account());

        self::assertInstanceOf(TemplatedEmail::class, $spy->sent);
        self::assertSame(self::EMAIL, $spy->sent->getTo()[0]->getAddress());
        self::assertSame('emails/account_created.html.twig', $spy->sent->getHtmlTemplate());
        self::assertSame('https://giulia-pizza-gorges.fr/admin/connexion', $spy->sent->getContext()['login_url']);
        self::assertSame($this->account()->email(), $spy->sent->getContext()['account']->email());
    }

    public function test_a_transport_failure_becomes_a_domain_exception(): void
    {
        $mailer = new SymfonyAccountMailer(
            new class implements MailerInterface {
                public function send(RawMessage $message, ?Envelope $envelope = null): void
                {
                    throw new TransportException('SMTP down');
                }
            },
            $this->urls(),
            'hello@giulia-pizza-gorges.fr',
        );

        $this->expectException(AccountMailerException::class);
        $mailer->sendAccountCreated($this->account());
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

    private function urls(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return 'https://giulia-pizza-gorges.fr/admin/connexion';
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }
        };
    }
}
