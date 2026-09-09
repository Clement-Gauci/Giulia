<?php
namespace App\Account\Domain;

interface LoginCodeRepositoryInterface
{
    /**
     * Enregistre le code courant de l'adresse, en REMPLAÇANT le précédent s'il
     * en existe un. Une adresse n'a donc jamais qu'un seul code valable, ce qui
     * évite l'ambiguïté du « utilisez le plus récent ».
     */
    public function save(LoginCode $code): void;

    /** Le code courant de l'adresse, qu'il soit encore valable ou non. */
    public function findFor(string $email): ?LoginCode;

    public function deleteFor(string $email): void;
}
