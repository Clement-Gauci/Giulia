<?php
namespace App\Opening\Domain;

interface ScheduleRepositoryInterface
{
    public function schedule(): WeeklySchedule;
}
