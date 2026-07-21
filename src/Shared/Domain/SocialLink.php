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
