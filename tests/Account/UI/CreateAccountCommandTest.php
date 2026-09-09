<?php
namespace App\Tests\Account\UI;

use App\Account\Application\CreateAccount;
use App\Account\Domain\AccountRole;
use App\Account\UI\CreateAccountCommand;
use App\Tests\Account\Support\InMemoryAccountRepository;
use App\Tests\Account\Support\RecordingAccountMailer;
use App\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAccountCommandTest extends TestCase
{
    private const EMAIL = 'gerant@giulia-pizza-gorges.fr';

    private InMemoryAccountRepository $accounts;
    private RecordingAccountMailer $mailer;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryAccountRepository();
        $this->mailer = new RecordingAccountMailer();
    }

    private function tester(?RecordingAccountMailer $mailer = null): CommandTester
    {
        $create = new CreateAccount(
            $this->accounts,
            $mailer ?? $this->mailer,
            new FrozenClock(new \DateTimeImmutable('2026-09-09 10:00:00')),
        );

        return new CommandTester(new CreateAccountCommand($create));
    }

    public function test_it_creates_the_account_and_notifies(): void
    {
        $tester = $this->tester();

        $status = $tester->execute(['--email' => self::EMAIL, '--name' => 'Clément', '--role' => 'manager']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(AccountRole::Manager, $this->accounts->findByEmail(self::EMAIL)?->role());
        self::assertCount(1, $this->mailer->sent);
        self::assertStringContainsString(self::EMAIL, $tester->getDisplay());
    }

    public function test_the_shop_role_is_accepted(): void
    {
        $this->tester()->execute(['--email' => 'boutique@giulia-pizza-gorges.fr', '--name' => 'Boutique', '--role' => 'shop']);

        self::assertSame(AccountRole::Shop, $this->accounts->findByEmail('boutique@giulia-pizza-gorges.fr')?->role());
    }

    public function test_an_unknown_role_is_refused_without_creating_anything(): void
    {
        $tester = $this->tester();

        $status = $tester->execute(['--email' => self::EMAIL, '--name' => 'Clément', '--role' => 'patron']);

        self::assertSame(Command::INVALID, $status);
        self::assertNull($this->accounts->findByEmail(self::EMAIL));
    }

    public function test_an_invalid_address_is_refused(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--email' => 'pas-une-adresse', '--name' => 'Clément', '--role' => 'manager']));
        self::assertSame([], $this->mailer->sent);
    }

    public function test_a_duplicate_address_is_refused(): void
    {
        $this->tester()->execute(['--email' => self::EMAIL, '--name' => 'Clément', '--role' => 'manager']);

        $tester = $this->tester();
        $status = $tester->execute(['--email' => self::EMAIL, '--name' => 'Clément bis', '--role' => 'shop']);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame('Clément', $this->accounts->findByEmail(self::EMAIL)?->name());
    }

    public function test_sans_email_creates_without_notifying(): void
    {
        $tester = $this->tester();

        $status = $tester->execute(['--email' => self::EMAIL, '--name' => 'Clément', '--role' => 'manager', '--sans-email' => true]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertNotNull($this->accounts->findByEmail(self::EMAIL));
        self::assertSame([], $this->mailer->sent);
    }

    public function test_a_failed_notification_is_reported_but_the_account_stands(): void
    {
        $tester = $this->tester(RecordingAccountMailer::failing());

        $status = $tester->execute(['--email' => self::EMAIL, '--name' => 'Clément', '--role' => 'manager']);

        // Le compte est créé : l'accès ne dépend pas de la remise SMTP. La
        // commande sort donc en succès, mais le dit franchement.
        self::assertSame(Command::SUCCESS, $status);
        self::assertNotNull($this->accounts->findByEmail(self::EMAIL));
        self::assertStringContainsString('e-mail', strtolower($tester->getDisplay()));
    }
}
