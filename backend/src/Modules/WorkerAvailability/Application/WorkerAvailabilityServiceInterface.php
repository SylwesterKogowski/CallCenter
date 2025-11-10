<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application;

use App\Modules\WorkerAvailability\Application\Dto\CopyAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\Dto\DayAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;

interface WorkerAvailabilityServiceInterface
{
    /**
     * @return iterable<WorkerAvailabilityInterface>
     */
    public function getWorkerAvailabilityForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable;

    /**
     * @param iterable<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $timeSlots
     */
    public function replaceWorkerAvailabilityForDay(
        string $workerId,
        \DateTimeImmutable $date,
        iterable $timeSlots,
    ): DayAvailabilityResultInterface;

    public function updateWorkerAvailabilitySlot(
        string $workerId,
        string $timeSlotId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): WorkerAvailabilityInterface;

    public function removeWorkerAvailabilitySlot(
        string $workerId,
        string $timeSlotId,
    ): \DateTimeImmutable;

    /**
     * @param \DateTimeImmutable[] $targetDates
     */
    public function copyWorkerAvailability(
        string $workerId,
        \DateTimeImmutable $sourceDate,
        array $targetDates,
        bool $overwrite,
    ): CopyAvailabilityResultInterface;
}
