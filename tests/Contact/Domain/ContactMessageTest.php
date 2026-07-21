<?php
namespace App\Tests\Contact\Domain;

use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use PHPUnit\Framework\TestCase;

final class ContactMessageTest extends TestCase
{
    public function test_builds_a_valid_message(): void
    {
        $m = new ContactMessage('Marie', 'marie@example.fr', '0612345678', Subject::ClickAndCollect, 'Bonjour');
        self::assertSame('Marie', $m->name());
        self::assertSame(Subject::ClickAndCollect, $m->subject());
        self::assertSame('0612345678', $m->phone());
    }

    public function test_phone_is_optional(): void
    {
        $m = new ContactMessage('Marie', 'marie@example.fr', null, Subject::Other, 'Bonjour');
        self::assertNull($m->phone());
    }

    public function test_rejects_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContactMessage('  ', 'marie@example.fr', null, Subject::Other, 'Bonjour');
    }

    public function test_rejects_invalid_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContactMessage('Marie', 'pas-un-email', null, Subject::Other, 'Bonjour');
    }

    public function test_subject_choices_map_label_to_value(): void
    {
        $choices = Subject::choices();
        self::assertSame('general', $choices['Une question générale']);
    }
}
