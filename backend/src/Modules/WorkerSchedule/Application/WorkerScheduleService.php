<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\TicketBacklogServiceInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignment;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerSchedulePrediction;
use App\Modules\WorkerSchedule\Domain\WorkerScheduleInterface;
use App\Modules\WorkerSchedule\Domain\WorkerScheduleRepositoryInterface;
use App\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine\Entity\WorkerSchedule as WorkerScheduleEntity;
use Symfony\Component\Uid\Uuid;

final class WorkerScheduleService implements WorkerScheduleServiceInterface
{
    /**
     * @var callable():string
     */
    private $uuidFactory;

    /**
     * @var callable():\DateTimeImmutable
     */
    private $nowFactory;

    /**
     * @var array<string, TicketInterface>
     */
    private array $ticketCache = [];

    /**
     * @var array<string, WorkerInterface|null>
     */
    private array $workerCache = [];

    /**
     * @var array<string, float>
     */
    private array $efficiencyCache = [];

    public function __construct(
        private readonly WorkerScheduleRepositoryInterface $repository,
        private readonly TicketServiceInterface $ticketService,
        private readonly TicketBacklogServiceInterface $ticketBacklogService,
        private readonly WorkerAvailabilityServiceInterface $workerAvailabilityService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly TicketCategoryServiceInterface $ticketCategoryService,
        private readonly AuthenticationServiceInterface $authenticationService,
        ?callable $uuidFactory = null,
        ?callable $nowFactory = null,
    ) {
        $this->uuidFactory = $uuidFactory ?? static fn (): string => Uuid::v7()->toRfc4122();
        $this->nowFactory = $nowFactory ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable();
    }

    public function getWorkerScheduleForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable
    {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $startDate = $this->normalizeDate($weekStartDate);
        $endDate = $startDate->add(new \DateInterval('P6D'));

        $assignments = $this->repository->findByWorkerAndPeriod($normalizedWorkerId, $startDate, $endDate);

        return array_map(
            fn (WorkerScheduleInterface $schedule): WorkerScheduleAssignmentInterface => $this->createAssignmentDto(
                $schedule,
            ),
            [...$assignments],
        );
    }

    public function getPredictionsForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable
    {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $startDate = $this->normalizeDate($weekStartDate);
        $availabilitySlots = [...$this->workerAvailabilityService->getWorkerAvailabilityForWeek(
            $normalizedWorkerId,
            $startDate,
        )];
        $availabilityByDate = $this->groupAvailabilityMinutesByDate($availabilitySlots);

        $categoryIds = $this->authorizationService->getAssignedCategoryIds($normalizedWorkerId);
        $categories = [] !== $categoryIds
            ? $this->ticketCategoryService->getCategoriesByIds($categoryIds)
            : $this->ticketCategoryService->getAllCategories();

        if ([] === $categories) {
            $defaultResolution = 30;
        } else {
            $defaultResolution = max(
                5,
                (int) round(array_sum(array_map(
                    static fn (TicketCategoryInterface $category): int => max(
                        1,
                        $category->getDefaultResolutionTimeMinutes(),
                    ),
                    $categories,
                )) / max(\count($categories), 1)),
            );
        }

        $worker = $this->getWorker($normalizedWorkerId);
        $efficiency = $this->computeAverageEfficiency($worker, $categories);
        $adjustedTicketDuration = max(1, (int) round($defaultResolution / max($efficiency, 0.1)));

        $predictions = [];
        $current = $startDate;

        for ($i = 0; $i < 7; ++$i) {
            $key = $current->format('Y-m-d');
            $availableMinutes = $availabilityByDate[$key] ?? 0;
            $predictedCount = (int) floor($availableMinutes / $adjustedTicketDuration);

            $predictions[] = new WorkerSchedulePrediction(
                $current,
                max(0, $predictedCount),
                $availableMinutes,
                $efficiency,
            );

            $current = $current->add(new \DateInterval('P1D'));
        }

        return $predictions;
    }

    public function assignTicketToWorker(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
        ?string $assignedById = null,
    ): WorkerScheduleAssignmentInterface {
        $normalizedTicketId = $this->normalizeId($ticketId, 'Ticket id cannot be empty.');
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $scheduled = $this->normalizeDate($scheduledDate);

        $this->assertDateNotInPast($scheduled);

        $ticket = $this->getTicket($normalizedTicketId);
        $categoryId = $ticket->getCategory()->getId();

        if (!$this->authorizationService->canWorkerAccessCategory($normalizedWorkerId, $categoryId)) {
            throw new \RuntimeException('Worker cannot access ticket category.');
        }

        $this->assertWorkerIsAvailableOnDate($normalizedWorkerId, $scheduled);

        $existing = $this->repository->findOneByWorkerTicketAndDate(
            $normalizedTicketId,
            $normalizedWorkerId,
            $scheduled,
        );

        if ($existing instanceof WorkerScheduleInterface) {
            throw new \RuntimeException('Ticket is already scheduled for worker on the given date.');
        }

        $assignment = new WorkerScheduleEntity(
            $this->nextUuid(),
            $normalizedWorkerId,
            $normalizedTicketId,
            $scheduled,
            $this->now(),
            $assignedById,
            false,
            null,
        );

        $this->repository->save($assignment);

        return $this->createAssignmentDto($assignment, $ticket);
    }

    public function removeTicketFromSchedule(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
    ): void {
        $normalizedTicketId = $this->normalizeId($ticketId, 'Ticket id cannot be empty.');
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $scheduled = $this->normalizeDate($scheduledDate);

        $assignment = $this->repository->findOneByWorkerTicketAndDate(
            $normalizedTicketId,
            $normalizedWorkerId,
            $scheduled,
        );

        if (!$assignment instanceof WorkerScheduleInterface) {
            throw new \RuntimeException('Schedule assignment not found.');
        }

        $this->repository->remove($assignment);
    }

    public function autoAssignTicketsForWorker(
        string $workerId,
        \DateTimeImmutable $weekStartDate,
        ?array $categoryIds = null,
    ): iterable {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $startDate = $this->normalizeDate($weekStartDate);
        $endDate = $startDate->add(new \DateInterval('P6D'));

        $existingAssignments = array_map(
            fn (WorkerScheduleInterface $schedule): WorkerScheduleAssignmentInterface => $this->createAssignmentDto(
                $schedule,
            ),
            [...$this->repository->findByWorkerAndPeriod($normalizedWorkerId, $startDate, $endDate)],
        );

        $assignedByDate = $this->groupAssignmentsByDate($existingAssignments);
        $availabilityMinutes = $this->groupAvailabilityMinutesByDate(
            [...$this->workerAvailabilityService->getWorkerAvailabilityForWeek($normalizedWorkerId, $startDate)],
        );
        $usedMinutes = $this->mapUsedMinutesByDate($assignedByDate);

        $remainingMinutes = [];
        $current = $startDate;
        for ($i = 0; $i < 7; ++$i) {
            $key = $current->format('Y-m-d');
            $remainingMinutes[$key] = max(0, ($availabilityMinutes[$key] ?? 0) - ($usedMinutes[$key] ?? 0));
            $current = $current->add(new \DateInterval('P1D'));
        }

        $filterCategories = null === $categoryIds ? [] : array_values(array_unique(array_map(
            static fn (string $id): string => trim($id),
            $categoryIds,
        )));

        $filters = new WorkerBacklogFilters(
            categories: $filterCategories,
            statuses: [],
            priorities: [],
        );

        $backlog = $this->ticketBacklogService->getWorkerBacklog($normalizedWorkerId, $filters);
        $scheduledTickets = [];
        foreach ($assignedByDate as $dateAssignments) {
            foreach ($dateAssignments as $assignment) {
                $scheduledTickets[$assignment->getTicket()->getId()] = true;
            }
        }

        $newAssignments = [];
        $current = $startDate;

        foreach ($backlog->getTickets() as $ticketItem) {
            $ticket = $ticketItem->getTicket();
            $estimatedMinutes = $this->calculateEstimatedTimeMinutes($ticket, $normalizedWorkerId);

            if (isset($scheduledTickets[$ticket->getId()])) {
                continue;
            }

            $scheduled = false;
            $current = $startDate;

            for ($dayIndex = 0; $dayIndex < 7; ++$dayIndex) {
                $dateKey = $current->format('Y-m-d');

                if (($remainingMinutes[$dateKey] ?? 0) < $estimatedMinutes) {
                    $current = $current->add(new \DateInterval('P1D'));
                    continue;
                }

                $existing = $this->repository->findOneByWorkerTicketAndDate(
                    $ticket->getId(),
                    $normalizedWorkerId,
                    $current,
                );

                if ($existing instanceof WorkerScheduleInterface) {
                    $current = $current->add(new \DateInterval('P1D'));
                    continue;
                }

                $assignment = new WorkerScheduleEntity(
                    $this->nextUuid(),
                    $normalizedWorkerId,
                    $ticket->getId(),
                    $current,
                    $this->now(),
                    null,
                    true,
                    null,
                );

                $this->repository->save($assignment);
                $dto = $this->createAssignmentDto($assignment, $ticket);

                $newAssignments[] = $dto;
                $assignedByDate[$dateKey][] = $dto;
                $remainingMinutes[$dateKey] -= $estimatedMinutes;
                $scheduledTickets[$ticket->getId()] = true;
                $scheduled = true;

                break;
            }

            if (!$scheduled) {
                continue;
            }
        }

        return $newAssignments;
    }

    public function getWorkerScheduleForPeriod(
        string $workerId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): iterable {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        if ($end < $start) {
            throw new \InvalidArgumentException('End date must be greater or equal to start date.');
        }

        $assignments = $this->repository->findByWorkerAndPeriod($normalizedWorkerId, $start, $end);

        return array_map(
            fn (WorkerScheduleInterface $schedule): WorkerScheduleAssignmentInterface => $this->createAssignmentDto(
                $schedule,
            ),
            [...$assignments],
        );
    }

    public function getWorkerScheduleStatistics(string $workerId, \DateTimeImmutable $date): array
    {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $scheduleDate = $this->normalizeDate($date);
        $assignments = $this->repository->findByWorkerAndDate($normalizedWorkerId, $scheduleDate);

        $ticketsCount = 0;
        $timePlanned = 0;
        $completed = 0;
        $inProgress = 0;
        $waiting = 0;

        foreach ($assignments as $assignment) {
            ++$ticketsCount;
            $ticket = $this->getTicket($assignment->getTicketId());
            $timePlanned += $this->calculateEstimatedTimeMinutes($ticket, $normalizedWorkerId);

            $status = $ticket->getStatus();
            if (TicketInterface::STATUS_CLOSED === $status) {
                ++$completed;
            } elseif (TicketInterface::STATUS_IN_PROGRESS === $status) {
                ++$inProgress;
            } else {
                ++$waiting;
            }
        }

        return [
            'ticketsCount' => $ticketsCount,
            'timePlanned' => $timePlanned,
            'completedTickets' => $completed,
            'inProgressTickets' => $inProgress,
            'waitingTickets' => $waiting,
        ];
    }

    public function fetchAssignmentsForDate(\DateTimeImmutable $date): array
    {
        $normalizedDate = $this->normalizeDate($date);

        return $this->repository->fetchAssignmentsForDate($normalizedDate);
    }

    /**
     * @param WorkerScheduleAssignmentInterface[] $assignments
     *
     * @return array<string, WorkerScheduleAssignmentInterface[]>
     */
    private function groupAssignmentsByDate(array $assignments): array
    {
        $grouped = [];

        foreach ($assignments as $assignment) {
            $dateKey = $assignment->getScheduledDate()->format('Y-m-d');
            $grouped[$dateKey][] = $assignment;
        }

        return $grouped;
    }

    /**
     * @param array<string, list<WorkerScheduleAssignmentInterface>> $groupedAssignments
     *
     * @return array<string, int>
     */
    private function mapUsedMinutesByDate(array $groupedAssignments): array
    {
        $minutes = [];

        foreach ($groupedAssignments as $date => $assignments) {
            $total = 0;

            foreach ($assignments as $assignment) {
                $total += max(1, $assignment->getEstimatedTimeMinutes());
            }

            $minutes[$date] = $total;
        }

        return $minutes;
    }

    /**
     * @param iterable<WorkerAvailabilityInterface> $slots
     *
     * @return array<string, int>
     */
    private function groupAvailabilityMinutesByDate(iterable $slots): array
    {
        $grouped = [];

        foreach ($slots as $slot) {
            $dateKey = $slot->getDate()->format('Y-m-d');
            $grouped[$dateKey] = ($grouped[$dateKey] ?? 0) + $slot->getDurationMinutes();
        }

        return $grouped;
    }

    private function assertWorkerIsAvailableOnDate(string $workerId, \DateTimeImmutable $date): void
    {
        $availability = $this->workerAvailabilityService->getWorkerAvailabilityForWeek($workerId, $date);

        foreach ($availability as $slot) {
            if ($slot->getDate()->format('Y-m-d') === $date->format('Y-m-d')) {
                return;
            }
        }

        throw new \RuntimeException('Worker is not available on the selected date.');
    }

    private function assertDateNotInPast(\DateTimeImmutable $date): void
    {
        $today = $this->now()->setTime(0, 0);

        if ($date < $today) {
            throw new \InvalidArgumentException('Schedule date cannot be in the past.');
        }
    }

    private function normalizeId(string $value, string $message): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($message);
        }

        return $normalized;
    }

    private function normalizeDate(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }

    private function nextUuid(): string
    {
        return ($this->uuidFactory)();
    }

    private function now(): \DateTimeImmutable
    {
        return ($this->nowFactory)();
    }

    private function getTicket(string $ticketId): TicketInterface
    {
        if (!isset($this->ticketCache[$ticketId])) {
            $ticket = $this->ticketService->getTicketById($ticketId);

            if (!$ticket instanceof TicketInterface) {
                throw new \RuntimeException(sprintf('Ticket "%s" not found.', $ticketId));
            }

            $this->ticketCache[$ticketId] = $ticket;
        }

        return $this->ticketCache[$ticketId];
    }

    private function getWorker(string $workerId): ?WorkerInterface
    {
        if (!array_key_exists($workerId, $this->workerCache)) {
            $this->workerCache[$workerId] = $this->authenticationService->getWorkerById($workerId);
        }

        return $this->workerCache[$workerId];
    }

    private function calculateEstimatedTimeMinutes(TicketInterface $ticket, string $workerId): int
    {
        $category = $ticket->getCategory();
        $defaultMinutes = max(1, $category->getDefaultResolutionTimeMinutes());
        $efficiency = $this->resolveWorkerCategoryEfficiency($workerId, $category);

        return max(1, (int) round($defaultMinutes / max($efficiency, 0.1)));
    }

    private function resolveWorkerCategoryEfficiency(string $workerId, TicketCategoryInterface $category): float
    {
        $cacheKey = $workerId.'|'.$category->getId();

        if (!array_key_exists($cacheKey, $this->efficiencyCache)) {
            $worker = $this->getWorker($workerId);

            if (null === $worker) {
                $this->efficiencyCache[$cacheKey] = 1.0;
            } else {
                $value = $this->ticketService->calculateWorkerEfficiency($worker, $category);
                $this->efficiencyCache[$cacheKey] = $value > 0 ? $value : 1.0;
            }
        }

        return $this->efficiencyCache[$cacheKey];
    }

    /**
     * @param TicketCategoryInterface[] $categories
     */
    private function computeAverageEfficiency(?WorkerInterface $worker, array $categories): float
    {
        if (null === $worker || [] === $categories) {
            return 1.0;
        }

        $values = [];

        foreach ($categories as $category) {
            $value = $this->ticketService->calculateWorkerEfficiency($worker, $category);

            if ($value > 0) {
                $values[] = $value;
            }
        }

        if ([] === $values) {
            return 1.0;
        }

        return round(array_sum($values) / \count($values), 2);
    }

    private function createAssignmentDto(
        WorkerScheduleInterface $schedule,
        ?TicketInterface $ticket = null,
    ): WorkerScheduleAssignmentInterface {
        $ticket ??= $this->getTicket($schedule->getTicketId());

        return new WorkerScheduleAssignment(
            $schedule->getId(),
            $schedule->getWorkerId(),
            $ticket,
            $schedule->getScheduledDate(),
            $schedule->getAssignedAt(),
            $this->calculateEstimatedTimeMinutes($ticket, $schedule->getWorkerId()),
            $schedule->getPriority(),
            $schedule->isAutoAssigned(),
        );
    }
}
