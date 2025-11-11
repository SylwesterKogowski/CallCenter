<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application;

use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerSchedulePredictionInterface;

interface WorkerScheduleServiceInterface
{
    /**
     * @return iterable<WorkerScheduleAssignmentInterface>
     */
    public function getWorkerScheduleForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable;

    /**
     * @return iterable<WorkerSchedulePredictionInterface>
     */
    public function getPredictionsForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable;

    public function assignTicketToWorker(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
        ?string $assignedById = null,
    ): WorkerScheduleAssignmentInterface;

    public function removeTicketFromSchedule(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
    ): void;

    /**
     * @param string[]|null $categoryIds
     *
     * @return iterable<WorkerScheduleAssignmentInterface>
     */
    public function autoAssignTicketsForWorker(
        string $workerId,
        \DateTimeImmutable $weekStartDate,
        ?array $categoryIds = null,
    ): iterable;

    /**
     * @return iterable<WorkerScheduleAssignmentInterface>
     */
    public function getWorkerScheduleForPeriod(
        string $workerId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): iterable;

    /**
     * @return array{
     *     ticketsCount: int,
     *     timePlanned: int,
     *     completedTickets: int,
     *     inProgressTickets: int,
     *     waitingTickets: int
     * }
     */
    public function getWorkerScheduleStatistics(string $workerId, \DateTimeImmutable $date): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAssignmentsForDate(\DateTimeImmutable $date): array;
}
