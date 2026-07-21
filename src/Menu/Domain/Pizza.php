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
        private bool $signature,
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
    public function isSignature(): bool { return $this->signature; }

    public function hasTag(Tag $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
