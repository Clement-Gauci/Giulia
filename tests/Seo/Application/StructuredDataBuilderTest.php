<?php
namespace App\Tests\Seo\Application;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Opening\Domain\TimeRange;
use App\Opening\Domain\WeeklySchedule;
use App\Seo\Application\StructuredDataBuilder;
use App\Shared\Domain\Weekday;
use App\Shared\Infrastructure\YamlEstablishmentRepository;
use PHPUnit\Framework\TestCase;

final class StructuredDataBuilderTest extends TestCase
{
    private function builder(): StructuredDataBuilder
    {
        $establishments = new YamlEstablishmentRepository(__DIR__ . '/fixtures/establishment.yaml');

        $schedule = new class implements ScheduleRepositoryInterface {
            public function schedule(): WeeklySchedule
            {
                return new WeeklySchedule([
                    Weekday::Tuesday->value => [
                        TimeRange::fromMinutes(600, 870),   // 10:00 – 14:30
                        TimeRange::fromMinutes(1020, 1290), // 17:00 – 21:30
                    ],
                    Weekday::Sunday->value => [
                        TimeRange::fromMinutes(1080, 1290), // 18:00 – 21:30
                    ],
                ]);
            }
        };

        return new StructuredDataBuilder($establishments, $schedule, 'https://giulia-pizza-gorges.fr');
    }

    public function test_it_builds_a_restaurant_with_nap_and_geo(): void
    {
        $data = $this->builder()->build('https://giulia-pizza-gorges.fr/img.png');

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('Restaurant', $data['@type']);
        self::assertSame('Giulia', $data['name']);
        self::assertSame('+33285528742', $data['telephone']);
        self::assertSame(47.1002191, $data['geo']['latitude']);
        self::assertSame(-1.3070557, $data['geo']['longitude']);
        self::assertSame('https://giulia-pizza-gorges.fr/img.png', $data['image']);
        self::assertSame('https://giulia-pizza-gorges.fr/menu.pdf', $data['hasMenu']);
    }

    public function test_it_splits_the_french_address(): void
    {
        $address = $this->builder()->build('https://x/i.png')['address'];

        self::assertSame('PostalAddress', $address['@type']);
        self::assertSame('44190', $address['postalCode']);
        self::assertSame('Gorges', $address['addressLocality']);
        self::assertSame('FR', $address['addressCountry']);
        self::assertStringContainsString('Cité des Sports', $address['streetAddress']);
    }

    public function test_it_maps_opening_hours_from_the_schedule(): void
    {
        $hours = $this->builder()->build('https://x/i.png')['openingHoursSpecification'];

        self::assertCount(3, $hours);
        self::assertSame('OpeningHoursSpecification', $hours[0]['@type']);
        self::assertSame('Tuesday', $hours[0]['dayOfWeek']);
        self::assertSame('10:00', $hours[0]['opens']);
        self::assertSame('14:30', $hours[0]['closes']);
    }

    public function test_it_lists_social_profiles_and_omits_ratings(): void
    {
        $data = $this->builder()->build('https://x/i.png');

        self::assertContains('https://www.instagram.com/giulia_pizza_gorges/', $data['sameAs']);
        self::assertContains('https://www.facebook.com/GiuliaPizzaGorges/', $data['sameAs']);
        self::assertArrayNotHasKey('aggregateRating', $data);
    }
}
