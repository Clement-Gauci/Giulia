<?php
namespace App\Opening\UI;

use App\Opening\Domain\Clock;
use App\Opening\Domain\ClosureRepositoryInterface;
use App\Opening\Domain\OpeningStatus;
use App\Opening\Domain\ScheduleRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController
{
    #[Route('/api/status', name: 'status', methods: ['GET'])]
    public function __invoke(ScheduleRepositoryInterface $schedule, Clock $clock, ClosureRepositoryInterface $closures): JsonResponse
    {
        $status = OpeningStatus::compute($schedule->schedule(), $clock->now(), $closures->closures());

        return new JsonResponse([
            'open' => $status->isOpen(),
            'label' => $status->label(),
            'detail' => $status->detail(),
        ]);
    }
}
