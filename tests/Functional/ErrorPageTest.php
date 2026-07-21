<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ErrorPageTest extends WebTestCase
{
    public function test_route_inexistante_renvoie_404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cette-page-nexiste-pas');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_template_erreur_est_brande_et_adapte_au_code(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->get('request_stack')->push(Request::create('/'));
        $twig = $container->get('twig');
        $tpl = 'bundles/TwigBundle/Exception/error.html.twig';

        $html404 = $twig->render($tpl, ['status_code' => 404]);
        self::assertStringContainsString('Cette page est partie en livraison', $html404);
        self::assertStringContainsString('Erreur 404', $html404);
        self::assertStringContainsString('help-strip', $html404); // bandeau « une faim pressante »

        $html500 = $twig->render($tpl, ['status_code' => 500]);
        self::assertStringContainsString('Notre four fait des siennes', $html500);
    }
}
