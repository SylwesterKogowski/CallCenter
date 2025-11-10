<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application\Dto;

interface WorkerSchedulePredictionInterface
{
    public function getDate(): \DateTimeImmutable;

    public function getPredictedTicketCount(): int;

    public function getAvailableTimeMinutes(): int;

    public function getEfficiency(): float;
}
