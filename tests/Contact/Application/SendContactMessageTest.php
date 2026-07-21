<?php
namespace App\Tests\Contact\Application;

use App\Contact\Application\SendContactMessage;
use App\Contact\Domain\ContactMailerInterface;
use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use PHPUnit\Framework\TestCase;

final class SendContactMessageTest extends TestCase
{
    public function test_it_sends_the_message_through_the_port(): void
    {
        $spy = new class implements ContactMailerInterface {
            public ?ContactMessage $sent = null;
            public function send(ContactMessage $message): void { $this->sent = $message; }
        };

        $handler = new SendContactMessage($spy);
        $message = new ContactMessage('Marie', 'marie@example.fr', null, Subject::ClickAndCollect, 'Bonjour');
        $handler($message);

        self::assertSame($message, $spy->sent);
    }
}
