<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\OpeningStatus;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class OpeningStatusTest extends TestCase
{
    private function schedule(): WeeklySchedule
    {
        $week = [600, 870, 1020, 1290];
        return new WeeklySchedule([
            Weekday::Tuesday->value   => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1290)],
            Weekday::Wednesday->value => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1290)],
            Weekday::Thursday->value  => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1290)],
            Weekday::Friday->value    => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1320)],
            Weekday::Saturday->value  => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1320)],
            Weekday::Sunday->value    => [TimeRange::fromMinutes(1080, 1290)],
        ]);
    }

    private function at(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Paris'));
    }

    public function test_open_now_shows_closing_time(): void
    {
        // Mardi 2026-07-21 12h00 → dans 600-870
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-21 12:00'));
        self::assertTrue($status->isOpen());
        self::assertSame('Ouvert', $status->label());
        self::assertSame('Ouvert jusqu’à 14h30', $status->detail());
    }

    public function test_before_a_later_slot_today(): void
    {
        // Mardi 15h30 → fermé entre les deux créneaux, ouvre à 17h
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-21 15:30'));
        self::assertFalse($status->isOpen());
        self::assertSame('Fermé', $status->label());
        self::assertSame('Ouvre aujourd’hui à 17h', $status->detail());
    }

    public function test_after_last_slot_opens_tomorrow(): void
    {
        // Mardi 22h00 → ouvre demain (mercredi) à 10h
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-21 22:00'));
        self::assertFalse($status->isOpen());
        self::assertSame("Ouvre demain à 10h", $status->detail());
    }

    public function test_monday_closed_opens_tuesday(): void
    {
        // Lundi 2026-07-20 12h00 → fermé, ouvre demain (mardi) à 10h
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-20 12:00'));
        self::assertFalse($status->isOpen());
        self::assertSame("Ouvre demain à 10h", $status->detail());
    }

    public function test_sunday_evening_after_close_opens_named_day(): void
    {
        // Dimanche 2026-07-26 22h00 → après 21h30, lundi fermé, ouvre mardi
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-26 22:00'));
        self::assertFalse($status->isOpen());
        self::assertSame("Ouvre mardi à 10h", $status->detail());
    }

    public function test_boundary_close_minute_is_closed(): void
    {
        // Mardi 14h30 pile → fermé (borne haute exclue)
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-21 14:30'));
        self::assertFalse($status->isOpen());
        self::assertSame('Ouvre aujourd’hui à 17h', $status->detail());
    }
}
