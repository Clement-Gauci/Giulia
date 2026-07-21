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
            $d['email'], $d['menu_pdf_url'], $d['order_url'], $d['directions_url'], $d['google_reviews_url'],
            $d['whatsapp_url'], $links,
            new Announcement((bool) $a['active'], $a['title'], $a['text']),
        );
    }
}
