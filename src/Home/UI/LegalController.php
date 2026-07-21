<?php

namespace App\Home\UI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }
}
