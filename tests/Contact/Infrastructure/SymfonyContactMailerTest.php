<?php
namespace App\Tests\Contact\Infrastructure;

use App\Contact\Domain\ContactMailerException;
use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use App\Contact\Infrastructure\SymfonyContactMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope as MailerEnvelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope as MessengerEnvelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class SymfonyContactMailerTest extends TestCase
{
    private function message(): ContactMessage
    {
        return new ContactMessage('Marie', 'marie@example.fr', null, Subject::Other, 'Bonjour');
    }

    public function test_it_sends_the_email_through_symfony_mailer(): void
    {
        $spy = new class implements MailerInterface {
            public ?RawMessage $sent = null;
            public function send(RawMessage $message, ?MailerEnvelope $envelope = null): void { $this->sent = $message; }
        };

        $mailer = new SymfonyContactMailer($spy, 'no-reply@giulia.fr', 'hello@giulia.fr');
        $mailer->send($this->message());

        self::assertInstanceOf(Email::class, $spy->sent);
    }

    public function test_transport_failure_is_translated_to_a_domain_exception(): void
    {
        $mailer = new SymfonyContactMailer($this->throwingMailer(new TransportException('SMTP down')), 'no-reply@giulia.fr', 'hello@giulia.fr');

        $this->expectException(ContactMailerException::class);
        $mailer->send($this->message());
    }

    public function test_messenger_wrapped_failure_is_translated_too(): void
    {
        // Envoi synchrone via le bus Messenger : l'exception transport est enveloppée.
        $wrapped = new HandlerFailedException(new MessengerEnvelope(new \stdClass()), [new TransportException('SMTP down')]);
        $mailer = new SymfonyContactMailer($this->throwingMailer($wrapped), 'no-reply@giulia.fr', 'hello@giulia.fr');

        $this->expectException(ContactMailerException::class);
        $mailer->send($this->message());
    }

    private function throwingMailer(\Throwable $e): MailerInterface
    {
        return new class($e) implements MailerInterface {
            public function __construct(private \Throwable $e) {}
            public function send(RawMessage $message, ?MailerEnvelope $envelope = null): void { throw $this->e; }
        };
    }
}
