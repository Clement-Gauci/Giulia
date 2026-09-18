<?php
namespace App\Tests\Account\Domain;

use App\Account\Domain\Account;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-09 10:00:00');
    }

    public function test_a_new_account_is_active_and_never_connected(): void
    {
        $account = Account::create('hello@giulia-pizza-gorges.fr', 'Boutique', $this->now());

        self::assertTrue($account->isActive());
        self::assertNull($account->lastLoginAt());
    }

    public function test_email_is_normalised(): void
    {
        $account = Account::create('  Hello@Giulia-Pizza-Gorges.FR ', 'Boutique', $this->now());

        self::assertSame('hello@giulia-pizza-gorges.fr', $account->email());
    }

    public function test_rejects_an_invalid_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Account::create('pas-une-adresse', 'Boutique', $this->now());
    }

    public function test_rejects_a_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Account::create('hello@giulia-pizza-gorges.fr', '   ', $this->now());
    }
}
