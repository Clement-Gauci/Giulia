<?php

namespace App\Home\UI;

use App\Menu\Domain\MenuRepositoryInterface;
use App\Menu\Domain\SpecialRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(MenuRepositoryInterface $menu, SpecialRepositoryInterface $special): Response
    {
        return $this->render('home/index.html.twig', [
            'special' => $special->current(),
            'featured' => $menu->signature(),
            'categories' => $menu->categories(),
        ]);
    }
}
