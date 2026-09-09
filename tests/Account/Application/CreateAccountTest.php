<?php
namespace App\Tests\Account\Application;

use App\Account\Application\CreateAccount;
use App\Account\Domain\AccountAlreadyExists;
use App\Account\Domain\AccountMailerException;
use App\Account\Domain\AccountRole;
use App\Tests\Account\Support\InMemoryAccountRepository;
use App\Tests\Account\Support\RecordingAccountMailer;
use App\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class CreateAccountTest extends TestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('2026-09-09 10:00:00'));
    }

    public function test_it_creates_an_active_account_and_notifies_the_person(): void
    {
        $accounts = new InMemoryAccountRepository();
        $mailer = new RecordingAccountMailer();

        $account = (new CreateAccount($accounts, $mailer, $this->clock()))(self::EMAIL, 'Clément', AccountRole::Manager, notify: true);

        self::assertSame(self::EMAIL, $account->email());
        self::assertTrue($account->isActive());
        self::assertSame(AccountRole::Manager, $account->role());
        self::assertSame([$account], $mailer->sent);
        self::assertSame(self::EMAIL, $accounts->findByEmail(self::EMAIL)?->email());
    }

    public function test_it_can_create_without_notifying(): void
    {
        $mailer = new RecordingAccountMailer();

        (new CreateAccount(new InMemoryAccountRepository(), $mailer, $this->clock()))(self::EMAIL, 'Clément', AccountRole::Manager, notify: false);

        self::assertSame([], $mailer->sent);
    }

    public function test_it_refuses_an_address_already_taken_whatever_its_case(): void
    {
        $accounts = new InMemoryAccountRepository();
        $create = new CreateAccount($accounts, new RecordingAccountMailer(), $this->clock());
        $create(self::EMAIL, 'Clément', AccountRole::Manager, notify: false);

        $this->expectException(AccountAlreadyExists::class);
        $create('Gerant@Giulia-Pizza-Gorges.FR', 'Clément bis', AccountRole::Shop, notify: false);
    }

    public function test_a_failed_notification_does_not_lose_the_account(): void
    {
        $accounts = new InMemoryAccountRepository();
        $create = new CreateAccount($accounts, RecordingAccountMailer::failing(), $this->clock());

        try {
            $create(self::EMAIL, 'Clément', AccountRole::Manager, notify: true);
            self::fail("L'échec d'envoi devait remonter.");
        } catch (AccountMailerException) {
            // L'accès ne doit pas dépendre de la remise SMTP : le compte reste créé
            // et c'est à l'appelant (la commande) de le signaler.
        }

        self::assertNotNull($accounts->findByEmail(self::EMAIL));
    }
}
