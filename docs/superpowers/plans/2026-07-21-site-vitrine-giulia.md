# Site vitrine Giulia — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire le site vitrine de la pizzeria Giulia en Symfony 8.1 / PHP 8.4, en DDD léger sans base de données, en portant fidèlement les maquettes du design system.

**Architecture:** Modular monolith DDD. Quatre contextes (`Menu`, `Opening`, `Contact`, `Shared`) sous `src/<Context>/{Domain,Application,Infrastructure,UI}`. Le domaine ne dépend ni de Symfony, ni de Twig, ni de YAML : les sources de données (fichiers YAML de `config/giulia/`) et l'envoi d'e-mail sont cachés derrière des interfaces. Le temps est injecté via une interface `Clock` pour des tests déterministes.

**Tech Stack:** Symfony 8.1, PHP 8.4, Twig, `symfony/yaml`, `symfony/form`, `symfony/validator`, `symfony/mailer`, `symfony/string` (slugger), AssetMapper + Stimulus + Turbo, PHPUnit 13.

## Global Constraints

- PHP **>= 8.4** — value objects en `final readonly class`, enums typés, constructor property promotion.
- Autoload PSR-4 : `App\` → `src/` (ex. `App\Menu\Domain\Pizza`). Namespace = chemin.
- **Aucune** dépendance Symfony/Twig/YAML dans les couches `Domain` et `Application`.
- Fuseau horaire métier : **Europe/Paris** (uniquement dans `SystemClock`).
- Prix affichés au format FR : `11,90 €` avec **espace insécable** (`\u{00A0}`) avant `€`.
- Commande de test : `vendor/bin/phpunit`.
- Langue de tout contenu visible : **français**.
- Commits fréquents, un par tâche minimum, en français à l'impératif.

## Cartographie des fichiers

**Domaine & infrastructure (`src/`)**
- `src/Shared/Domain/Money.php` — montant en centimes + formatage FR.
- `src/Shared/Domain/Weekday.php` — enum jours (ISO-8601, 1=lundi..7=dimanche).
- `src/Shared/Domain/SocialLink.php`, `Announcement.php`, `Establishment.php` — infos établissement.
- `src/Shared/Infrastructure/YamlEstablishmentRepository.php` + `EstablishmentRepositoryInterface`.
- `src/Opening/Domain/TimeRange.php`, `WeeklySchedule.php`, `OpeningStatus.php`, `Clock.php`, `ScheduleRepositoryInterface.php`.
- `src/Opening/Infrastructure/SystemClock.php`, `YamlScheduleRepository.php`.
- `src/Opening/UI/StatusController.php`.
- `src/Menu/Domain/Tag.php`, `Pizza.php`, `Category.php`, `MenuRepositoryInterface.php`.
- `src/Menu/Infrastructure/YamlMenuRepository.php`.
- `src/Menu/UI/MenuController.php`.
- `src/Contact/Domain/Subject.php`, `ContactMessage.php`, `ContactMailerInterface.php`.
- `src/Contact/Application/SendContactMessage.php`.
- `src/Contact/Infrastructure/SymfonyContactMailer.php`.
- `src/Contact/UI/ContactController.php`, `ContactFormData.php`, `ContactType.php`.
- `src/Home/UI/HomeController.php`, `src/Home/UI/LegalController.php` (pages composites).

**Données (`config/giulia/`)** — `menu.yaml`, `hours.yaml`, `establishment.yaml`.

**Présentation** — `templates/{base,home,menu,contact,legal,components}`, `assets/styles/`, `assets/fonts/`, `assets/controllers/{live_status,pizza_slider}_controller.js`.

**Tests (`tests/`)** — miroir des contextes pour l'unitaire, `tests/Functional/` pour les smoke tests.

**Ordre de dépendance :** Shared primitives → Opening → Menu → Establishment → Contact → wiring/services → UI. Chaque tâche est indépendamment testable.

---

### Task 1 : Shared — Money & Weekday

**Files:**
- Create: `src/Shared/Domain/Money.php`
- Create: `src/Shared/Domain/Weekday.php`
- Test: `tests/Shared/Domain/MoneyTest.php`
- Test: `tests/Shared/Domain/WeekdayTest.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `Money::fromCents(int $cents): self`, `->cents(): int`, `->format(): string` (ex. `"11,90\u{00A0}€"`), `->equals(Money): bool`.
  - `Weekday` enum `int` (Monday=1 … Sunday=7), `->label(): string` (FR : « Lundi »…), `Weekday::fromDate(\DateTimeImmutable): self`.

- [ ] **Step 1 : Écrire les tests Money**

```php
<?php
namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_formats_in_french_with_non_breaking_space(): void
    {
        self::assertSame("11,90\u{00A0}€", Money::fromCents(1190)->format());
        self::assertSame("17,50\u{00A0}€", Money::fromCents(1750)->format());
    }

    public function test_keeps_cents(): void
    {
        self::assertSame(1190, Money::fromCents(1190)->cents());
    }

    public function test_equality(): void
    {
        self::assertTrue(Money::fromCents(1190)->equals(Money::fromCents(1190)));
        self::assertFalse(Money::fromCents(1190)->equals(Money::fromCents(1200)));
    }

    public function test_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromCents(-1);
    }
}
```

- [ ] **Step 2 : Écrire les tests Weekday**

```php
<?php
namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class WeekdayTest extends TestCase
{
    public function test_labels_are_french(): void
    {
        self::assertSame('Lundi', Weekday::Monday->label());
        self::assertSame('Dimanche', Weekday::Sunday->label());
    }

    public function test_from_date_uses_iso_day(): void
    {
        // 2026-07-20 est un lundi
        $monday = new \DateTimeImmutable('2026-07-20');
        self::assertSame(Weekday::Monday, Weekday::fromDate($monday));
        // 2026-07-26 est un dimanche
        $sunday = new \DateTimeImmutable('2026-07-26');
        self::assertSame(Weekday::Sunday, Weekday::fromDate($sunday));
    }
}
```

- [ ] **Step 3 : Lancer les tests (échec attendu)**

Run: `vendor/bin/phpunit tests/Shared`
Expected: FAIL — classes `Money` / `Weekday` introuvables.

- [ ] **Step 4 : Implémenter Money**

```php
<?php
namespace App\Shared\Domain;

final readonly class Money
{
    private function __construct(public int $cents)
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Le montant ne peut pas être négatif.');
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function format(): string
    {
        $euros = number_format($this->cents / 100, 2, ',', "\u{00A0}");
        return $euros . "\u{00A0}€";
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
```

- [ ] **Step 5 : Implémenter Weekday**

```php
<?php
namespace App\Shared\Domain;

enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Lundi',
            self::Tuesday => 'Mardi',
            self::Wednesday => 'Mercredi',
            self::Thursday => 'Jeudi',
            self::Friday => 'Vendredi',
            self::Saturday => 'Samedi',
            self::Sunday => 'Dimanche',
        };
    }

    public static function fromDate(\DateTimeImmutable $date): self
    {
        return self::from((int) $date->format('N'));
    }
}
```

- [ ] **Step 6 : Lancer les tests (succès attendu)**

Run: `vendor/bin/phpunit tests/Shared`
Expected: PASS.

- [ ] **Step 7 : Commit**

```bash
git add src/Shared tests/Shared
git commit -m "feat(shared): ajoute les value objects Money et Weekday"
```

---

### Task 2 : Opening — TimeRange & WeeklySchedule

**Files:**
- Create: `src/Opening/Domain/TimeRange.php`
- Create: `src/Opening/Domain/WeeklySchedule.php`
- Test: `tests/Opening/Domain/TimeRangeTest.php`
- Test: `tests/Opening/Domain/WeeklyScheduleTest.php`

**Interfaces:**
- Consumes: `App\Shared\Domain\Weekday`.
- Produces:
  - `TimeRange::fromMinutes(int $open, int $close): self`, `->openMinute(): int`, `->closeMinute(): int`, `->contains(int $minute): bool` (open inclus, close exclu), `->openLabel(): string` / `->closeLabel(): string` (ex. `"10h"`, `"14h30"`), `TimeRange::formatMinute(int): string`.
  - `WeeklySchedule` construit via `new WeeklySchedule(array $ranges)` où `$ranges` = `array<int, TimeRange[]>` indexé par valeur `Weekday` ; `->rangesFor(Weekday): TimeRange[]` (triées par ouverture).

- [ ] **Step 1 : Écrire les tests TimeRange**

```php
<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\TimeRange;
use PHPUnit\Framework\TestCase;

final class TimeRangeTest extends TestCase
{
    public function test_contains_is_open_inclusive_close_exclusive(): void
    {
        $range = TimeRange::fromMinutes(600, 870); // 10h00 - 14h30
        self::assertTrue($range->contains(600));
        self::assertTrue($range->contains(869));
        self::assertFalse($range->contains(870));
        self::assertFalse($range->contains(599));
    }

    public function test_labels(): void
    {
        $range = TimeRange::fromMinutes(600, 870);
        self::assertSame('10h', $range->openLabel());
        self::assertSame('14h30', $range->closeLabel());
    }

    public function test_rejects_invalid_bounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimeRange::fromMinutes(870, 600);
    }
}
```

- [ ] **Step 2 : Écrire les tests WeeklySchedule**

```php
<?php
namespace App\Tests\Opening\Domain;

use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class WeeklyScheduleTest extends TestCase
{
    public function test_returns_ranges_sorted_by_open(): void
    {
        $schedule = new WeeklySchedule([
            Weekday::Tuesday->value => [
                TimeRange::fromMinutes(1020, 1290),
                TimeRange::fromMinutes(600, 870),
            ],
        ]);
        $ranges = $schedule->rangesFor(Weekday::Tuesday);
        self::assertCount(2, $ranges);
        self::assertSame(600, $ranges[0]->openMinute());
        self::assertSame(1020, $ranges[1]->openMinute());
    }

    public function test_empty_day_returns_no_ranges(): void
    {
        $schedule = new WeeklySchedule([]);
        self::assertSame([], $schedule->rangesFor(Weekday::Monday));
    }
}
```

- [ ] **Step 3 : Lancer les tests (échec attendu)**

Run: `vendor/bin/phpunit tests/Opening/Domain`
Expected: FAIL — classes introuvables.

- [ ] **Step 4 : Implémenter TimeRange**

```php
<?php
namespace App\Opening\Domain;

final readonly class TimeRange
{
    private function __construct(public int $openMinute, public int $closeMinute)
    {
        if ($openMinute < 0 || $closeMinute > 1440 || $openMinute >= $closeMinute) {
            throw new \InvalidArgumentException('Créneau horaire invalide.');
        }
    }

    public static function fromMinutes(int $open, int $close): self
    {
        return new self($open, $close);
    }

    public function openMinute(): int
    {
        return $this->openMinute;
    }

    public function closeMinute(): int
    {
        return $this->closeMinute;
    }

    public function contains(int $minute): bool
    {
        return $minute >= $this->openMinute && $minute < $this->closeMinute;
    }

    public function openLabel(): string
    {
        return self::formatMinute($this->openMinute);
    }

    public function closeLabel(): string
    {
        return self::formatMinute($this->closeMinute);
    }

    public static function formatMinute(int $minute): string
    {
        $h = intdiv($minute, 60);
        $m = $minute % 60;
        return $m === 0 ? $h . 'h' : $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 5 : Implémenter WeeklySchedule**

```php
<?php
namespace App\Opening\Domain;

use App\Shared\Domain\Weekday;

final readonly class WeeklySchedule
{
    /** @var array<int, TimeRange[]> */
    private array $ranges;

    /** @param array<int, TimeRange[]> $ranges indexé par Weekday::value */
    public function __construct(array $ranges)
    {
        $normalized = [];
        foreach ($ranges as $day => $dayRanges) {
            usort($dayRanges, static fn (TimeRange $a, TimeRange $b) => $a->openMinute() <=> $b->openMinute());
            $normalized[$day] = array_values($dayRanges);
        }
        $this->ranges = $normalized;
    }

    /** @return TimeRange[] */
    public function rangesFor(Weekday $day): array
    {
        return $this->ranges[$day->value] ?? [];
    }
}
```

- [ ] **Step 6 : Lancer les tests (succès attendu)**

Run: `vendor/bin/phpunit tests/Opening/Domain`
Expected: PASS.

- [ ] **Step 7 : Commit**

```bash
git add src/Opening tests/Opening
git commit -m "feat(opening): ajoute TimeRange et WeeklySchedule"
```

### Task 3 : Opening — Clock & OpeningStatus (cœur métier)

**Files:**
- Create: `src/Opening/Domain/Clock.php`
- Create: `src/Opening/Infrastructure/SystemClock.php`
- Create: `src/Opening/Domain/OpeningStatus.php`
- Test: `tests/Opening/Domain/OpeningStatusTest.php`
- Test helper: `tests/Opening/Support/FrozenClock.php`

**Interfaces:**
- Consumes: `WeeklySchedule`, `TimeRange`, `Weekday`.
- Produces:
  - `Clock` interface : `now(): \DateTimeImmutable`.
  - `SystemClock` : implémente `Clock`, renvoie l'heure en `Europe/Paris`.
  - `OpeningStatus::compute(WeeklySchedule $schedule, \DateTimeImmutable $now): self` ; accès `->isOpen(): bool`, `->label(): string` (« Ouvert » / « Fermé »), `->detail(): string`.

- [ ] **Step 1 : Écrire le FrozenClock de test**

```php
<?php
namespace App\Tests\Opening\Support;

use App\Opening\Domain\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
```

- [ ] **Step 2 : Écrire les tests OpeningStatus**

Le planning de référence (minutes depuis minuit) : Lun fermé ; Mar–Jeu 600–870 / 1020–1290 ; Ven–Sam 600–870 / 1020–1320 ; Dim 1080–1290.

```php
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
        self::assertSame("Ouvert jusqu’à 14h30", $status->detail());
    }

    public function test_before_a_later_slot_today(): void
    {
        // Mardi 15h30 → fermé entre les deux créneaux, ouvre à 17h
        $status = OpeningStatus::compute($this->schedule(), $this->at('2026-07-21 15:30'));
        self::assertFalse($status->isOpen());
        self::assertSame('Fermé', $status->label());
        self::assertSame("Ouvre aujourd’hui à 17h", $status->detail());
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
        self::assertSame("Ouvre aujourd’hui à 17h", $status->detail());
    }
}
```

- [ ] **Step 3 : Lancer les tests (échec attendu)**

Run: `vendor/bin/phpunit tests/Opening/Domain/OpeningStatusTest.php`
Expected: FAIL — `OpeningStatus` / `Clock` introuvables.

- [ ] **Step 4 : Implémenter Clock & SystemClock**

```php
<?php
namespace App\Opening\Domain;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
```

```php
<?php
namespace App\Opening\Infrastructure;

use App\Opening\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }
}
```

- [ ] **Step 5 : Implémenter OpeningStatus**

```php
<?php
namespace App\Opening\Domain;

use App\Shared\Domain\Weekday;

final readonly class OpeningStatus
{
    private function __construct(
        private bool $open,
        private string $label,
        private string $detail,
    ) {}

    public static function compute(WeeklySchedule $schedule, \DateTimeImmutable $now): self
    {
        $day = Weekday::fromDate($now);
        $minute = ((int) $now->format('G')) * 60 + (int) $now->format('i');

        // 1) Ouvert dans un créneau du jour ?
        foreach ($schedule->rangesFor($day) as $range) {
            if ($range->contains($minute)) {
                return new self(true, 'Ouvert', 'Ouvert jusqu’à ' . $range->closeLabel());
            }
        }

        // 2) Un créneau plus tard aujourd’hui ?
        foreach ($schedule->rangesFor($day) as $range) {
            if ($minute < $range->openMinute()) {
                return new self(false, 'Fermé', 'Ouvre aujourd’hui à ' . $range->openLabel());
            }
        }

        // 3) Prochain jour ouvré.
        for ($i = 1; $i <= 7; $i++) {
            $nextDay = Weekday::from((($day->value - 1 + $i) % 7) + 1);
            $ranges = $schedule->rangesFor($nextDay);
            if ($ranges !== []) {
                $when = $i === 1 ? 'demain' : strtolower($nextDay->label());
                return new self(false, 'Fermé', 'Ouvre ' . $when . ' à ' . $ranges[0]->openLabel());
            }
        }

        return new self(false, 'Fermé', '');
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function detail(): string
    {
        return $this->detail;
    }
}
```

- [ ] **Step 6 : Lancer les tests (succès attendu)**

Run: `vendor/bin/phpunit tests/Opening/Domain/OpeningStatusTest.php`
Expected: PASS (6 tests).

- [ ] **Step 7 : Commit**

```bash
git add src/Opening tests/Opening
git commit -m "feat(opening): calcule le statut d'ouverture (Clock injectable)"
```

---

### Task 4 : Opening — ScheduleRepository & hours.yaml

**Files:**
- Create: `src/Opening/Domain/ScheduleRepositoryInterface.php`
- Create: `src/Opening/Infrastructure/YamlScheduleRepository.php`
- Create: `config/giulia/hours.yaml`
- Test: `tests/Opening/Infrastructure/YamlScheduleRepositoryTest.php`
- Test fixture: `tests/Opening/Infrastructure/fixtures/hours.yaml`

**Interfaces:**
- Consumes: `WeeklySchedule`, `TimeRange`, `Weekday`, `symfony/yaml`.
- Produces: `ScheduleRepositoryInterface::schedule(): WeeklySchedule` ; `YamlScheduleRepository(string $file)` (chemin absolu du YAML injecté).

Format `hours.yaml` — clés = noms de jours anglais en minuscules, valeurs = liste de créneaux `HH:MM` :

```yaml
monday: []
tuesday:
  - { open: "10:00", close: "14:30" }
  - { open: "17:00", close: "21:30" }
wednesday:
  - { open: "10:00", close: "14:30" }
  - { open: "17:00", close: "21:30" }
thursday:
  - { open: "10:00", close: "14:30" }
  - { open: "17:00", close: "21:30" }
friday:
  - { open: "10:00", close: "14:30" }
  - { open: "17:00", close: "22:00" }
saturday:
  - { open: "10:00", close: "14:30" }
  - { open: "17:00", close: "22:00" }
sunday:
  - { open: "18:00", close: "21:30" }
```

- [ ] **Step 1 : Créer la fixture de test**

Créer `tests/Opening/Infrastructure/fixtures/hours.yaml` avec exactement le contenu ci-dessus.

- [ ] **Step 2 : Écrire le test du repository**

```php
<?php
namespace App\Tests\Opening\Infrastructure;

use App\Opening\Infrastructure\YamlScheduleRepository;
use App\Shared\Domain\Weekday;
use PHPUnit\Framework\TestCase;

final class YamlScheduleRepositoryTest extends TestCase
{
    private function repo(): YamlScheduleRepository
    {
        return new YamlScheduleRepository(__DIR__ . '/fixtures/hours.yaml');
    }

    public function test_monday_is_closed(): void
    {
        self::assertSame([], $this->repo()->schedule()->rangesFor(Weekday::Monday));
    }

    public function test_tuesday_has_two_ranges_parsed_to_minutes(): void
    {
        $ranges = $this->repo()->schedule()->rangesFor(Weekday::Tuesday);
        self::assertCount(2, $ranges);
        self::assertSame(600, $ranges[0]->openMinute());
        self::assertSame(870, $ranges[0]->closeMinute());
        self::assertSame(1020, $ranges[1]->openMinute());
        self::assertSame(1290, $ranges[1]->closeMinute());
    }

    public function test_friday_closes_later(): void
    {
        $ranges = $this->repo()->schedule()->rangesFor(Weekday::Friday);
        self::assertSame(1320, $ranges[1]->closeMinute());
    }
}
```

- [ ] **Step 3 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Opening/Infrastructure`
Expected: FAIL — classes introuvables.

- [ ] **Step 4 : Implémenter l'interface et le repository**

```php
<?php
namespace App\Opening\Domain;

interface ScheduleRepositoryInterface
{
    public function schedule(): WeeklySchedule;
}
```

```php
<?php
namespace App\Opening\Infrastructure;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Shared\Domain\Weekday;
use Symfony\Component\Yaml\Yaml;

final class YamlScheduleRepository implements ScheduleRepositoryInterface
{
    private const DAYS = [
        'monday' => Weekday::Monday,
        'tuesday' => Weekday::Tuesday,
        'wednesday' => Weekday::Wednesday,
        'thursday' => Weekday::Thursday,
        'friday' => Weekday::Friday,
        'saturday' => Weekday::Saturday,
        'sunday' => Weekday::Sunday,
    ];

    public function __construct(private string $file) {}

    public function schedule(): WeeklySchedule
    {
        $data = Yaml::parseFile($this->file);
        $ranges = [];
        foreach (self::DAYS as $key => $weekday) {
            $slots = $data[$key] ?? [];
            $ranges[$weekday->value] = array_map(
                static fn (array $slot) => TimeRange::fromMinutes(
                    self::toMinutes($slot['open']),
                    self::toMinutes($slot['close']),
                ),
                $slots,
            );
        }
        return new WeeklySchedule($ranges);
    }

    private static function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        return $h * 60 + $m;
    }
}
```

- [ ] **Step 5 : Créer les données de production**

Créer `config/giulia/hours.yaml` avec le même contenu que la fixture (Step 1).

- [ ] **Step 6 : Lancer le test (succès attendu)**

Run: `vendor/bin/phpunit tests/Opening/Infrastructure`
Expected: PASS.

- [ ] **Step 7 : Commit**

```bash
git add src/Opening config/giulia/hours.yaml tests/Opening/Infrastructure
git commit -m "feat(opening): charge les horaires depuis hours.yaml"
```

### Task 5 : Menu — Tag, Pizza & Category

**Files:**
- Create: `src/Menu/Domain/Tag.php`
- Create: `src/Menu/Domain/Pizza.php`
- Create: `src/Menu/Domain/Category.php`
- Test: `tests/Menu/Domain/PizzaTest.php`

**Interfaces:**
- Consumes: `App\Shared\Domain\Money`.
- Produces:
  - `Tag` enum `string` : `Vegetarian = 'veg'`, `Spicy = 'spicy'` ; `->label(): string`, `->icon(): string`.
  - `Pizza` : `new Pizza(string $name, string $slug, string[] $ingredients, Money $price, Tag[] $tags, string[] $allergens, bool $featured)` ; getters `->name()`, `->slug()`, `->ingredients()`, `->price()`, `->tags()`, `->allergens()`, `->isFeatured()`, `->hasTag(Tag): bool`.
  - `Category` : `new Category(string $kicker, string $label, Pizza[] $pizzas)` ; `->kicker()`, `->label()`, `->pizzas()`.

- [ ] **Step 1 : Écrire les tests Pizza/Tag**

```php
<?php
namespace App\Tests\Menu\Domain;

use App\Menu\Domain\Pizza;
use App\Menu\Domain\Tag;
use App\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class PizzaTest extends TestCase
{
    private function margherita(): Pizza
    {
        return new Pizza(
            'Margherita',
            'margherita',
            ['San Marzano', 'fior di latte', 'basilic'],
            Money::fromCents(1190),
            [Tag::Vegetarian],
            ['gluten', 'lait'],
            false,
        );
    }

    public function test_exposes_its_data(): void
    {
        $pizza = $this->margherita();
        self::assertSame('Margherita', $pizza->name());
        self::assertSame('margherita', $pizza->slug());
        self::assertSame("11,90\u{00A0}€", $pizza->price()->format());
        self::assertTrue($pizza->hasTag(Tag::Vegetarian));
        self::assertFalse($pizza->hasTag(Tag::Spicy));
        self::assertFalse($pizza->isFeatured());
    }

    public function test_tag_metadata(): void
    {
        self::assertSame('Végétarienne', Tag::Vegetarian->label());
        self::assertSame('🌱', Tag::Vegetarian->icon());
        self::assertSame('Piquante', Tag::Spicy->label());
        self::assertSame('🌶️', Tag::Spicy->icon());
    }
}
```

- [ ] **Step 2 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Menu/Domain`
Expected: FAIL — classes introuvables.

- [ ] **Step 3 : Implémenter Tag**

```php
<?php
namespace App\Menu\Domain;

enum Tag: string
{
    case Vegetarian = 'veg';
    case Spicy = 'spicy';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => 'Végétarienne',
            self::Spicy => 'Piquante',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Vegetarian => '🌱',
            self::Spicy => '🌶️',
        };
    }
}
```

- [ ] **Step 4 : Implémenter Pizza**

```php
<?php
namespace App\Menu\Domain;

use App\Shared\Domain\Money;

final readonly class Pizza
{
    /**
     * @param string[] $ingredients
     * @param Tag[]    $tags
     * @param string[] $allergens
     */
    public function __construct(
        private string $name,
        private string $slug,
        private array $ingredients,
        private Money $price,
        private array $tags,
        private array $allergens,
        private bool $featured,
    ) {}

    public function name(): string { return $this->name; }
    public function slug(): string { return $this->slug; }
    /** @return string[] */
    public function ingredients(): array { return $this->ingredients; }
    public function price(): Money { return $this->price; }
    /** @return Tag[] */
    public function tags(): array { return $this->tags; }
    /** @return string[] */
    public function allergens(): array { return $this->allergens; }
    public function isFeatured(): bool { return $this->featured; }

    public function hasTag(Tag $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
```

- [ ] **Step 5 : Implémenter Category**

```php
<?php
namespace App\Menu\Domain;

final readonly class Category
{
    /** @param Pizza[] $pizzas */
    public function __construct(
        private string $kicker,
        private string $label,
        private array $pizzas,
    ) {}

    public function kicker(): string { return $this->kicker; }
    public function label(): string { return $this->label; }
    /** @return Pizza[] */
    public function pizzas(): array { return $this->pizzas; }
}
```

- [ ] **Step 6 : Lancer le test (succès attendu)**

Run: `vendor/bin/phpunit tests/Menu/Domain`
Expected: PASS.

- [ ] **Step 7 : Commit**

```bash
git add src/Menu tests/Menu
git commit -m "feat(menu): ajoute Tag, Pizza et Category"
```

---

### Task 6 : Menu — MenuRepository & menu.yaml

**Files:**
- Create: `src/Menu/Domain/MenuRepositoryInterface.php`
- Create: `src/Menu/Infrastructure/YamlMenuRepository.php`
- Create: `config/giulia/menu.yaml`
- Test: `tests/Menu/Infrastructure/YamlMenuRepositoryTest.php`
- Test fixture: `tests/Menu/Infrastructure/fixtures/menu.yaml`

**Interfaces:**
- Consumes: `Category`, `Pizza`, `Tag`, `Money`, `symfony/string` (`AsciiSlugger`), `symfony/yaml`.
- Produces: `MenuRepositoryInterface::categories(): Category[]`, `::findBySlug(string $slug): ?Pizza`, `::featured(): ?Pizza` ; `YamlMenuRepository(string $file)`.

Format `menu.yaml` (le slug est **calculé** depuis le nom, pas stocké) :

```yaml
categories:
  - kicker: "Base tomate San Marzano"
    label: "Les rouges"
    pizzas:
      - { name: "Margherita", price: 1190, tags: [veg], allergens: [gluten, lait], ingredients: ["San Marzano", "fior di latte", "basilic", "huile d’olive"] }
      - { name: "Regina", price: 1390, ingredients: ["San Marzano", "fior di latte", "jambon blanc", "champignons"] }
      - { name: "Napoli", price: 1350, ingredients: ["San Marzano", "fior di latte", "anchois", "câpres", "olives"] }
      - { name: "Diavola", price: 1450, tags: [spicy], ingredients: ["San Marzano", "fior di latte", "spianata piccante", "basilic"] }
      - { name: "Capricciosa", price: 1550, ingredients: ["San Marzano", "fior di latte", "jambon", "champignons", "artichauts", "olives"] }
      - { name: "Vegetariana", price: 1450, tags: [veg], ingredients: ["San Marzano", "fior di latte", "légumes grillés de saison", "roquette"] }
      - { name: "Calabrese", price: 1590, tags: [spicy], ingredients: ["San Marzano", "fior di latte", "’nduja", "oignons rouges", "miel"] }
  - kicker: "Base crème & mozzarella"
    label: "Les blanches"
    pizzas:
      - { name: "Quattro Formaggi", price: 1490, tags: [veg], ingredients: ["fior di latte", "gorgonzola", "comté 24 mois", "parmesan"] }
      - { name: "Boscaiola", price: 1490, ingredients: ["crème", "fior di latte", "champignons", "lardons", "persillade"] }
      - { name: "Burratina", price: 1650, tags: [veg], ingredients: ["crème", "fior di latte", "burrata", "tomates confites", "basilic"] }
      - { name: "Tartufo", price: 1750, tags: [veg], ingredients: ["crème de truffe", "fior di latte", "champignons", "parmesan", "roquette"] }
  - kicker: "L’édition du moment"
    label: "La signature"
    pizzas:
      - { name: "La Fresca", price: 1790, featured: true, ingredients: ["San Marzano", "fior di latte", "bresaola", "roquette", "guacamole", "comté 24 mois"] }
```

- [ ] **Step 1 : Créer la fixture de test**

Créer `tests/Menu/Infrastructure/fixtures/menu.yaml` avec le même contenu que ci-dessus (peut être réduit à 1 catégorie + la signature, mais garder au moins une pizza `veg`, une `spicy` et `La Fresca` `featured`).

- [ ] **Step 2 : Écrire le test du repository**

```php
<?php
namespace App\Tests\Menu\Infrastructure;

use App\Menu\Domain\Tag;
use App\Menu\Infrastructure\YamlMenuRepository;
use PHPUnit\Framework\TestCase;

final class YamlMenuRepositoryTest extends TestCase
{
    private function repo(): YamlMenuRepository
    {
        return new YamlMenuRepository(__DIR__ . '/fixtures/menu.yaml');
    }

    public function test_reads_categories_and_pizzas(): void
    {
        $categories = $this->repo()->categories();
        self::assertNotEmpty($categories);
        self::assertSame('Les rouges', $categories[0]->label());
        self::assertSame('Margherita', $categories[0]->pizzas()[0]->name());
    }

    public function test_computes_slug_from_name(): void
    {
        $pizza = $this->repo()->findBySlug('quattro-formaggi');
        self::assertNotNull($pizza);
        self::assertSame('Quattro Formaggi', $pizza->name());
    }

    public function test_unknown_slug_returns_null(): void
    {
        self::assertNull($this->repo()->findBySlug('inexistante'));
    }

    public function test_parses_tags_and_price(): void
    {
        $pizza = $this->repo()->findBySlug('margherita');
        self::assertNotNull($pizza);
        self::assertTrue($pizza->hasTag(Tag::Vegetarian));
        self::assertSame("11,90\u{00A0}€", $pizza->price()->format());
    }

    public function test_featured_returns_la_fresca(): void
    {
        $featured = $this->repo()->featured();
        self::assertNotNull($featured);
        self::assertSame('La Fresca', $featured->name());
        self::assertTrue($featured->isFeatured());
    }
}
```

- [ ] **Step 3 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Menu/Infrastructure`
Expected: FAIL — classes introuvables.

- [ ] **Step 4 : Implémenter l'interface**

```php
<?php
namespace App\Menu\Domain;

interface MenuRepositoryInterface
{
    /** @return Category[] */
    public function categories(): array;

    public function findBySlug(string $slug): ?Pizza;

    public function featured(): ?Pizza;
}
```

- [ ] **Step 5 : Implémenter YamlMenuRepository**

```php
<?php
namespace App\Menu\Infrastructure;

use App\Menu\Domain\Category;
use App\Menu\Domain\MenuRepositoryInterface;
use App\Menu\Domain\Pizza;
use App\Menu\Domain\Tag;
use App\Shared\Domain\Money;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class YamlMenuRepository implements MenuRepositoryInterface
{
    /** @var Category[]|null */
    private ?array $cache = null;

    public function __construct(private string $file) {}

    public function categories(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $data = \Symfony\Component\Yaml\Yaml::parseFile($this->file);
        $slugger = new AsciiSlugger('fr');
        $categories = [];
        foreach ($data['categories'] as $cat) {
            $pizzas = [];
            foreach ($cat['pizzas'] as $p) {
                $pizzas[] = new Pizza(
                    $p['name'],
                    strtolower((string) $slugger->slug($p['name'])),
                    $p['ingredients'] ?? [],
                    Money::fromCents((int) $p['price']),
                    array_map(static fn (string $t) => Tag::from($t), $p['tags'] ?? []),
                    $p['allergens'] ?? [],
                    (bool) ($p['featured'] ?? false),
                );
            }
            $categories[] = new Category($cat['kicker'], $cat['label'], $pizzas);
        }
        return $this->cache = $categories;
    }

    public function findBySlug(string $slug): ?Pizza
    {
        foreach ($this->categories() as $category) {
            foreach ($category->pizzas() as $pizza) {
                if ($pizza->slug() === $slug) {
                    return $pizza;
                }
            }
        }
        return null;
    }

    public function featured(): ?Pizza
    {
        foreach ($this->categories() as $category) {
            foreach ($category->pizzas() as $pizza) {
                if ($pizza->isFeatured()) {
                    return $pizza;
                }
            }
        }
        return null;
    }
}
```

- [ ] **Step 6 : Créer les données de production**

Créer `config/giulia/menu.yaml` avec le contenu complet (les 3 catégories, 12 pizzas ci-dessus).

- [ ] **Step 7 : Lancer le test (succès attendu)**

Run: `vendor/bin/phpunit tests/Menu/Infrastructure`
Expected: PASS.

- [ ] **Step 8 : Commit**

```bash
git add src/Menu config/giulia/menu.yaml tests/Menu/Infrastructure
git commit -m "feat(menu): charge la carte depuis menu.yaml (slug calculé)"
```

### Task 7 : Shared — Establishment, Announcement, SocialLink & establishment.yaml

**Files:**
- Create: `src/Shared/Domain/SocialLink.php`
- Create: `src/Shared/Domain/Announcement.php`
- Create: `src/Shared/Domain/Establishment.php`
- Create: `src/Shared/Domain/EstablishmentRepositoryInterface.php`
- Create: `src/Shared/Infrastructure/YamlEstablishmentRepository.php`
- Create: `config/giulia/establishment.yaml`
- Test: `tests/Shared/Infrastructure/YamlEstablishmentRepositoryTest.php`
- Test fixture: `tests/Shared/Infrastructure/fixtures/establishment.yaml`

**Interfaces:**
- Consumes: `symfony/yaml`.
- Produces:
  - `SocialLink` : `new SocialLink(string $label, string $url, string $icon)` ; `->label()`, `->url()`, `->icon()`.
  - `Announcement` : `new Announcement(bool $active, string $title, string $text)` ; `->isActive()`, `->title()`, `->text()`.
  - `Establishment` : getters `->name()`, `->tagline()`, `->address()`, `->phone()`, `->phoneHref()` (format `+33…`), `->email()`, `->menuPdfUrl()`, `->directionsUrl()`, `->googleReviewsUrl()`, `->whatsappUrl()`, `->socialLinks(): SocialLink[]`, `->announcement(): Announcement`.
  - `EstablishmentRepositoryInterface::get(): Establishment` ; `YamlEstablishmentRepository(string $file)`.

Format `establishment.yaml` :

```yaml
name: "Giulia"
tagline: "Pizzeria napolitaine · Gorges"
address: "1 rue de la cité des sports, 44190 Gorges"
phone: "02 85 52 87 42"
phone_href: "+33285528742"
email: "hello@giulia-pizza-gorges.fr"
menu_pdf_url: "/menu.pdf"
directions_url: "https://maps.google.com/?q=Giulia+Pizzeria+Gorges"
google_reviews_url: "https://g.page/r/giulia-gorges/review"
whatsapp_url: "https://chat.whatsapp.com/giulia-anti-gaspi"
social_links:
  - { label: "Instagram", url: "https://instagram.com/giulia.gorges", icon: "instagram" }
  - { label: "Facebook", url: "https://facebook.com/giulia.gorges", icon: "facebook" }
announcement:
  active: false
  title: "À noter"
  text: "Fermeture exceptionnelle le 15 août. Réouverture le lendemain aux horaires habituels."
```

> Les URL externes (Maps, avis Google, WhatsApp, réseaux) sont des **valeurs à confirmer** avec le client ; le format est correct, les cibles exactes seront ajustées avant mise en ligne.

- [ ] **Step 1 : Créer la fixture de test**

Copier le YAML ci-dessus dans `tests/Shared/Infrastructure/fixtures/establishment.yaml`.

- [ ] **Step 2 : Écrire le test**

```php
<?php
namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\YamlEstablishmentRepository;
use PHPUnit\Framework\TestCase;

final class YamlEstablishmentRepositoryTest extends TestCase
{
    private function repo(): YamlEstablishmentRepository
    {
        return new YamlEstablishmentRepository(__DIR__ . '/fixtures/establishment.yaml');
    }

    public function test_reads_core_fields(): void
    {
        $e = $this->repo()->get();
        self::assertSame('Giulia', $e->name());
        self::assertSame('02 85 52 87 42', $e->phone());
        self::assertSame('+33285528742', $e->phoneHref());
        self::assertSame('hello@giulia-pizza-gorges.fr', $e->email());
    }

    public function test_reads_social_links(): void
    {
        $links = $this->repo()->get()->socialLinks();
        self::assertCount(2, $links);
        self::assertSame('Instagram', $links[0]->label());
    }

    public function test_announcement_is_inactive_by_default(): void
    {
        $a = $this->repo()->get()->announcement();
        self::assertFalse($a->isActive());
        self::assertSame('À noter', $a->title());
    }
}
```

- [ ] **Step 3 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Shared/Infrastructure`
Expected: FAIL — classes introuvables.

- [ ] **Step 4 : Implémenter SocialLink & Announcement**

```php
<?php
namespace App\Shared\Domain;

final readonly class SocialLink
{
    public function __construct(
        private string $label,
        private string $url,
        private string $icon,
    ) {}

    public function label(): string { return $this->label; }
    public function url(): string { return $this->url; }
    public function icon(): string { return $this->icon; }
}
```

```php
<?php
namespace App\Shared\Domain;

final readonly class Announcement
{
    public function __construct(
        private bool $active,
        private string $title,
        private string $text,
    ) {}

    public function isActive(): bool { return $this->active; }
    public function title(): string { return $this->title; }
    public function text(): string { return $this->text; }
}
```

- [ ] **Step 5 : Implémenter Establishment**

```php
<?php
namespace App\Shared\Domain;

final readonly class Establishment
{
    /** @param SocialLink[] $socialLinks */
    public function __construct(
        private string $name,
        private string $tagline,
        private string $address,
        private string $phone,
        private string $phoneHref,
        private string $email,
        private string $menuPdfUrl,
        private string $directionsUrl,
        private string $googleReviewsUrl,
        private string $whatsappUrl,
        private array $socialLinks,
        private Announcement $announcement,
    ) {}

    public function name(): string { return $this->name; }
    public function tagline(): string { return $this->tagline; }
    public function address(): string { return $this->address; }
    public function phone(): string { return $this->phone; }
    public function phoneHref(): string { return $this->phoneHref; }
    public function email(): string { return $this->email; }
    public function menuPdfUrl(): string { return $this->menuPdfUrl; }
    public function directionsUrl(): string { return $this->directionsUrl; }
    public function googleReviewsUrl(): string { return $this->googleReviewsUrl; }
    public function whatsappUrl(): string { return $this->whatsappUrl; }
    /** @return SocialLink[] */
    public function socialLinks(): array { return $this->socialLinks; }
    public function announcement(): Announcement { return $this->announcement; }
}
```

- [ ] **Step 6 : Implémenter l'interface & le repository**

```php
<?php
namespace App\Shared\Domain;

interface EstablishmentRepositoryInterface
{
    public function get(): Establishment;
}
```

```php
<?php
namespace App\Shared\Infrastructure;

use App\Shared\Domain\Announcement;
use App\Shared\Domain\Establishment;
use App\Shared\Domain\EstablishmentRepositoryInterface;
use App\Shared\Domain\SocialLink;
use Symfony\Component\Yaml\Yaml;

final class YamlEstablishmentRepository implements EstablishmentRepositoryInterface
{
    public function __construct(private string $file) {}

    public function get(): Establishment
    {
        $d = Yaml::parseFile($this->file);
        $links = array_map(
            static fn (array $l) => new SocialLink($l['label'], $l['url'], $l['icon']),
            $d['social_links'] ?? [],
        );
        $a = $d['announcement'];
        return new Establishment(
            $d['name'], $d['tagline'], $d['address'], $d['phone'], $d['phone_href'],
            $d['email'], $d['menu_pdf_url'], $d['directions_url'], $d['google_reviews_url'],
            $d['whatsapp_url'], $links,
            new Announcement((bool) $a['active'], $a['title'], $a['text']),
        );
    }
}
```

- [ ] **Step 7 : Créer les données de production**

Créer `config/giulia/establishment.yaml` (contenu ci-dessus).

- [ ] **Step 8 : Lancer le test (succès attendu) & Commit**

Run: `vendor/bin/phpunit tests/Shared/Infrastructure` → PASS.

```bash
git add src/Shared config/giulia/establishment.yaml tests/Shared/Infrastructure
git commit -m "feat(shared): ajoute Establishment chargé depuis establishment.yaml"
```

---

### Task 8 : Contact — Subject & ContactMessage

**Files:**
- Create: `src/Contact/Domain/Subject.php`
- Create: `src/Contact/Domain/ContactMessage.php`
- Test: `tests/Contact/Domain/ContactMessageTest.php`

**Interfaces:**
- Consumes: rien (domaine pur).
- Produces:
  - `Subject` enum `string` : `GeneralQuestion='general'`, `ClickAndCollect='cc'`, `Event='event'`, `Allergy='allergy'`, `Other='other'` ; `->label(): string` (FR). `Subject::choices(): array<string,string>` (label => value) pour le formulaire.
  - `ContactMessage` : `new ContactMessage(string $name, string $email, ?string $phone, Subject $subject, string $message)` ; getters correspondants. Valide ses invariants (nom/email/message non vides, email plausible) → `\InvalidArgumentException` sinon.

- [ ] **Step 1 : Écrire les tests**

```php
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
```

- [ ] **Step 2 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Contact/Domain`
Expected: FAIL — classes introuvables.

- [ ] **Step 3 : Implémenter Subject**

```php
<?php
namespace App\Contact\Domain;

enum Subject: string
{
    case GeneralQuestion = 'general';
    case ClickAndCollect = 'cc';
    case Event = 'event';
    case Allergy = 'allergy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GeneralQuestion => 'Une question générale',
            self::ClickAndCollect => 'Une commande click & collect',
            self::Event => 'Un événement / grande commande',
            self::Allergy => 'Une allergie ou un régime',
            self::Other => 'Autre',
        };
    }

    /** @return array<string,string> label => value */
    public static function choices(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->label()] = $case->value;
        }
        return $out;
    }
}
```

- [ ] **Step 4 : Implémenter ContactMessage**

```php
<?php
namespace App\Contact\Domain;

final readonly class ContactMessage
{
    public function __construct(
        private string $name,
        private string $email,
        private ?string $phone,
        private Subject $subject,
        private string $message,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail invalide.');
        }
        if (trim($message) === '') {
            throw new \InvalidArgumentException('Le message est obligatoire.');
        }
    }

    public function name(): string { return $this->name; }
    public function email(): string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function subject(): Subject { return $this->subject; }
    public function message(): string { return $this->message; }
}
```

- [ ] **Step 5 : Lancer le test (succès attendu) & Commit**

Run: `vendor/bin/phpunit tests/Contact/Domain` → PASS.

```bash
git add src/Contact tests/Contact
git commit -m "feat(contact): ajoute Subject et ContactMessage"
```

---

### Task 9 : Contact — Mailer port, use case & adapter Symfony

**Files:**
- Create: `src/Contact/Domain/ContactMailerInterface.php`
- Create: `src/Contact/Application/SendContactMessage.php`
- Create: `src/Contact/Infrastructure/SymfonyContactMailer.php`
- Test: `tests/Contact/Application/SendContactMessageTest.php`

**Interfaces:**
- Consumes: `ContactMessage`, `Subject`, `symfony/mailer` (`MailerInterface`, `Email`).
- Produces:
  - `ContactMailerInterface::send(ContactMessage $message): void`.
  - `SendContactMessage` (invocable) : `__invoke(ContactMessage $message): void` — délègue au port.
  - `SymfonyContactMailer(MailerInterface $mailer, string $fromEmail, string $toEmail)` — construit l'e-mail (from configuré, to boutique, `replyTo` = e-mail du visiteur, sujet `[<label>] <nom>`, corps texte).

- [ ] **Step 1 : Écrire le test du use case (mailer espion)**

```php
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
```

- [ ] **Step 2 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Contact/Application`
Expected: FAIL — classes introuvables.

- [ ] **Step 3 : Implémenter le port & le use case**

```php
<?php
namespace App\Contact\Domain;

interface ContactMailerInterface
{
    public function send(ContactMessage $message): void;
}
```

```php
<?php
namespace App\Contact\Application;

use App\Contact\Domain\ContactMailerInterface;
use App\Contact\Domain\ContactMessage;

final readonly class SendContactMessage
{
    public function __construct(private ContactMailerInterface $mailer) {}

    public function __invoke(ContactMessage $message): void
    {
        $this->mailer->send($message);
    }
}
```

- [ ] **Step 4 : Implémenter l'adapter Symfony Mailer**

```php
<?php
namespace App\Contact\Infrastructure;

use App\Contact\Domain\ContactMailerInterface;
use App\Contact\Domain\ContactMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SymfonyContactMailer implements ContactMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $toEmail,
    ) {}

    public function send(ContactMessage $message): void
    {
        $body = sprintf(
            "Nom : %s\nE-mail : %s\nTéléphone : %s\nSujet : %s\n\n%s",
            $message->name(),
            $message->email(),
            $message->phone() ?? '—',
            $message->subject()->label(),
            $message->message(),
        );

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($this->toEmail)
            ->replyTo($message->email())
            ->subject(sprintf('[%s] %s', $message->subject()->label(), $message->name()))
            ->text($body);

        $this->mailer->send($email);
    }
}
```

- [ ] **Step 5 : Lancer le test (succès attendu) & Commit**

Run: `vendor/bin/phpunit tests/Contact/Application` → PASS.

```bash
git add src/Contact tests/Contact
git commit -m "feat(contact): use case d'envoi + adapter Symfony Mailer"
```

### Task 10 : Fondations UI — wiring, charte, fonts & extensions Twig

**Files:**
- Modify: `config/services.yaml`
- Modify: `.env` (ajout des adresses e-mail contact)
- Create: `assets/fonts/` (woff2 self-hostés) + `assets/styles/app.css` (remplacer le contenu)
- Copy: `assets/images/giulia-icon.png`, `giulia-logo.png`, `giulia-wordmark.png` (depuis `.claude/design-system/assets/`)
- Modify: `templates/base.html.twig`
- Create: `templates/components/_header.html.twig`, `_footer.html.twig`, `_status_badge.html.twig`
- Create: `src/Opening/UI/OpeningStatusExtension.php`
- Create: `src/Opening/UI/StatusController.php` (endpoint `/api/status`, requis par le badge)
- Create: `src/Shared/UI/EstablishmentExtension.php`
- Test: `tests/Opening/UI/OpeningStatusExtensionTest.php`

**Interfaces:**
- Consumes: `ScheduleRepositoryInterface`, `Clock`, `OpeningStatus`, `EstablishmentRepositoryInterface`.
- Produces: fonctions Twig `opening_status(): OpeningStatus` et `establishment(): Establishment` disponibles dans tous les templates ; alias de services câblés.

- [ ] **Step 1 : Câbler les services**

Remplacer la section `services:` de `config/services.yaml` par :

```yaml
parameters:
    giulia.data_dir: '%kernel.project_dir%/config/giulia'
    contact.from_email: '%env(CONTACT_FROM_EMAIL)%'
    contact.to_email: '%env(CONTACT_TO_EMAIL)%'

services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/**/Domain/'
            - '../src/Kernel.php'

    # Alias ports → adapters
    App\Opening\Domain\Clock: '@App\Opening\Infrastructure\SystemClock'
    App\Opening\Domain\ScheduleRepositoryInterface: '@App\Opening\Infrastructure\YamlScheduleRepository'
    App\Menu\Domain\MenuRepositoryInterface: '@App\Menu\Infrastructure\YamlMenuRepository'
    App\Shared\Domain\EstablishmentRepositoryInterface: '@App\Shared\Infrastructure\YamlEstablishmentRepository'
    App\Contact\Domain\ContactMailerInterface: '@App\Contact\Infrastructure\SymfonyContactMailer'

    # Chemins des sources de données
    App\Opening\Infrastructure\YamlScheduleRepository:
        arguments: { $file: '%giulia.data_dir%/hours.yaml' }
    App\Menu\Infrastructure\YamlMenuRepository:
        arguments: { $file: '%giulia.data_dir%/menu.yaml' }
    App\Shared\Infrastructure\YamlEstablishmentRepository:
        arguments: { $file: '%giulia.data_dir%/establishment.yaml' }
    App\Contact\Infrastructure\SymfonyContactMailer:
        arguments: { $fromEmail: '%contact.from_email%', $toEmail: '%contact.to_email%' }
```

Ajouter dans `.env` (sous le bloc mailer) :

```bash
CONTACT_FROM_EMAIL=no-reply@giulia-pizza-gorges.fr
CONTACT_TO_EMAIL=hello@giulia-pizza-gorges.fr
```

- [ ] **Step 2 : Installer les polices self-hostées**

Télécharger les woff2 via [google-webfonts-helper](https://gwfh.mranftl.com/fonts) (ou fontsource) et les placer dans `assets/fonts/` :
- Bricolage Grotesque : poids 500, 600, 700, 800 → `bricolage-500.woff2` … `bricolage-800.woff2`
- DM Sans : poids 400, 500, 600, 700 → `dmsans-400.woff2` … `dmsans-700.woff2`

Copier les logos : `cp .claude/design-system/assets/*.png assets/images/`.

- [ ] **Step 3 : Écrire `assets/styles/app.css`** (tokens de charte + `@font-face` + composants)

```css
@font-face { font-family: 'Bricolage Grotesque'; font-weight: 500; font-display: swap; src: url('../fonts/bricolage-500.woff2') format('woff2'); }
@font-face { font-family: 'Bricolage Grotesque'; font-weight: 600; font-display: swap; src: url('../fonts/bricolage-600.woff2') format('woff2'); }
@font-face { font-family: 'Bricolage Grotesque'; font-weight: 700; font-display: swap; src: url('../fonts/bricolage-700.woff2') format('woff2'); }
@font-face { font-family: 'Bricolage Grotesque'; font-weight: 800; font-display: swap; src: url('../fonts/bricolage-800.woff2') format('woff2'); }
@font-face { font-family: 'DM Sans'; font-weight: 400; font-display: swap; src: url('../fonts/dmsans-400.woff2') format('woff2'); }
@font-face { font-family: 'DM Sans'; font-weight: 500; font-display: swap; src: url('../fonts/dmsans-500.woff2') format('woff2'); }
@font-face { font-family: 'DM Sans'; font-weight: 600; font-display: swap; src: url('../fonts/dmsans-600.woff2') format('woff2'); }
@font-face { font-family: 'DM Sans'; font-weight: 700; font-display: swap; src: url('../fonts/dmsans-700.woff2') format('woff2'); }

:root {
  --cream: #f4ede0;
  --card: #fffdf8;
  --card-alt: #fbf6ec;
  --ink: #2a3138;
  --ink-hover: #20262c;
  --text: #4a4339;
  --text-soft: #6b6459;
  --text-muted: #8a8377;
  --terracotta: #d3a273;
  --terracotta-link: #b3743f;
  --green: #5c8a49;
  --green-deep: #4a6a3f;
  --red: #a24b32;
  --border: #e7ddca;
  --font-title: 'Bricolage Grotesque', system-ui, sans-serif;
  --font-body: 'DM Sans', system-ui, sans-serif;
}

* { box-sizing: border-box; }
body {
  margin: 0;
  background: var(--cream);
  background-image:
    radial-gradient(circle at 12% 8%, rgba(211,162,115,.10), transparent 42%),
    radial-gradient(circle at 88% 22%, rgba(127,178,205,.10), transparent 40%),
    radial-gradient(circle at 50% 100%, rgba(154,171,147,.14), transparent 55%);
  font-family: var(--font-body);
  color: var(--ink);
}
a { color: inherit; text-decoration: none; }
h1, h2, h3 { font-family: var(--font-title); letter-spacing: -.3px; }

.page { min-height: 100vh; display: flex; justify-content: center; padding: 22px 16px 56px; }
.container { width: 100%; max-width: 600px; }

.badge { display: inline-flex; align-items: center; gap: 8px; padding: 7px 13px; border-radius: 100px; font-weight: 600; font-size: 13.5px; }
.badge--open { background: #eaf1e6; border: 1px solid #cfe0c6; color: var(--green-deep); }
.badge--closed { background: #f0e7dc; border: 1px solid #ddcdb9; color: #8a6f57; }
.badge__dot { width: 8px; height: 8px; border-radius: 50%; }
.badge--open .badge__dot { background: var(--green); animation: gPulse 1.8s ease-in-out infinite; }
.badge--closed .badge__dot { background: #b98e5e; }
@keyframes gPulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }

.card { background: var(--card); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; }
.tag { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; padding: 5px 11px; border-radius: 100px; }
.tag--veg { color: var(--green-deep); background: #eaf1e6; border: 1px solid #cfe0c6; }
.tag--spicy { color: var(--red); background: #f7e6df; border: 1px solid #ecc9bd; }

.nav { display: flex; justify-content: center; gap: 7px; flex-wrap: wrap; margin: 12px 0; }
.nav a { font-size: 13.5px; font-weight: 600; color: var(--text-soft); padding: 7px 13px; border-radius: 100px; background: var(--card-alt); border: 1px solid var(--border); }
.nav a[aria-current="page"] { color: #3a4148; background: #efe4d1; border-color: #ddceb4; }

.cta-cc { display: block; background: var(--ink); color: var(--cream); border-radius: 20px; padding: 20px 22px; }
.footer { text-align: center; margin-top: 26px; font-size: 13px; color: var(--text-muted); }
```

> Détail visuel (grilles, panneaux horaires, fiches) : s'appuyer sur les maquettes `.claude/design-system/*.dc.html` pour le fignolage — le CSS ci-dessus pose les composants de base réutilisés par toutes les pages.

- [ ] **Step 4 : Écrire `templates/base.html.twig`**

```twig
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}Giulia — Pizzeria napolitaine à Gorges{% endblock %}</title>
    <meta name="description" content="{% block description %}Giulia, pizzeria napolitaine à Gorges : pizzas à emporter en click & collect, tout près de Clisson et Nantes.{% endblock %}">
    <link rel="icon" href="{{ asset('images/giulia-icon.png') }}">
    {% block stylesheets %}{% endblock %}
    {% block javascripts %}{{ importmap('app') }}{% endblock %}
</head>
<body>
    <div class="page"><div class="container">
        {% block header %}{{ include('components/_header.html.twig') }}{% endblock %}
        {% block body %}{% endblock %}
        {% block footer %}{{ include('components/_footer.html.twig') }}{% endblock %}
    </div></div>
</body>
</html>
```

- [ ] **Step 5 : Écrire les composants d'en-tête / pied / badge**

`templates/components/_status_badge.html.twig` :

```twig
{% set s = opening_status() %}
<div class="badge {{ s.isOpen ? 'badge--open' : 'badge--closed' }}"
     data-controller="live-status" data-live-status-url-value="{{ path('status') }}">
    <span class="badge__dot"></span>
    <span data-live-status-target="label">{{ s.label }}</span>
</div>
```

`templates/components/_header.html.twig` :

```twig
{% set e = establishment() %}
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <a href="{{ path('home') }}" style="display:flex;align-items:center;gap:9px;">
        <img src="{{ asset('images/giulia-icon.png') }}" alt="Giulia" style="height:34px;">
        <span style="font-family:var(--font-title);font-weight:600;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#a08d72;">{{ e.tagline }}</span>
    </a>
    {{ include('components/_status_badge.html.twig') }}
</div>
<nav class="nav">
    <a href="{{ path('home') }}">Accueil</a>
    <a href="{{ path('menu_index') }}">Carte</a>
    <a href="{{ path('contact') }}">Contact</a>
</nav>
```

> La navigation active (`aria-current`) est ajoutée en Task 15. Les routes `menu_index` / `contact` référencées ici n'existent qu'aux Tasks 13/14 : c'est sans effet tant qu'on ne rend pas une page (le premier rendu réel est l'accueil en Task 11, qui n'a besoin que de `home` et `status`).
```

`templates/components/_footer.html.twig` :

```twig
{% set e = establishment() %}
<div class="footer">
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:9px;">
        <a href="{{ path('menu_index') }}">Nos pizzas</a><span>·</span>
        <a href="{{ path('contact') }}">Contact</a><span>·</span>
        <a href="{{ path('legal') }}">Mentions légales</a>
    </div>
    © {{ 'now'|date('Y') }} {{ e.name }} · Pizzeria napolitaine · Gorges
</div>
```

- [ ] **Step 6 : Écrire les extensions Twig**

```php
<?php
namespace App\Opening\UI;

use App\Opening\Domain\Clock;
use App\Opening\Domain\OpeningStatus;
use App\Opening\Domain\ScheduleRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OpeningStatusExtension extends AbstractExtension
{
    public function __construct(
        private ScheduleRepositoryInterface $schedule,
        private Clock $clock,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('opening_status', $this->status(...))];
    }

    public function status(): OpeningStatus
    {
        return OpeningStatus::compute($this->schedule->schedule(), $this->clock->now());
    }
}
```

```php
<?php
namespace App\Shared\UI;

use App\Shared\Domain\Establishment;
use App\Shared\Domain\EstablishmentRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EstablishmentExtension extends AbstractExtension
{
    public function __construct(private EstablishmentRepositoryInterface $repository) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('establishment', $this->repository->get(...))];
    }
}
```

- [ ] **Step 6b : Créer l'endpoint de statut (route `status`)**

Le badge partagé (`_status_badge.html.twig`) référence `path('status')` ; la route doit donc exister dès le socle, avant tout rendu de page. Créer `src/Opening/UI/StatusController.php` :

```php
<?php
namespace App\Opening\UI;

use App\Opening\Domain\Clock;
use App\Opening\Domain\OpeningStatus;
use App\Opening\Domain\ScheduleRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController
{
    #[Route('/api/status', name: 'status', methods: ['GET'])]
    public function __invoke(ScheduleRepositoryInterface $schedule, Clock $clock): JsonResponse
    {
        $status = OpeningStatus::compute($schedule->schedule(), $clock->now());

        return new JsonResponse([
            'open' => $status->isOpen(),
            'label' => $status->label(),
            'detail' => $status->detail(),
        ]);
    }
}
```

- [ ] **Step 7 : Écrire le test de l'extension statut**

```php
<?php
namespace App\Tests\Opening\UI;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Opening\UI\OpeningStatusExtension;
use App\Shared\Domain\Weekday;
use App\Tests\Opening\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class OpeningStatusExtensionTest extends TestCase
{
    public function test_status_uses_injected_schedule_and_clock(): void
    {
        $repo = new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([Weekday::Tuesday->value => [TimeRange::fromMinutes(600, 870)]]);
            }
        };
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-21 12:00', new \DateTimeZone('Europe/Paris')));
        $extension = new OpeningStatusExtension($repo, $clock);

        self::assertTrue($extension->status()->isOpen());
    }
}
```

- [ ] **Step 8 : Vérifier le câblage & lancer les tests**

Run: `php bin/console lint:container` → OK (aucune erreur de wiring).
Run: `php bin/console debug:twig | grep -E 'opening_status|establishment'` → les deux fonctions apparaissent.
Run: `vendor/bin/phpunit tests/Opening/UI` → PASS.

- [ ] **Step 9 : Commit**

```bash
git add config/services.yaml .env assets templates src/Opening/UI src/Shared/UI tests/Opening/UI
git commit -m "feat(ui): socle — charte, polices, layout et extensions Twig"
```

---

### Task 11 : Page d'accueil (link-in-bio)

**Files:**
- Create: `src/Home/UI/HomeController.php`
- Create: `templates/home/index.html.twig`
- Test: `tests/Functional/HomePageTest.php`

**Interfaces:**
- Consumes: `MenuRepositoryInterface` (featured + slider), `establishment()` / `opening_status()` (Twig). Route nommée **`home`** (`/`).
- Produces: route `home`. Template `home/index.html.twig`.

- [ ] **Step 1 : Écrire le smoke test**

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function test_home_renders_key_blocks(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge'); // statut d'ouverture
        self::assertSelectorTextContains('body', 'Click & Collect');
        self::assertSelectorTextContains('body', 'La Fresca'); // pizza du moment
    }
}
```

- [ ] **Step 2 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Functional/HomePageTest.php`
Expected: FAIL — route `/` inexistante (404).

- [ ] **Step 3 : Écrire le HomeController**

```php
<?php
namespace App\Home\UI;

use App\Menu\Domain\MenuRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(MenuRepositoryInterface $menu): Response
    {
        return $this->render('home/index.html.twig', [
            'featured' => $menu->featured(),
            'categories' => $menu->categories(),
        ]);
    }
}
```

- [ ] **Step 4 : Écrire `templates/home/index.html.twig`**

Porter la structure de la maquette `Giulia.dc.html` (annonce MOTD, pizza du moment, slider, Click & Collect, liens link-in-bio, horaires). Squelette minimal fonctionnel :

```twig
{% extends 'base.html.twig' %}
{% block body %}
    {% set e = establishment() %}
    {% set motd = e.announcement %}
    {% if motd.isActive %}
        <div class="card" style="padding:14px 16px;margin:16px 0;">
            <strong>{{ motd.title }}</strong> — {{ motd.text }}
        </div>
    {% endif %}

    {% if featured %}
        <a href="{{ path('menu_show', { slug: featured.slug }) }}" class="card" style="display:block;padding:18px;margin:16px 0;background:var(--ink);color:var(--cream);">
            <span class="tag" style="background:var(--terracotta);color:var(--ink);">Du moment</span>
            <h2 style="margin:10px 0 4px;color:var(--cream);">{{ featured.name }}</h2>
            <div style="color:#c3bbac;font-size:13px;">{{ featured.ingredients|join(' · ') }}</div>
            <div style="font-family:var(--font-title);font-weight:700;margin-top:8px;color:#a9c39a;">{{ featured.price.format }}</div>
        </a>
    {% endif %}

    <div id="pizzaScroller" data-controller="pizza-slider" style="display:flex;gap:12px;overflow-x:auto;padding-bottom:8px;">
        {% for category in categories %}{% for pizza in category.pizzas %}
            <a href="{{ path('menu_show', { slug: pizza.slug }) }}" class="card" style="min-width:200px;padding:14px;">
                <div style="font-family:var(--font-title);font-weight:800;">{{ pizza.name }}</div>
                <div style="font-size:12.5px;color:var(--text-muted);margin-top:6px;">{{ pizza.ingredients|join(' · ') }}</div>
                <div style="font-family:var(--font-title);font-weight:700;color:var(--green);margin-top:8px;">{{ pizza.price.format }}</div>
            </a>
        {% endfor %}{% endfor %}
    </div>

    <a id="commander" href="{{ e.menuPdfUrl }}" class="cta-cc" style="margin-top:16px;">
        <div style="font-family:var(--font-title);font-weight:800;font-size:19px;">Commander en Click &amp; Collect</div>
        <div style="font-size:13.5px;color:#b9b1a4;">Commandez en ligne, retirez sur place</div>
    </a>

    <div style="display:flex;flex-direction:column;gap:8px;margin-top:16px;">
        <a class="card" style="padding:14px;" href="{{ e.directionsUrl }}">Itinéraire</a>
        <a class="card" style="padding:14px;" href="tel:{{ e.phoneHref }}">Appeler · {{ e.phone }}</a>
        <a class="card" style="padding:14px;" href="{{ e.googleReviewsUrl }}">Laisser un avis Google</a>
        <a class="card" style="padding:14px;" href="{{ e.whatsappUrl }}">Groupe WhatsApp anti-gaspi</a>
        {% for link in e.socialLinks %}
            <a class="card" style="padding:14px;" href="{{ link.url }}">{{ link.label }}</a>
        {% endfor %}
    </div>

    {{ include('components/_hours.html.twig') }}
{% endblock %}
```

> Le composant horaires `_hours.html.twig` est créé en Task 13 (partagé accueil/carte). Pour cette tâche, inclure un placeholder inline `<div id="contact"></div>` puis remplacer en Task 13, **ou** créer `_hours.html.twig` maintenant (voir Task 13 Step 4) — au choix de l'exécutant, mais le test de cette tâche ne dépend pas des horaires.

Pour que le test passe sans `_hours.html.twig`, remplacer la dernière ligne par `<div id="contact"></div>` dans cette tâche.

- [ ] **Step 5 : Lancer le test (succès attendu)**

Run: `vendor/bin/phpunit tests/Functional/HomePageTest.php`
Expected: PASS.

- [ ] **Step 6 : Commit**

```bash
git add src/Home tests/Functional/HomePageTest.php templates/home
git commit -m "feat(home): page d'accueil link-in-bio"
```

### Task 12 : Statut « en direct » — endpoint & Stimulus

**Files:**
- Create: `src/Opening/UI/StatusController.php`
- Create: `assets/controllers/live_status_controller.js`
- Test: `tests/Functional/StatusEndpointTest.php`

**Interfaces:**
- Consumes: `ScheduleRepositoryInterface`, `Clock`, `OpeningStatus`.
- Produces: route **`status`** (`GET /api/status`) → JSON `{ "open": bool, "label": string, "detail": string }`. Contrôleur Stimulus `live-status` (valeurs `url`, `refresh` défaut 60000 ms ; cible `label`).

- [ ] **Step 1 : Écrire le smoke test**

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatusEndpointTest extends WebTestCase
{
    public function test_status_endpoint_returns_json(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/status');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('open', $data);
        self::assertArrayHasKey('label', $data);
        self::assertArrayHasKey('detail', $data);
        self::assertIsBool($data['open']);
    }
}
```

- [ ] **Step 2 : Lancer le test (échec attendu)**

Run: `vendor/bin/phpunit tests/Functional/StatusEndpointTest.php`
Expected: FAIL — route inexistante.

- [ ] **Step 3 : Vérifier le StatusController**

Le `StatusController` (route `status`, `GET /api/status`) a déjà été créé au socle en **Task 10 (Step 6b)**. Aucun code à écrire ici : cette tâche ajoute le rafraîchissement client et le test de l'endpoint. Vérifier qu'il existe : `php bin/console debug:router status`.

- [ ] **Step 4 : Écrire le contrôleur Stimulus `live-status`**

```javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, refresh: { type: Number, default: 60000 } };
    static targets = ['label'];

    connect() {
        this.timer = setInterval(() => this.refresh(), this.refreshValue);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    async refresh() {
        try {
            const res = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (this.hasLabelTarget) this.labelTarget.textContent = data.label;
            this.element.classList.toggle('badge--open', data.open);
            this.element.classList.toggle('badge--closed', !data.open);
        } catch (e) { /* silencieux : on garde l'état serveur */ }
    }
}
```

- [ ] **Step 5 : Lancer le test (succès attendu) & Commit**

Run: `vendor/bin/phpunit tests/Functional/StatusEndpointTest.php` → PASS.

```bash
git add assets/controllers/live_status_controller.js tests/Functional/StatusEndpointTest.php
git commit -m "feat(opening): rafraîchissement Stimulus du badge + test de l'endpoint"
```

---

### Task 13 : Carte, fiche pizza, horaires & slider

**Files:**
- Create: `src/Menu/UI/MenuController.php`
- Create: `templates/menu/index.html.twig`, `templates/menu/show.html.twig`
- Create: `templates/components/_hours.html.twig`
- Modify: `src/Opening/UI/OpeningStatusExtension.php` (ajout `weekly_hours`)
- Create: `assets/controllers/pizza_slider_controller.js`
- Test: `tests/Functional/MenuPageTest.php`
- Test: `tests/Opening/UI/WeeklyHoursTest.php`

**Interfaces:**
- Consumes: `MenuRepositoryInterface`, `WeeklySchedule`, `Weekday`, `TimeRange`, `Clock`.
- Produces: routes **`menu_index`** (`/nos-pizzas`), **`menu_show`** (`/nos-pizzas/{slug}`). Fonction Twig `weekly_hours(): array` (liste `{ day: string, hours: string, today: bool }`, lundi→dimanche).

- [ ] **Step 1 : Écrire les tests fonctionnels menu**

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MenuPageTest extends WebTestCase
{
    public function test_menu_index_lists_categories(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Les rouges');
        self::assertSelectorTextContains('body', 'Margherita');
        self::assertSelectorTextContains('body', 'La signature');
    }

    public function test_pizza_page_shows_details(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas/la-fresca');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'La Fresca');
        self::assertSelectorTextContains('body', 'bresaola');
    }

    public function test_unknown_pizza_returns_404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nos-pizzas/inexistante');
        self::assertResponseStatusCodeSame(404);
    }
}
```

- [ ] **Step 2 : Écrire le test de weekly_hours**

```php
<?php
namespace App\Tests\Opening\UI;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Opening\UI\OpeningStatusExtension;
use App\Shared\Domain\Weekday;
use App\Tests\Opening\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class WeeklyHoursTest extends TestCase
{
    public function test_weekly_hours_labels_days_and_marks_today(): void
    {
        $repo = new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([
                    Weekday::Tuesday->value => [TimeRange::fromMinutes(600, 870), TimeRange::fromMinutes(1020, 1290)],
                ]);
            }
        };
        // 2026-07-20 = lundi
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-20 12:00', new \DateTimeZone('Europe/Paris')));
        $rows = (new OpeningStatusExtension($repo, $clock))->weeklyHours();

        self::assertSame('Lundi', $rows[0]['day']);
        self::assertTrue($rows[0]['today']);
        self::assertSame('Fermé', $rows[0]['hours']);
        self::assertSame('Mardi', $rows[1]['day']);
        self::assertSame('10h – 14h30 · 17h – 21h30', $rows[1]['hours']);
    }
}
```

- [ ] **Step 3 : Lancer les tests (échec attendu)**

Run: `vendor/bin/phpunit tests/Functional/MenuPageTest.php tests/Opening/UI/WeeklyHoursTest.php`
Expected: FAIL — routes / méthode inexistantes.

- [ ] **Step 4 : Ajouter `weekly_hours` à l'extension**

Modifier `src/Opening/UI/OpeningStatusExtension.php` : ajouter la fonction et la méthode.

```php
    // dans getFunctions(), ajouter :
    new TwigFunction('weekly_hours', $this->weeklyHours(...)),
```

```php
    /** @return array<int, array{day: string, hours: string, today: bool}> */
    public function weeklyHours(): array
    {
        $schedule = $this->schedule->schedule();
        $today = \App\Shared\Domain\Weekday::fromDate($this->clock->now());
        $rows = [];
        foreach (\App\Shared\Domain\Weekday::cases() as $day) {
            $ranges = $schedule->rangesFor($day);
            $hours = $ranges === []
                ? 'Fermé'
                : implode(' · ', array_map(
                    static fn ($r) => $r->openLabel() . ' – ' . $r->closeLabel(),
                    $ranges,
                ));
            $rows[] = ['day' => $day->label(), 'hours' => $hours, 'today' => $day === $today];
        }
        return $rows;
    }
```

- [ ] **Step 5 : Écrire le composant horaires `templates/components/_hours.html.twig`**

```twig
{% set e = establishment() %}
<div id="contact" class="card" style="padding:20px 22px;margin-top:26px;">
    <h2 style="font-size:16px;margin:0 0 14px;">Horaires d'ouverture · Pizzeria Giulia Gorges</h2>
    {% for row in weekly_hours() %}
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid #f0e8d9;font-size:14.5px;{{ row.today ? 'font-weight:700;' : '' }}">
            <span style="color:var(--text);">{{ row.day }}{{ row.today ? ' (aujourd’hui)' : '' }}</span>
            <span style="color:var(--text-soft);">{{ row.hours }}</span>
        </div>
    {% endfor %}
    <div style="border-top:1px solid #f0e8d9;margin-top:14px;padding-top:16px;display:flex;flex-direction:column;gap:8px;font-size:14px;color:var(--text-soft);">
        <div>{{ e.address }}</div>
        <div><a href="tel:{{ e.phoneHref }}">{{ e.phone }}</a></div>
        <div><a href="mailto:{{ e.email }}">{{ e.email }}</a></div>
    </div>
</div>
```

Si la Task 11 a laissé `<div id="contact"></div>`, le remplacer par `{{ include('components/_hours.html.twig') }}`.

- [ ] **Step 6 : Écrire le MenuController**

```php
<?php
namespace App\Menu\UI;

use App\Menu\Domain\MenuRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/nos-pizzas', name: 'menu_index', methods: ['GET'])]
    public function index(MenuRepositoryInterface $menu): Response
    {
        return $this->render('menu/index.html.twig', ['categories' => $menu->categories()]);
    }

    #[Route('/nos-pizzas/{slug}', name: 'menu_show', methods: ['GET'])]
    public function show(string $slug, MenuRepositoryInterface $menu): Response
    {
        $pizza = $menu->findBySlug($slug);
        if ($pizza === null) {
            throw $this->createNotFoundException('Pizza introuvable.');
        }

        return $this->render('menu/show.html.twig', ['pizza' => $pizza]);
    }
}
```

- [ ] **Step 7 : Écrire `templates/menu/index.html.twig`**

```twig
{% extends 'base.html.twig' %}
{% block title %}Toutes nos pizzas — Giulia, pizzeria napolitaine à Gorges{% endblock %}
{% block body %}
    <div style="margin:20px 0 6px;">
        <h1 style="font-size:34px;margin:0;">Toutes nos pizzas</h1>
        <p style="color:var(--text-soft);max-width:460px;">Pâte napolitaine maturée 48h, 33 cm, à emporter en <strong>click &amp; collect</strong>.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0;">
        <span class="tag tag--veg">🌱 Végétarienne</span>
        <span class="tag tag--spicy">🌶️ Piquante</span>
    </div>

    {% for category in categories %}
        <div style="margin-top:34px;">
            <div style="font-family:var(--font-title);font-weight:700;font-size:11.5px;letter-spacing:2px;text-transform:uppercase;color:#a08d72;">{{ category.kicker }}</div>
            <h2 style="margin:3px 0 15px;font-size:22px;">{{ category.label }}</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(178px,1fr));gap:12px;">
                {% for pizza in category.pizzas %}
                    <a href="{{ path('menu_show', { slug: pizza.slug }) }}" class="card" style="display:flex;flex-direction:column;padding:13px 15px 15px;">
                        <div style="display:flex;justify-content:space-between;gap:8px;">
                            <div style="font-family:var(--font-title);font-weight:800;font-size:18px;">{{ pizza.name }}</div>
                            <div style="display:flex;gap:4px;">
                                {% for tag in pizza.tags %}<span title="{{ tag.label }}">{{ tag.icon }}</span>{% endfor %}
                            </div>
                        </div>
                        <div style="font-size:12.5px;color:var(--text-muted);margin-top:7px;flex:1;">{{ pizza.ingredients|join(' · ') }}</div>
                        <div style="font-family:var(--font-title);font-weight:700;color:var(--green);margin-top:11px;">{{ pizza.price.format }}</div>
                    </a>
                {% endfor %}
            </div>
        </div>
    {% endfor %}

    {{ include('components/_hours.html.twig') }}
{% endblock %}
```

- [ ] **Step 8 : Écrire `templates/menu/show.html.twig`**

```twig
{% extends 'base.html.twig' %}
{% block title %}{{ pizza.name }} — Giulia, pizzeria napolitaine à Gorges{% endblock %}
{% block body %}
    <a href="{{ path('menu_index') }}" style="display:inline-block;margin:16px 0;color:var(--terracotta-link);font-weight:600;">← Toutes nos pizzas</a>
    <div class="card" style="padding:24px;">
        {% if pizza.isFeatured %}<span class="tag" style="background:var(--terracotta);color:var(--ink);">Du moment</span>{% endif %}
        <h1 style="font-size:32px;margin:10px 0 6px;">{{ pizza.name }}</h1>
        <div style="display:flex;gap:6px;margin-bottom:10px;">
            {% for tag in pizza.tags %}<span class="tag {{ tag == constant('App\\Menu\\Domain\\Tag::Vegetarian') ? 'tag--veg' : 'tag--spicy' }}">{{ tag.icon }} {{ tag.label }}</span>{% endfor %}
        </div>
        <div style="font-family:var(--font-title);font-weight:700;font-size:22px;color:var(--green);">{{ pizza.price.format }}</div>
        <h3 style="margin:18px 0 6px;font-size:14px;">Ingrédients</h3>
        <div style="color:var(--text-soft);">{{ pizza.ingredients|join(' · ') }}</div>
        {% if pizza.allergens is not empty %}
            <h3 style="margin:18px 0 6px;font-size:14px;">Allergènes</h3>
            <div style="color:var(--text-soft);">{{ pizza.allergens|join(', ') }}</div>
        {% endif %}
    </div>
{% endblock %}
```

- [ ] **Step 9 : Écrire le contrôleur Stimulus `pizza-slider`**

```javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { interval: { type: Number, default: 3200 }, step: { type: Number, default: 220 } };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        this.paused = false;
        this.element.addEventListener('pointerenter', this.pause);
        this.element.addEventListener('pointerleave', this.resume);
        this.timer = setInterval(() => this.tick(), this.intervalValue);
    }

    disconnect() {
        clearInterval(this.timer);
        this.element.removeEventListener('pointerenter', this.pause);
        this.element.removeEventListener('pointerleave', this.resume);
    }

    pause = () => { this.paused = true; };
    resume = () => { this.paused = false; };

    tick() {
        if (this.paused) return;
        const el = this.element;
        const max = el.scrollWidth - el.clientWidth;
        if (el.scrollLeft >= max - 4) el.scrollTo({ left: 0, behavior: 'smooth' });
        else el.scrollBy({ left: this.stepValue, behavior: 'smooth' });
    }
}
```

- [ ] **Step 10 : Lancer les tests (succès attendu)**

Run: `vendor/bin/phpunit tests/Functional/MenuPageTest.php tests/Opening/UI/WeeklyHoursTest.php`
Expected: PASS.

- [ ] **Step 11 : Commit**

```bash
git add src/Menu/UI src/Opening/UI/OpeningStatusExtension.php templates/menu templates/components/_hours.html.twig assets/controllers/pizza_slider_controller.js tests/Functional/MenuPageTest.php tests/Opening/UI/WeeklyHoursTest.php
git commit -m "feat(menu): carte, fiche pizza par slug, horaires et slider"
```

### Task 14 : Formulaire de contact (serveur → Mailer)

**Files:**
- Create: `src/Contact/UI/ContactFormData.php`
- Create: `src/Contact/UI/ContactType.php`
- Create: `src/Contact/UI/ContactController.php`
- Create: `templates/contact/index.html.twig`
- Test: `tests/Functional/ContactPageTest.php`

**Interfaces:**
- Consumes: `Subject`, `ContactMessage`, `SendContactMessage`, `symfony/form`, `symfony/validator`.
- Produces: route **`contact`** (`GET`/`POST` `/contact`). DTO `ContactFormData` (propriétés publiques `name`, `email`, `phone`, `subject`, `message` avec contraintes Validator).

- [ ] **Step 1 : Écrire les tests fonctionnels**

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactPageTest extends WebTestCase
{
    public function test_form_is_displayed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function test_valid_submission_sends_email_and_redirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        $client->submitForm('Envoyer', [
            'contact[name]' => 'Marie Dupont',
            'contact[email]' => 'marie@example.fr',
            'contact[phone]' => '0612345678',
            'contact[subject]' => 'cc',
            'contact[message]' => 'Bonjour, une question sur le click & collect.',
        ]);

        self::assertEmailCount(1);
        self::assertResponseRedirects('/contact');
    }

    public function test_invalid_submission_shows_errors(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        $client->submitForm('Envoyer', [
            'contact[name]' => '',
            'contact[email]' => 'pas-un-email',
            'contact[subject]' => 'general',
            'contact[message]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }
}
```

- [ ] **Step 2 : Lancer les tests (échec attendu)**

Run: `vendor/bin/phpunit tests/Functional/ContactPageTest.php`
Expected: FAIL — route inexistante.

- [ ] **Step 3 : Écrire le DTO `ContactFormData`**

```php
<?php
namespace App\Contact\UI;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactFormData
{
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Indiquez votre e-mail.')]
    #[Assert\Email(message: 'E-mail invalide.')]
    public string $email = '';

    public ?string $phone = null;

    #[Assert\NotBlank]
    public string $subject = 'general';

    #[Assert\NotBlank(message: 'Écrivez votre message.')]
    #[Assert\Length(min: 5, minMessage: 'Message trop court.')]
    public string $message = '';
}
```

- [ ] **Step 4 : Écrire `ContactType`**

```php
<?php
namespace App\Contact\UI;

use App\Contact\Domain\Subject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'E-mail'])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('subject', ChoiceType::class, ['label' => 'Sujet', 'choices' => Subject::choices()])
            ->add('message', TextareaType::class, ['label' => 'Message']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactFormData::class]);
    }
}
```

- [ ] **Step 5 : Écrire le ContactController**

```php
<?php
namespace App\Contact\UI;

use App\Contact\Application\SendContactMessage;
use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, SendContactMessage $send): Response
    {
        $data = new ContactFormData();
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $send(new ContactMessage(
                $data->name,
                $data->email,
                $data->phone ?: null,
                Subject::from($data->subject),
                $data->message,
            ));
            $this->addFlash('success', 'Merci ! Votre message a bien été envoyé.');

            return $this->redirectToRoute('contact');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('contact/index.html.twig', ['form' => $form], new Response(status: $status));
    }
}
```

- [ ] **Step 6 : Écrire `templates/contact/index.html.twig`**

```twig
{% extends 'base.html.twig' %}
{% block title %}Contact — Giulia, pizzeria napolitaine à Gorges{% endblock %}
{% block body %}
    <h1 style="font-size:32px;margin:20px 0 6px;">Nous contacter</h1>
    <p style="color:var(--text-soft);">Une question, une allergie, une grande commande ? Écrivez-nous.</p>

    {% for message in app.flashes('success') %}
        <div class="card" style="padding:14px 16px;margin:16px 0;background:#eaf1e6;border-color:#cfe0c6;color:var(--green-deep);">{{ message }}</div>
    {% endfor %}

    <div class="card" style="padding:22px;margin-top:16px;">
        {{ form_start(form) }}
            {{ form_row(form.name) }}
            {{ form_row(form.email) }}
            {{ form_row(form.phone) }}
            {{ form_row(form.subject) }}
            {{ form_row(form.message) }}
            <button type="submit" class="cta-cc" style="border:0;cursor:pointer;width:100%;margin-top:10px;font-family:var(--font-title);font-weight:800;font-size:16px;">Envoyer</button>
        {{ form_end(form) }}
    </div>

    {{ include('components/_hours.html.twig') }}
{% endblock %}
```

- [ ] **Step 7 : Lancer les tests (succès attendu)**

Run: `vendor/bin/phpunit tests/Functional/ContactPageTest.php`
Expected: PASS (3 tests).

- [ ] **Step 8 : Commit**

```bash
git add src/Contact/UI templates/contact tests/Functional/ContactPageTest.php
git commit -m "feat(contact): formulaire serveur avec envoi d'e-mail"
```

---

### Task 15 : Mentions légales & finitions

**Files:**
- Create: `src/Home/UI/LegalController.php`
- Create: `templates/legal/mentions.html.twig`
- Modify: `templates/components/_header.html.twig` (marquer `aria-current` sur la page active)
- Test: `tests/Functional/LegalPageTest.php`
- Test: `tests/Functional/SmokeAllRoutesTest.php`

**Interfaces:**
- Consumes: `establishment()`.
- Produces: route **`legal`** (`/mentions-legales`).

- [ ] **Step 1 : Écrire les tests**

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPageTest extends WebTestCase
{
    public function test_legal_page_renders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
    }
}
```

```php
<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeAllRoutesTest extends WebTestCase
{
    /** @dataProvider routes */
    public function test_route_is_successful(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
    }

    public static function routes(): iterable
    {
        yield ['/'];
        yield ['/nos-pizzas'];
        yield ['/nos-pizzas/margherita'];
        yield ['/contact'];
        yield ['/mentions-legales'];
        yield ['/api/status'];
    }
}
```

- [ ] **Step 2 : Lancer les tests (échec attendu)**

Run: `vendor/bin/phpunit tests/Functional/LegalPageTest.php`
Expected: FAIL — route inexistante.

- [ ] **Step 3 : Écrire le LegalController**

```php
<?php
namespace App\Home\UI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }
}
```

- [ ] **Step 4 : Écrire `templates/legal/mentions.html.twig`**

```twig
{% extends 'base.html.twig' %}
{% block title %}Mentions légales — Giulia{% endblock %}
{% block body %}
    {% set e = establishment() %}
    <h1 style="font-size:32px;margin:20px 0 16px;">Mentions légales</h1>
    <div class="card" style="padding:22px;line-height:1.6;color:var(--text-soft);">
        <h2 style="font-size:16px;">Éditeur</h2>
        <p>{{ e.name }} — {{ e.address }}<br>Tél. {{ e.phone }} · {{ e.email }}</p>
        <p>SIRET, RCS et TVA intracommunautaire : à compléter avant mise en ligne.</p>
        <h2 style="font-size:16px;">Hébergeur</h2>
        <p>Hébergeur du site : à compléter (nom, adresse, téléphone).</p>
        <h2 style="font-size:16px;">Propriété intellectuelle</h2>
        <p>L'ensemble des contenus (textes, visuels, logos) est la propriété de {{ e.name }}, sauf mention contraire.</p>
    </div>
{% endblock %}
```

> Les champs « à compléter » (SIRET/RCS/TVA, hébergeur) sont des informations légales que **seul le client peut fournir** ; ce ne sont pas des placeholders techniques mais des données en attente côté métier.

- [ ] **Step 5 : Marquer la navigation active**

Dans `templates/components/_header.html.twig`, remplacer les liens de `.nav` par une version qui pose `aria-current` selon la route courante :

```twig
<nav class="nav">
    <a href="{{ path('home') }}" {{ app.request.attributes.get('_route') == 'home' ? 'aria-current="page"' : '' }}>Accueil</a>
    <a href="{{ path('menu_index') }}" {{ app.request.attributes.get('_route') starts with 'menu' ? 'aria-current="page"' : '' }}>Carte</a>
    <a href="{{ path('contact') }}" {{ app.request.attributes.get('_route') == 'contact' ? 'aria-current="page"' : '' }}>Contact</a>
</nav>
```

- [ ] **Step 6 : Lancer TOUTE la suite de tests**

Run: `vendor/bin/phpunit`
Expected: PASS — toute la suite verte (domaine + fonctionnel + smoke des 6 routes).

- [ ] **Step 7 : Vérifs finales**

Run: `php bin/console lint:twig templates`
Run: `php bin/console lint:container`
Expected: aucune erreur.

- [ ] **Step 8 : Commit**

```bash
git add src/Home/UI/LegalController.php templates/legal templates/components/_header.html.twig tests/Functional/LegalPageTest.php tests/Functional/SmokeAllRoutesTest.php
git commit -m "feat(legal): mentions légales, navigation active et smoke global"
```

---

## Self-Review (revue du plan vs spec)

**Couverture de la spec :**
- §3 Architecture modular monolith → structure appliquée Tasks 1–15 (`src/<Context>/…`, alias Task 10). ✓
- §4.1 Opening (OpeningStatus, Clock, WeeklySchedule, TimeRange) → Tasks 2–4. ✓
- §4.2 Menu (Pizza, Category, Tag, Money, allergens) → Tasks 1, 5, 6. ✓
- §4.3 Contact (Subject, ContactMessage, use case, mailer) → Tasks 8, 9, 14. ✓
- §4.4 Shared (Establishment, Announcement, SocialLink, Weekday) → Tasks 1, 7. ✓
- §5 Données YAML `config/giulia/` → Tasks 4, 6, 7. ✓
- §6 Pages & routes (les 6 routes) → Tasks 11, 12, 13, 14, 15. ✓
- §7 Statut live (serveur + Stimulus + /api/status) → Tasks 10 (badge), 12. ✓
- §8 Présentation (base.html.twig, charte CSS, fonts self-host, composants, Stimulus, logos) → Tasks 10, 11, 13. ✓
- §9 Tests (domaine à fond + smoke UI) → tests dans chaque tâche + Task 15 (suite complète). ✓
- §10 Hors périmètre → aucune tâche n'introduit DB/admin/commande/multilingue. ✓

**Cohérence des types :** `OpeningStatus::compute(WeeklySchedule, \DateTimeImmutable)` identique Tasks 3/10/12 ; `MenuRepositoryInterface` (`categories`/`findBySlug`/`featured`) identique Tasks 6/11/13 ; `Subject::choices()` label⇒value cohérent Tasks 8/14 ; `Money::format()` avec `\u{00A0}` cohérent Tasks 1/5/6. ✓

**Placeholders :** les seules mentions « à compléter » (SIRET, hébergeur, URL externes) sont des **données métier fournies par le client**, explicitement signalées — pas des trous techniques. ✓

**Note d'exécution :** exécuter les tâches dans l'ordre (dépendances strictes Shared → Opening → Menu → Contact → UI). Task 11 crée un placeholder `<div id="contact"></div>` remplacé par le composant horaires en Task 13.

