<?php
namespace App\Legal\Infrastructure;

use App\Legal\Domain\Host;
use App\Legal\Domain\LegalNotice;
use App\Legal\Domain\LegalRepositoryInterface;
use Symfony\Component\Yaml\Yaml;

final class YamlLegalRepository implements LegalRepositoryInterface
{
    public function __construct(private string $file) {}

    public function get(): LegalNotice
    {
        $d = Yaml::parseFile($this->file);
        $e = $d['editor'];
        $h = $d['host'];

        return new LegalNotice(
            $e['legal_name'], $e['legal_form'], $e['capital'], $e['siren'], $e['siret'],
            $e['rcs'], $e['vat'], $e['ape'], $e['publication_director'],
            new Host($h['name'], $h['address'], $h['phone'], $h['note'] ?? null),
        );
    }
}
