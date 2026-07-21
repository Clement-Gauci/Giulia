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
