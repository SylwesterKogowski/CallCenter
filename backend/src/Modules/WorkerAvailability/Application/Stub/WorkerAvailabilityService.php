<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\WorkerAvailability\Application\Dto\CopyAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\Dto\DayAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;

final class WorkerAvailabilityService implements WorkerAvailabilityServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function getWorkerAvailabilityForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable
    {
        return $this->notImplemented(__METHOD__);
    }

    public function replaceWorkerAvailabilityForDay(
        string $workerId,
        \DateTimeImmutable $date,
        iterable $timeSlots,
    ): DayAvailabilityResultInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function updateWorkerAvailabilitySlot(
        string $workerId,
        string $timeSlotId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): WorkerAvailabilityInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function removeWorkerAvailabilitySlot(string $workerId, string $timeSlotId): \DateTimeImmutable
    {
        return $this->notImplemented(__METHOD__);
    }

    public function copyWorkerAvailability(
        string $workerId,
        \DateTimeImmutable $sourceDate,
        array $targetDates,
        bool $overwrite,
    ): CopyAvailabilityResultInterface {
        return $this->notImplemented(__METHOD__);
    }
}
