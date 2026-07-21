<?php
namespace App\Opening\UI;

use App\Opening\Domain\Clock;
use App\Opening\Domain\OpeningStatus;
use App\Opening\Domain\ScheduleRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OpeningStatusExtension extends AbstractExtension
{
    public function __construct(
        private ScheduleRepositoryInterface $schedule,
        private Clock $clock,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('opening_status', $this->status(...))];
    }

    public function status(): OpeningStatus
    {
        return OpeningStatus::compute($this->schedule->schedule(), $this->clock->now());
    }
}
