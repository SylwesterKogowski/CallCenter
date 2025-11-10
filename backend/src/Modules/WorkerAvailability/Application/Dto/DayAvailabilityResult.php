<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application\Dto;

use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;

final class DayAvailabilityResult implements DayAvailabilityResultInterface
{
    /**
     * @param iterable<WorkerAvailabilityInterface> $timeSlots
     */
    public function __construct(
        private readonly \DateTimeImmutable $date,
        private readonly iterable $timeSlots,
        private readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getTimeSlots(): iterable
    {
        return $this->timeSlots;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
