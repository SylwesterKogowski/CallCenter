<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Domain;

/**
 * Persistence boundary for worker availability aggregates.
 *
 * Provides data access capabilities required by the worker availability
 * application services.
 */
interface WorkerAvailabilityRepositoryInterface
{
    public function findById(string $id): ?WorkerAvailabilityInterface;

    /**
     * @return iterable<WorkerAvailabilityInterface>
     */
    public function findForDate(string $workerId, \DateTimeImmutable $date): iterable;

    /**
     * @return iterable<WorkerAvailabilityInterface>
     */
    public function findForPeriod(
        string $workerId,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
    ): iterable;

    public function save(WorkerAvailabilityInterface $availability): void;

    public function remove(WorkerAvailabilityInterface $availability): void;

    public function removeAllForDate(string $workerId, \DateTimeImmutable $date): void;
}
