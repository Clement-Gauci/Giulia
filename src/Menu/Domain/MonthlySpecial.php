<?php
namespace App\Menu\Domain;

use App\Shared\Domain\Money;

final readonly class MonthlySpecial
{
    /**
     * @param string[] $ingredients
     * @param Tag[]    $tags
     */
    public function __construct(
        private string $name,
        private ?string $period,
        private ?string $pitch,
        private array $ingredients,
        private Money $price,
        private array $tags,
        private ?string $photo = null,
    ) {}

    public function name(): string { return $this->name; }
    public function period(): ?string { return $this->period; }
    public function pitch(): ?string { return $this->pitch; }
    /** @return string[] */
    public function ingredients(): array { return $this->ingredients; }
    public function price(): Money { return $this->price; }
    /** @return Tag[] */
    public function tags(): array { return $this->tags; }

    /** Nom de base du fichier photo, sans extension ; null tant qu'aucune photo n'est disponible. */
    public function photo(): ?string { return $this->photo; }
    public function hasPhoto(): bool { return $this->photo !== null; }

    public function hasTag(Tag $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
