<?php

namespace App\Menu\UI;

use App\Menu\Domain\MenuRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/nos-pizzas', name: 'menu_index', methods: ['GET'])]
    public function index(MenuRepositoryInterface $menu): Response
    {
        return $this->render('menu/index.html.twig', ['categories' => $menu->categories()]);
    }

    #[Route('/nos-pizzas/{slug}', name: 'menu_show', methods: ['GET'])]
    public function show(string $slug, MenuRepositoryInterface $menu): Response
    {
        $pizza = $menu->findBySlug($slug);
        if ($pizza === null) {
            throw $this->createNotFoundException('Pizza introuvable.');
        }

        return $this->render('menu/show.html.twig', ['pizza' => $pizza]);
    }
}
