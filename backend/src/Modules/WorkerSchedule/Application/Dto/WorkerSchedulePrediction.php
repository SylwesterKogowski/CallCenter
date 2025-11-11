<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application\Dto;

final class WorkerSchedulePrediction implements WorkerSchedulePredictionInterface
{
    public function __construct(
        private readonly \DateTimeImmutable $date,
        private readonly int $predictedTicketCount,
        private readonly int $availableTimeMinutes,
        private readonly float $efficiency,
    ) {
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getPredictedTicketCount(): int
    {
        return $this->predictedTicketCount;
    }

    public function getAvailableTimeMinutes(): int
    {
        return $this->availableTimeMinutes;
    }

    public function getEfficiency(): float
    {
        return $this->efficiency;
    }
}
