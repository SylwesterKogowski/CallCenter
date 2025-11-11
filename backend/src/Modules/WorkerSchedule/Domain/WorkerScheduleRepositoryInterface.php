<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Domain;

interface WorkerScheduleRepositoryInterface
{
    public function findById(string $id): ?WorkerScheduleInterface;

    /**
     * @return iterable<WorkerScheduleInterface>
     */
    public function findByWorkerAndDate(string $workerId, \DateTimeImmutable $date): iterable;

    /**
     * @return iterable<WorkerScheduleInterface>
     */
    public function findByWorkerAndPeriod(
        string $workerId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): iterable;

    /**
     * @return iterable<WorkerScheduleInterface>
     */
    public function findByTicketAndDate(string $ticketId, \DateTimeImmutable $date): iterable;

    public function findOneByWorkerTicketAndDate(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $date,
    ): ?WorkerScheduleInterface;

    public function save(WorkerScheduleInterface $assignment): void;

    public function remove(WorkerScheduleInterface $assignment): void;
}
