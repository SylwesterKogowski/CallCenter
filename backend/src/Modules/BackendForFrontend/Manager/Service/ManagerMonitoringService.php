<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Service;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;
use App\Modules\BackendForFrontend\Manager\Persistence\Entity\ManagerAutoAssignmentSettings;
use App\Modules\BackendForFrontend\Manager\Persistence\ManagerAutoAssignmentSettingsRepositoryInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;

/**
 * Testy w {@see \Tests\Unit\Modules\BackendForFrontend\Manager\Service\ManagerMonitoringServiceTest}.
 */
final class ManagerMonitoringService implements ManagerMonitoringServiceInterface
{
    /**
     * @var callable():\DateTimeImmutable
     */
    private $nowFactory;

    /**
     * @var array<string, string>|null
     */
    private ?array $categoryNamesById = null;

    public function __construct(
        private readonly AuthenticationServiceInterface $authenticationService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly TicketCategoryServiceInterface $ticketCategoryService,
        private readonly TicketServiceInterface $ticketService,
        private readonly ManagerAutoAssignmentSettingsRepositoryInterface $settingsRepository,
        private readonly WorkerScheduleServiceInterface $workerScheduleService,
        private readonly WorkerAvailabilityServiceInterface $workerAvailabilityService,
        ?callable $nowFactory = null,
    ) {
        $this->nowFactory = $nowFactory ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable();
    }

    public function getMonitoringData(string $managerId, \DateTimeImmutable $date): array
    {
        $this->assertManagerId($managerId);

        $dateString = $date->format('Y-m-d');
        $assignments = $this->workerScheduleService->fetchAssignmentsForDate($date);
        $workerIds = array_values(array_unique(array_column($assignments, 'worker_id')));
        $timeSpentMap = $this->ticketService->getWorkersTimeSpentForDate($workerIds, $date);

        $workerStats = $this->buildWorkerStats($assignments, $timeSpentMap, $date);
        $queueStats = $this->buildQueueStats($assignments);
        $summary = $this->buildSummary(
            $workerStats,
            $queueStats,
            $this->authenticationService->countNonManagerWorkers(),
        );

        $settings = $this->settingsRepository->find($managerId);
        $settingsPayload = $this->normalizeSettings($settings ?? new ManagerAutoAssignmentSettings($managerId));

        return [
            'date' => $dateString,
            'summary' => $summary,
            'workerStats' => array_values($workerStats),
            'queueStats' => array_values($queueStats),
            'autoAssignmentSettings' => $settingsPayload,
        ];
    }

    public function updateAutoAssignmentSettings(
        string $managerId,
        bool $enabled,
        UpdateAutoAssignmentSettingsInput $settings,
    ): array {
        $entity = $this->settingsRepository->find($managerId);

        if (!$entity instanceof ManagerAutoAssignmentSettings) {
            $entity = new ManagerAutoAssignmentSettings($managerId);
        }

        $entity->setEnabled($enabled);
        $entity->setConsiderEfficiency($settings->considerEfficiency);
        $entity->setConsiderAvailability($settings->considerAvailability);
        $entity->setMaxTicketsPerWorker($settings->maxTicketsPerWorker);

        $this->settingsRepository->save($entity);

        return [
            'autoAssignmentSettings' => $this->normalizeSettings($entity),
            'updatedAt' => $entity->getUpdatedAt(),
        ];
    }

    public function triggerAutoAssignment(string $managerId, \DateTimeImmutable $date): array
    {
        $this->assertManagerId($managerId);

        $workerIds = $this->authenticationService->getNonManagerWorkerIds();

        $totalAssigned = 0;
        $assignedTo = [];

        foreach ($workerIds as $workerId) {
            $assignments = $this->workerScheduleService->autoAssignTicketsForWorker($workerId, $date);
            $count = 0;

            foreach ($assignments as $assignment) {
                ++$count;
            }

            if ($count > 0) {
                $assignedTo[] = [
                    'workerId' => $workerId,
                    'ticketsCount' => $count,
                ];
                $totalAssigned += $count;
            }
        }

        $now = $this->now();

        $settings = $this->settingsRepository->find($managerId);

        if (!$settings instanceof ManagerAutoAssignmentSettings) {
            $settings = new ManagerAutoAssignmentSettings($managerId);
        }

        $settings->setLastRun($now, $totalAssigned);
        $this->settingsRepository->save($settings);

        return [
            'message' => 'Automatyczne przypisywanie zostało uruchomione.',
            'ticketsAssigned' => $totalAssigned,
            'assignedTo' => $assignedTo,
            'completedAt' => $now,
        ];
    }

    public function streamMonitoringEvents(
        string $managerId,
        \DateTimeImmutable $date,
        callable $emit,
    ): void {
        $emit('heartbeat', ['managerId' => $managerId, 'date' => $date->format('Y-m-d')], $this->now());
    }

    private function assertManagerId(string $managerId): void
    {
        if ('' === trim($managerId)) {
            throw new \InvalidArgumentException('Manager id cannot be empty.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @param array<string, int>               $timeSpentMap
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildWorkerStats(array $assignments, array $timeSpentMap, \DateTimeImmutable $date): array
    {
        $stats = [];
        $resolutionTotals = [];
        $resolutionCounts = [];

        foreach ($assignments as $row) {
            $workerId = (string) ($row['worker_id'] ?? '');

            if ('' === $workerId) {
                continue;
            }

            if (!isset($stats[$workerId])) {
                $stats[$workerId] = [
                    'workerId' => $workerId,
                    'workerLogin' => (string) ($row['worker_login'] ?? $workerId),
                    'ticketsCount' => 0,
                    'timeSpent' => 0,
                    'timePlanned' => 0,
                    'workloadLevel' => 'low',
                    'efficiency' => 0.0,
                    'categories' => $this->resolveWorkerCategories($workerId),
                    'completedTickets' => 0,
                    'inProgressTickets' => 0,
                    'waitingTickets' => 0,
                ];
            }

            ++$stats[$workerId]['ticketsCount'];
            $stats[$workerId]['timePlanned'] += max(
                0,
                (int) ($row['category_default_resolution_minutes'] ?? 0),
            );

            $status = $this->mapTicketStatus((string) ($row['status'] ?? ''));

            if ('completed' === $status) {
                ++$stats[$workerId]['completedTickets'];
            } elseif ('in_progress' === $status) {
                ++$stats[$workerId]['inProgressTickets'];
            } else {
                ++$stats[$workerId]['waitingTickets'];
            }
        }

        foreach ($stats as $workerId => &$stat) {
            $timePlanned = max(0, (int) $stat['timePlanned']);
            $timeSpent = $timeSpentMap[$workerId] ?? 0;
            $stat['timeSpent'] = $timeSpent;

            $availableTime = $this->workerAvailabilityService->getAvailableTimeForDate($workerId, $date);
            $workloadRatio = $availableTime > 0 ? $timePlanned / $availableTime : 0.0;
            $stat['workloadLevel'] = $this->determineWorkloadLevel($workloadRatio);

            $efficiencyRatio = $timePlanned > 0 ? $timeSpent / $timePlanned : 0.0;
            $stat['efficiency'] = round(min(max($efficiencyRatio, 0), 2), 2);
        }
        unset($stat);

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildQueueStats(array $assignments): array
    {
        $stats = [];

        foreach ($assignments as $row) {
            $categoryId = (string) ($row['category_id'] ?? '');

            if ('' === $categoryId) {
                continue;
            }

            if (!isset($stats[$categoryId])) {
                $stats[$categoryId] = [
                    'queueId' => $categoryId,
                    'queueName' => (string) ($row['category_name'] ?? $categoryId),
                    'waitingTickets' => 0,
                    'inProgressTickets' => 0,
                    'completedTickets' => 0,
                    'totalTickets' => 0,
                    'averageResolutionTime' => 0,
                    'assignedWorkers' => [],
                ];
            }

            $stat = &$stats[$categoryId];
            ++$stat['totalTickets'];
            $status = $this->mapTicketStatus((string) ($row['status'] ?? ''));

            if ('completed' === $status) {
                ++$stat['completedTickets'];
            } elseif ('in_progress' === $status) {
                ++$stat['inProgressTickets'];
            } else {
                ++$stat['waitingTickets'];
            }

            $workerId = (string) ($row['worker_id'] ?? '');

            if ('' !== $workerId) {
                $stat['assignedWorkers'][$workerId] = true;
            }

            if (null !== ($row['created_at'] ?? null) && null !== ($row['closed_at'] ?? null)) {
                try {
                    $createdAt = new \DateTimeImmutable((string) $row['created_at']);
                    $closedAt = new \DateTimeImmutable((string) $row['closed_at']);
                    $minutes = max(0, (int) floor(($closedAt->getTimestamp() - $createdAt->getTimestamp()) / 60));
                    $resolutionTotals[$categoryId] = ($resolutionTotals[$categoryId] ?? 0) + $minutes;
                    $resolutionCounts[$categoryId] = ($resolutionCounts[$categoryId] ?? 0) + 1;
                } catch (\Throwable) {
                    // Ignore invalid datetime entries.
                }
            }
            unset($stat);
        }

        foreach ($stats as $categoryId => &$stat) {
            $stat['assignedWorkers'] = \count($stat['assignedWorkers']);

            $count = $resolutionCounts[$categoryId] ?? 0;

            if ($count > 0) {
                $total = $resolutionTotals[$categoryId] ?? 0;
                $stat['averageResolutionTime'] = (int) round($total / max(1, $count));
            }
        }
        unset($stat);

        return $stats;
    }

    /**
     * @param array<string, array<string, mixed>> $workerStats
     * @param array<string, array<string, mixed>> $queueStats
     *
     * @return array<string, int|float>
     */
    private function buildSummary(array $workerStats, array $queueStats, int $totalWorkers): array
    {
        $totalTickets = 0;
        $waiting = 0;
        $inProgress = 0;
        $completed = 0;
        $kpiRatios = [];

        foreach ($workerStats as $stat) {
            $totalTickets += (int) $stat['ticketsCount'];
            $waiting += (int) $stat['waitingTickets'];
            $inProgress += (int) $stat['inProgressTickets'];
            $completed += (int) $stat['completedTickets'];

            $timePlanned = (int) $stat['timePlanned'];
            $timeSpent = (int) $stat['timeSpent'];
            $ratio = $timePlanned > 0 ? $timeSpent / $timePlanned : 0.0;
            $kpiRatios[] = $ratio;
        }

        $averageWorkload = 0.0;

        if (\count($kpiRatios) > 0) {
            $averageWorkload = round(array_sum($kpiRatios) / \count($kpiRatios) * 100, 2);
        }

        $avgResolutionSamples = [];
        foreach ($queueStats as $queue) {
            if (($queue['averageResolutionTime'] ?? 0) > 0) {
                $avgResolutionSamples[] = (int) $queue['averageResolutionTime'];
            }
        }

        $averageResolution = 0;

        if (\count($avgResolutionSamples) > 0) {
            $averageResolution = (int) round(array_sum($avgResolutionSamples) / \count($avgResolutionSamples));
        }

        return [
            'totalTickets' => $totalTickets,
            'totalWorkers' => $totalWorkers,
            'totalQueues' => \count($queueStats),
            'averageWorkload' => $averageWorkload,
            'averageResolutionTime' => $averageResolution,
            'waitingTicketsTotal' => $waiting,
            'inProgressTicketsTotal' => $inProgress,
            'completedTicketsTotal' => $completed,
        ];
    }

    /**
     * @return string[]
     */
    private function resolveWorkerCategories(string $workerId): array
    {
        $categoryIds = $this->authorizationService->getAssignedCategoryIds($workerId);

        if ([] === $categoryIds) {
            return [];
        }

        if (null === $this->categoryNamesById) {
            $this->categoryNamesById = [];

            foreach ($this->ticketCategoryService->getAllCategories() as $category) {
                $this->categoryNamesById[$category->getId()] = $category->getName();
            }
        }

        $names = [];

        foreach ($categoryIds as $categoryId) {
            $names[] = $this->categoryNamesById[$categoryId] ?? $categoryId;
        }

        sort($names);

        return $names;
    }

    private function mapTicketStatus(string $status): string
    {
        return match ($status) {
            TicketInterface::STATUS_CLOSED => 'completed',
            TicketInterface::STATUS_IN_PROGRESS => 'in_progress',
            TicketInterface::STATUS_AWAITING_RESPONSE,
            TicketInterface::STATUS_AWAITING_CUSTOMER => 'waiting',
            default => 'waiting',
        };
    }

    private function determineWorkloadLevel(float $ratio): string
    {
        return match (true) {
            $ratio >= 1.0 => 'critical',
            $ratio >= 0.8 => 'high',
            $ratio >= 0.5 => 'normal',
            default => 'low',
        };
    }

    /**
     * @return array{
     *     enabled: bool,
     *     lastRun: \DateTimeImmutable|null,
     *     ticketsAssigned: int,
     *     settings: array{
     *         considerEfficiency: bool,
     *         considerAvailability: bool,
     *         maxTicketsPerWorker: int
     *     }
     * }
     */
    private function normalizeSettings(ManagerAutoAssignmentSettings $settings): array
    {
        return [
            'enabled' => $settings->isEnabled(),
            'lastRun' => $settings->getLastRun(),
            'ticketsAssigned' => $settings->getTicketsAssigned(),
            'settings' => [
                'considerEfficiency' => $settings->shouldConsiderEfficiency(),
                'considerAvailability' => $settings->shouldConsiderAvailability(),
                'maxTicketsPerWorker' => $settings->getMaxTicketsPerWorker(),
            ],
        ];
    }

    private function now(): \DateTimeImmutable
    {
        return ($this->nowFactory)();
    }
}
