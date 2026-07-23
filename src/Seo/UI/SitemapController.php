<?php
namespace App\Seo\UI;

use App\Seo\Application\SitemapUrlProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function index(SitemapUrlProvider $provider): Response
    {
        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $provider->urls()]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
