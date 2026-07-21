<?php
namespace App\Shared\UI;

use App\Shared\Domain\Establishment;
use App\Shared\Domain\EstablishmentRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EstablishmentExtension extends AbstractExtension
{
    public function __construct(private EstablishmentRepositoryInterface $repository) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('establishment', $this->repository->get(...))];
    }
}
