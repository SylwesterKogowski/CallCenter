<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;

final class WorkerScheduleService implements WorkerScheduleServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function getWorkerScheduleForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getPredictionsForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable
    {
        return $this->notImplemented(__METHOD__);
    }

    public function assignTicketToWorker(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
        ?string $assignedById = null,
    ): WorkerScheduleAssignmentInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function removeTicketFromSchedule(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
    ): void {
        $this->notImplemented(__METHOD__);
    }

    public function autoAssignTicketsForWorker(
        string $workerId,
        \DateTimeImmutable $weekStartDate,
        ?array $categoryIds = null,
    ): iterable {
        return $this->notImplemented(__METHOD__);
    }

    public function getWorkerScheduleForPeriod(
        string $workerId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): iterable {
        return $this->notImplemented(__METHOD__);
    }

    public function getWorkerScheduleStatistics(string $workerId, \DateTimeImmutable $date): array
    {
        return $this->notImplemented(__METHOD__);
    }
}
