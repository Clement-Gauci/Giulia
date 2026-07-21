<?php

namespace App\Legal\UI;

use App\Legal\Domain\LegalRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal', methods: ['GET'])]
    public function index(LegalRepositoryInterface $legal): Response
    {
        return $this->render('legal/mentions.html.twig', [
            'notice' => $legal->get(),
        ]);
    }
}
