<?php
namespace App\Dashboard\UI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Coquille du dashboard. Les écrans de gestion (carte, horaires, congés) sont le
 * chantier suivant ; cette page existe pour que la connexion ait une destination
 * et que le pare-feu ait quelque chose à protéger.
 */
final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }
}
