<?php
namespace App\Shared\UI;

use App\Shared\Domain\Month;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;

/**
 * Rend « 9 septembre ». Twig sait formater une date, mais pas nommer un mois en
 * français ; `format_datetime` de twig/intl-extra le ferait au prix d'une
 * dépendance et de l'intl, pour douze mots qui ne changeront jamais.
 */
final class DayMonthExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('day_month', $this->dayMonth(...), ['needs_environment' => true])];
    }

    public function dayMonth(Environment $env, \DateTimeImmutable $date): string
    {
        // Même fuseau d'affichage que le filtre `date` (voir twig.yaml) : sans
        // cette conversion, un instant stocké en UTC bascule la veille passé
        // 22 heures.
        $local = $date->setTimezone($env->getExtension(CoreExtension::class)->getTimezone());

        return $local->format('j') . ' ' . Month::fromDate($local)->label();
    }
}
