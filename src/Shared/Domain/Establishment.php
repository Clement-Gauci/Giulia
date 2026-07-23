<?php
namespace App\Shared\Domain;

final readonly class Establishment
{
    /** @param SocialLink[] $socialLinks */
    public function __construct(
        private string $name,
        private string $tagline,
        private string $address,
        private float $latitude,
        private float $longitude,
        private string $phone,
        private string $phoneHref,
        private string $email,
        private string $menuPdfUrl,
        private string $orderUrl,
        private string $directionsUrl,
        private string $googleReviewsUrl,
        private string $whatsappUrl,
        private array $socialLinks,
        private Announcement $announcement,
    ) {}

    public function name(): string { return $this->name; }
    public function tagline(): string { return $this->tagline; }
    public function address(): string { return $this->address; }
    public function latitude(): float { return $this->latitude; }
    public function longitude(): float { return $this->longitude; }
    public function phone(): string { return $this->phone; }
    public function phoneHref(): string { return $this->phoneHref; }
    public function email(): string { return $this->email; }
    public function menuPdfUrl(): string { return $this->menuPdfUrl; }
    public function orderUrl(): string { return $this->orderUrl; }
    public function directionsUrl(): string { return $this->directionsUrl; }
    public function googleReviewsUrl(): string { return $this->googleReviewsUrl; }
    public function whatsappUrl(): string { return $this->whatsappUrl; }
    /** @return SocialLink[] */
    public function socialLinks(): array { return $this->socialLinks; }
    public function announcement(): Announcement { return $this->announcement; }
}
