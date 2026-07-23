<?php
namespace App\Seo\Application;

use App\Opening\Domain\ScheduleRepositoryInterface;
use App\Shared\Domain\EstablishmentRepositoryInterface;
use App\Shared\Domain\SocialLink;
use App\Shared\Domain\Weekday;

final readonly class StructuredDataBuilder
{
    public function __construct(
        private EstablishmentRepositoryInterface $establishments,
        private ScheduleRepositoryInterface $schedule,
        private string $siteUrl,
    ) {}

    /** @return array<string, mixed> */
    public function build(string $imageUrl): array
    {
        $e = $this->establishments->get();
        $base = rtrim($this->siteUrl, '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $e->name(),
            'url' => $base,
            'telephone' => $e->phoneHref(),
            'servesCuisine' => 'Pizza napolitaine',
            'priceRange' => '€€',
            'acceptsReservations' => false,
            'image' => $imageUrl,
            'hasMenu' => $base . $e->menuPdfUrl(),
            'address' => $this->address($e->address()),
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $e->latitude(),
                'longitude' => $e->longitude(),
            ],
            'openingHoursSpecification' => $this->openingHours(),
            'sameAs' => array_values(array_map(
                static fn (SocialLink $l) => $l->url(),
                $e->socialLinks(),
            )),
        ];
    }

    /** @return array<string, string> */
    private function address(string $full): array
    {
        $address = ['@type' => 'PostalAddress', 'addressCountry' => 'FR'];

        if (preg_match('/^(.*?),?\s*(\d{5})\s+(.+)$/u', $full, $m) === 1) {
            $address['streetAddress'] = trim($m[1]);
            $address['postalCode'] = $m[2];
            $address['addressLocality'] = trim($m[3]);
        } else {
            $address['streetAddress'] = $full;
        }

        return $address;
    }

    /** @return list<array<string, string>> */
    private function openingHours(): array
    {
        $schedule = $this->schedule->schedule();
        $specs = [];

        foreach (Weekday::cases() as $day) {
            foreach ($schedule->rangesFor($day) as $range) {
                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $day->name,
                    'opens' => $this->hm($range->openMinute()),
                    'closes' => $this->hm($range->closeMinute()),
                ];
            }
        }

        return $specs;
    }

    private function hm(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
