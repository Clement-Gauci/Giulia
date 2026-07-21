<?php
namespace App\Legal\Domain;

final readonly class LegalNotice
{
    public function __construct(
        private string $legalName,
        private string $legalForm,
        private string $capital,
        private string $siren,
        private string $siret,
        private string $rcs,
        private string $vat,
        private string $ape,
        private string $publicationDirector,
        private Host $host,
    ) {}

    public function legalName(): string { return $this->legalName; }
    public function legalForm(): string { return $this->legalForm; }
    public function capital(): string { return $this->capital; }
    public function siren(): string { return $this->siren; }
    public function siret(): string { return $this->siret; }
    public function rcs(): string { return $this->rcs; }
    public function vat(): string { return $this->vat; }
    public function ape(): string { return $this->ape; }
    public function publicationDirector(): string { return $this->publicationDirector; }
    public function host(): Host { return $this->host; }
}
