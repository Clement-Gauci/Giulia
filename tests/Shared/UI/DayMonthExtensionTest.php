<?php
namespace App\Tests\Shared\UI;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class DayMonthExtensionTest extends KernelTestCase
{
    private function render(string $instant, string $timezone): string
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);

        return $twig->createTemplate('{{ d|day_month }}')
            ->render(['d' => new \DateTimeImmutable($instant, new \DateTimeZone($timezone))]);
    }

    public function test_it_names_the_day_and_the_month(): void
    {
        self::assertSame('9 septembre', $this->render('2026-09-09 12:00:00', 'Europe/Paris'));
    }

    public function test_it_converts_to_the_pizzeria_s_timezone_before_naming_the_day(): void
    {
        // 22 h 30 UTC le 9, c'est déjà le 10 à Gorges. Dater la demande de la
        // veille ferait douter d'un e-mail pourtant légitime.
        self::assertSame('10 septembre', $this->render('2026-09-09 22:30:00', 'UTC'));
    }
}
