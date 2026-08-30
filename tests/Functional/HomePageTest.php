<?php
namespace App\Tests\Functional;

use App\Menu\Domain\MonthlySpecial;
use App\Menu\Domain\SpecialRepositoryInterface;
use App\Menu\Infrastructure\YamlSpecialRepository;
use App\Shared\Domain\Money;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function test_home_renders_key_blocks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge'); // statut d'ouverture
        self::assertSelectorTextContains('body', 'Click & Collect');
    }

    public function test_click_and_collect_cta_points_to_order_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorExists('a#commander[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }

    public function test_pizza_du_moment_block_shows_special(): void
    {
        $client = static::createClient();
        $this->useSpecial($client, self::aSpecial());

        $client->request('GET', '/');

        self::assertSelectorTextContains('.featured__label', 'Pizza du moment');
        self::assertSelectorTextContains('.featured__name', 'La Diavola');
        self::assertSelectorTextContains('.featured', 'Nduja de Calabre');
        self::assertSelectorExists('.featured a.featured__cta[href="https://giuliapizzas.foxorders.com/carte-giulia-pizzas-gorges-44190.html"]');
    }

    public function test_pizza_du_moment_shows_period_and_pitch(): void
    {
        $client = static::createClient();
        $this->useSpecial($client, self::aSpecial());

        $client->request('GET', '/');

        self::assertSelectorTextContains('.featured__period', 'Édition de printemps');
        self::assertSelectorTextContains('.featured__pitch', 'Le piquant qui réveille');
    }

    public function test_falls_back_to_the_signature_pizza_when_no_special(): void
    {
        $client = static::createClient();
        $this->useSpecial($client, null);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.featured__label', 'La signature');
        self::assertSelectorNotExists('.featured__period');
        self::assertSelectorTextContains('.featured .featured__cta', 'Voir la fiche');
    }

    /** Remplace la source de la pizza du moment : les tests ne dépendent pas de special.yaml. */
    private function useSpecial(KernelBrowser $client, ?MonthlySpecial $special): void
    {
        // Le kernel est rebooté avant chaque requête : on le désactive pour que
        // le double survive jusqu'à l'appel HTTP.
        $client->disableReboot();
        // HomeController dépend de l'interface, aliasée vers le service concret :
        // c'est ce dernier qu'il faut remplacer.
        static::getContainer()->set(YamlSpecialRepository::class, new class($special) implements SpecialRepositoryInterface {
            public function __construct(private ?MonthlySpecial $special) {}

            public function current(): ?MonthlySpecial
            {
                return $this->special;
            }
        });
    }

    private static function aSpecial(): MonthlySpecial
    {
        return new MonthlySpecial(
            'La Diavola',
            'Édition de printemps',
            'Le piquant qui réveille.',
            ['Nduja de Calabre', 'Mozzarella fior di latte'],
            Money::fromCents(1690),
            [],
        );
    }
}
