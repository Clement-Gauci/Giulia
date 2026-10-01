<?php
namespace App\Menu\Domain;

use App\Shared\Domain\Money;

final readonly class Pizza
{
    /** Diamètre de référence de la carte, en centimètres. */
    public const STANDARD_DIAMETER_CM = 33;

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
        private ?string $photo = null,
        private int $diameterCm = self::STANDARD_DIAMETER_CM,
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
    /** Nom de base des fichiers photo, sans extension ; null tant qu'aucune photo n'est disponible. */
    public function photo(): ?string { return $this->photo; }
    public function hasPhoto(): bool { return $this->photo !== null; }
    /** Diamètre de la pizza en centimètres. */
    public function diameterCm(): int { return $this->diameterCm; }
    public function hasStandardDiameter(): bool { return $this->diameterCm === self::STANDARD_DIAMETER_CM; }

    public function hasTag(Tag $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
