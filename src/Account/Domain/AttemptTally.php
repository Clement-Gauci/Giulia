<?php
namespace App\Account\Domain;

/**
 * Décompte de tentatives sur une fenêtre : combien, et la plus récente.
 * Les deux répondent en une seule requête, parce que les règles ont toujours
 * besoin des deux — le plafond se lit sur le compte, le délai sur l'instant.
 */
final readonly class AttemptTally
{
    public function __construct(
        public int $count,
        public ?\DateTimeImmutable $lastAt,
    ) {}
}
