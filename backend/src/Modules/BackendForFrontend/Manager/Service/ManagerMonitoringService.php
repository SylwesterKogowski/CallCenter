<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Service;

use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;
use App\Modules\BackendForFrontend\Manager\Persistence\Entity\ManagerAutoAssignmentSettings;
use App\Modules\BackendForFrontend\Manager\Persistence\ManagerAutoAssignmentSettingsRepositoryInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

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
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly TicketCategoryServiceInterface $ticketCategoryService,
        private readonly ManagerAutoAssignmentSettingsRepositoryInterface $settingsRepository,
        private readonly WorkerScheduleServiceInterface $workerScheduleService,
        ?callable $nowFactory = null,
    ) {
        $this->nowFactory = $nowFactory ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable();
    }

    public function getMonitoringData(string $managerId, \DateTimeImmutable $date): array
    {
        $this->assertManagerId($managerId);

        $connection = $this->entityManager->getConnection();
        $dateString = $date->format('Y-m-d');
        $assignments = $this->workerScheduleService->fetchAssignmentsForDate($date);
        $workerIds = array_values(array_unique(array_column($assignments, 'worker_id')));
        $timeSpentMap = $this->fetchTimeSpentMap($connection, $workerIds, $date);

        $workerStats = $this->buildWorkerStats($assignments, $timeSpentMap);
        $queueStats = $this->buildQueueStats($assignments);
        $summary = $this->buildSummary($workerStats, $queueStats, $connection);

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

        $connection = $this->entityManager->getConnection();
        $workerIds = $this->fetchNonManagerWorkerIds($connection);

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
     * @param string[] $workerIds
     *
     * @return array<string, int>
     */
    private function fetchTimeSpentMap(Connection $connection, array $workerIds, \DateTimeImmutable $date): array
    {
        if ([] === $workerIds) {
            return [];
        }

        $start = $date->setTime(0, 0, 0);
        $end = $start->add(new \DateInterval('P1D'));

        $sql = <<<SQL
SELECT
    worker_id,
    SUM(
        CASE
            WHEN duration_minutes IS NOT NULL THEN duration_minutes
            WHEN ended_at IS NOT NULL THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, started_at, ended_at))
            ELSE GREATEST(0, TIMESTAMPDIFF(MINUTE, started_at, :endOfDay))
        END
    ) AS minutes
FROM ticket_registered_time
WHERE worker_id IN (:workerIds)
  AND started_at >= :start
  AND started_at < :end
GROUP BY worker_id
SQL;

        $params = [
            'workerIds' => $workerIds,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'endOfDay' => $end->format('Y-m-d H:i:s'),
        ];

        $types = [
            'workerIds' => Connection::PARAM_STR_ARRAY,
            'start' => ParameterType::STRING,
            'end' => ParameterType::STRING,
            'endOfDay' => ParameterType::STRING,
        ];

        $rows = $connection->fetchAllAssociative($sql, $params, $types);

        $map = [];

        foreach ($rows as $row) {
            $workerId = (string) ($row['worker_id'] ?? '');
            $minutes = (int) ($row['minutes'] ?? 0);
            $map[$workerId] = max(0, $minutes);
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @param array<string, int>               $timeSpentMap
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildWorkerStats(array $assignments, array $timeSpentMap): array
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

            $ratio = $timePlanned > 0 ? $timeSpent / $timePlanned : 0.0;
            $stat['workloadLevel'] = $this->determineWorkloadLevel($ratio);
            $stat['efficiency'] = round(min(max($ratio, 0), 2), 2);
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
    private function buildSummary(array $workerStats, array $queueStats, Connection $connection): array
    {
        $totalWorkers = (int) $connection->fetchOne('SELECT COUNT(*) FROM workers WHERE is_manager = 0');
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
    private function fetchNonManagerWorkerIds(Connection $connection): array
    {
        /** @var list<string> $ids */
        $ids = $connection->fetchFirstColumn('SELECT id FROM workers WHERE is_manager = 0 ORDER BY login ASC');

        return array_values(array_filter(array_map('strval', $ids)));
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
