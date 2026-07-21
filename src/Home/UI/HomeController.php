<?php

namespace App\Home\UI;

use App\Menu\Domain\MenuRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(MenuRepositoryInterface $menu): Response
    {
        return $this->render('home/index.html.twig', [
            'featured' => $menu->featured(),
            'categories' => $menu->categories(),
        ]);
    }
}
