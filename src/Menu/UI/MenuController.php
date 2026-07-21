<?php

namespace App\Menu\UI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/nos-pizzas', name: 'menu_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('menu/index.html.twig');
    }

    #[Route('/nos-pizzas/{slug}', name: 'menu_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        return $this->render('menu/show.html.twig', ['slug' => $slug]);
    }
}
